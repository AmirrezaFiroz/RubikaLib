<?php

declare(strict_types=1);

namespace RubikaLib;

use Ratchet\Client\WebSocket;
use React\EventLoop\Loop;
use RubikaLib\enums\{
    chatActivities,
    deleteType,
    Sort
};
use RubikaLib\interfaces\{
    MainSettings,
    Runner
};
use RubikaLib\Utils\{
    Optimal,
    Tools
};

final class Main
{
    private ?Runner $runner;
    private ?int $phone_number;
    private ?Requests $req;
    private ?Session $session;
    public static $VERSION = '1.2.0';
    private ?Cryption $crypto;

    public function __construct(
        int $phone_number,
        string $app_name = '',
        private MainSettings $settings = (new MainSettings)
    ) {
        $this->phone_number = Tools::phoneNumberParse($phone_number);

        if (!Session::is_session($this->phone_number)) {
            $this->req = new Requests($settings->userAgent, $settings->auth, mainSettings: $settings);
            $this->session = new Session($this->phone_number);
            $send_code = $this->sendCode();

            if ($send_code['status'] == 'SendPassKey') {
                while (true) {
                    $pass_key = readline("enter your passkey ({$send_code['hint_pass_key']}) : ");
                    $send_code = $this->sendCode($pass_key);

                    if ($send_code['status'] == 'OK') {
                        break;
                    }
                }
            }

            $this->session
                ->changeData('step', 'getCode')
                ->changeData('phone_code_hash', $send_code['phone_code_hash'])
                ->changeData('code_digits_count', $send_code['code_digits_count'])
                ->changeData('useragent', $this->req->useragent);

            list($signIn, $private_key) = [[], ''];
            while (true) {
                $code = (int)readline("enter code ({$send_code['code_digits_count']}-digits) : ");

                if (strlen((string)$code) == $send_code['code_digits_count']) {
                    list($signIn, $private_key) = $this->signIn($send_code['phone_code_hash'], $code);
                    break;
                }
            }

            $auth = Cryption::decrypt_RSA_by_key($private_key, $signIn['auth']);
            unset($signIn['user']['online_time']);
            $this->session->regenerate_session();
            $this->session
                ->changeData('auth', $auth)
                ->changeData('user', $signIn['user'])
                ->changeData('private_key', $private_key)
                ->changeData('useragent', $this->req->useragent)
                ->auth = $auth;
            $this->req = new Requests(auth: $auth, private_key: $private_key, useragent: $this->req->useragent, mainSettings: $settings);

            $this->registerDevice($app_name);
        } else {
            $this->session = new Session($this->phone_number);
            $this->req = new Requests(
                auth: $this->session->auth,
                private_key: $this->session->data['private_key'] ?? '',
                useragent: $this->session->data['useragent'],
                mainSettings: $settings
            );

            switch ($this->session->data['step']) {
                case 'getCode':
                    list($signIn, $private_key) = [[], ''];
                    while (1) {
                        $code = (int)readline("enter code ({$this->session->data['code_digits_count']}-digits) : ");

                        if (strlen((string)$code) == $this->session->data['code_digits_count']) {
                            list($signIn, $private_key) = $this->signIn($this->session->data['phone_code_hash'], $code);
                            break;
                        }
                    }

                    $auth = Cryption::decrypt_RSA_by_key($private_key, $signIn['auth']);
                    unset($signIn['online_time']);
                    $this->session->regenerate_session();
                    $this->session
                        ->changeData('auth', $auth)
                        ->changeData('user', $signIn['user'])
                        ->changeData('private_key', $private_key)
                        ->auth = $auth;
                    $this->req = new Requests(auth: $auth, private_key: $private_key, useragent: $this->req->useragent, mainSettings: $settings);

                    $this->registerDevice($app_name);
                    break;
            }
        }

        $this->crypto = new Cryption($this->req->auth, $this->session->data['private_key']);
    }

    /**
     * send login code to phone number
     *
     * @param string $pass_key if account have password
     * @throws Logger INVALID_INPUT
     * @return array
     */
    private function sendCode(string $pass_key = ''): array
    {
        $d = [
            'phone_number' => (string)$this->phone_number,
            'send_type' => 'SMS'
        ];
        if ($pass_key != '') {
            $d['pass_key'] = $pass_key;
        }

        $r = $this->req->making_request('sendCode', $d, $this->session, true)['data'];

        if (!in_array($r['status'], ['OK', 'SendPassKey'])) {
            throw new Logger('there is an error in result: ' . json_encode(['status' => 'OK', 'status_det' => $r['status']]));
        } else {
            return $r;
        }
    }

    /**
     * sign in to API
     *
     * @param string $phone_code_hash
     * @param integer $code
     * @throws Logger CodeIsExpired, CodeIsInvalid, CodeIsUsed
     * @return array [API result, private_key]
     */
    private function signIn(string $phone_code_hash, int $code): array
    {
        list($publicKey, $privateKey) = cryption::RSA_KeyGenerate();

        $r = $this->req->making_request('signIn', [
            "phone_number" =>            (string)$this->phone_number,
            "phone_code_hash" => $phone_code_hash,
            "phone_code" => $code,
            "public_key" => $publicKey
        ], $this->session, true)['data'];

        if ($r['status'] != 'OK') {
            throw new Logger('there is an error in result: ' . json_encode(['status' => 'OK', 'status_det' => $r['status']]));
        } else {
            return [$r, $privateKey];
        }
    }

    /**
     * register login data as a device
     *
     * @param string $app_name it will be showen in device_model (max length = 10)
     * @return array API result
     */
    private function registerDevice(string $app_name = ''): array
    {
        if (strlen($app_name) != 0 && $app_name > 10) {
            $app_name = substr($app_name, 0, 10);
        }

        $d = [
            'token_type' => 'Web',
            'token' => '',
            'app_version' => 'WB_4.4.15',
            'lang_code' => 'fa',
            'system_version' => Tools::getOS($this->req->useragent),
            'device_model' => ($app_name != '' ? "Rubika-lib($app_name) " . self::$VERSION : 'Rubika-lib ' . self::$VERSION),
            'device_hash' => Tools::generate_device_hash($this->req->useragent)
        ];
        $r = $this->req->making_request('registerDevice', $d, $this->session);

        if ($r['status'] != 'OK') {
            throw new Logger('there is an error in result: ' . json_encode(['status' => 'OK', 'status_det' => $r['status']]));
        } else {
            return $r;
        }
    }

    /**
     * terminate this session
     *
     * @return array API result
     */
    public function logout(): array
    {
        $this->session->terminate();
        return $this->req->making_request('logout', array(), $this->session)['data'];
    }

    /**
     * get account sessions
     *
     * @return array API result
     */
    public function getMySessions(): array
    {
        return $this->req->making_request('getMySessions', array(), $this->session)['data'];
    }

    /**
     * terminate session
     *
     * @return array API result
     */
    public function terminateSession(string $session_key): array
    {
        return $this->req->making_request('terminateSession', [
            'session_key' => $session_key
        ], $this->session)['data'];
    }

    /**
     * get session data
     *
     * @return array user data of session
     */
    public function getMySelf(): array
    {
        return $this->session->data['user'];
    }

    /**
     * send text to someone or somewhere
     *
     * @param string $guid object_guid
     * @param string $text message
     * @param string $reply_to_message_id if you have to reply
     * @return array API result
     */
    public function sendMessage(string $guid, string $text, string $reply_to_message_id = ''): array
    {
        $d = [
            'object_guid' => $guid,
            'rnd' => (string)mt_rand(10000000, 999999999),
            'text' => $text
        ];
        if ($reply_to_message_id != '') {
            $d['reply_to_message_id'] = $reply_to_message_id;
        }
        return $this->req->making_request('sendMessage', $d, $this->session)['data'];
    }

    /**
     * edit message text
     *
     * @param string $guid object_guid
     * @param string $text message
     * @param int $message_id
     * @return array API result
     */
    public function editMessage(string $guid, string $text, string $message_id): array
    {
        return $this->req->making_request('editMessage', [
            'object_guid' => $guid,
            'text' => $text,
            'message_id' => $message_id
        ], $this->session)['data'];
    }

    /**
     * forward message text
     *
     * @param string $from_object_guid
     * @param array $message_ids
     * @param string $to_object_guid
     * @return array API result
     */
    public function forwardMessages(string $from_object_guid, array $message_ids, string $to_object_guid): array
    {
        return $this->req->making_request('forwardMessages', [
            'from_object_guid' => $from_object_guid,
            'message_ids' => $message_ids,
            'rnd' => (string)mt_rand(10000000, 999999999),
            'to_object_guid' => $to_object_guid
        ], $this->session)['data'];
    }

    /**
     * delete message from chat
     *
     * @param string $object_guid
     * @param array $message_ids
     * @param deleteType $type local or global
     * @return array
     */
    public function deleteMessages(string $object_guid, array $message_ids, deleteType $type = deleteType::Local): array
    {
        return $this->req->making_request('deleteMessages', [
            'object_guid' => $object_guid,
            'message_ids' => $message_ids,
            'type' => $type->value
        ], $this->session)['data'];
    }

    /**
     * send chat action (on top of page)
     *
     * @param string $guid
     * @param chatActivities $activity
     * @return array API result
     */
    public function sendChatActivity(string $guid, chatActivities $activity): array
    {
        return $this->req->making_request('sendChatActivity', [
            'object_guid' => $guid,
            'activity' => $activity->value
        ], $this->session)['data'];
    }

    /**
     * get chats list
     *
     * @param int $start_id
     * @return array API result
     */
    public function getChats(int $start_id = 0): array
    {
        return $this->req->making_request('getChats', [
            'start_id' => $start_id
        ], $this->session)['data'];
    }

    /**
     * join to channel or group
     *
     * @param string $enterKey guid or join link
     * @return array API result
     */
    public function joinChat(string $enterKey): array
    {
        if (filter_var($enterKey, FILTER_VALIDATE_URL)) {
            if (str_contains($enterKey, 'rubika.ir/joing')) {
                $method = 'joinGroup';
            } elseif (str_contains($enterKey, 'rubika.ir/joinc')) {
                $method = 'joinChannelByLink';
            } else {
                exit;
            }
        } else {
            $method = 'joinChannelAction';
        }
        $t = explode('/', $enterKey);
        return $this->req->making_request($method, filter_var($enterKey, FILTER_VALIDATE_URL) ? [
            'hash_link' => $t[count($t) - 1]
        ] : [
            'channel_guid' => $enterKey,
            'action' => 'Join'
        ], $this->session)['data'];
    }

    /**
     * leave channel or group
     *
     * @param string $guid
     * @return array API result
     */
    public function leaveChat(string $guid): array
    {
        $chatType = strtolower((string)Tools::ChatType_guid($guid));
        $d = [
            "{$chatType}_guid" => $guid
        ];
        if ($chatType == 'Channel') {
            $d['action'] = 'Leave';
        }
        return $this->req->making_request($chatType == 'group' ? 'leaveGroup' : 'joinChannelAction', $d, $this->session)['data'];
    }

    /**
     * delete group for all users
     *
     * @param string $group_guid
     * @return array API result
     */
    public function deleteGroup(string $group_guid): array
    {
        return $this->req->making_request('removeGroup', [
            'group_guid' => $group_guid
        ], $this->session)['data'];
    }

    /**
     * get stickers list
     *
     * @return array API result
     */
    public function getMyStickerSets(): array
    {
        return $this->req->making_request('getMyStickerSets', [], $this->session)['data'];
    }

    /**
     * get folders list
     *
     * @return array API result
     */
    public function getFolders(): array
    {
        return $this->req->making_request('getFolders', [], $this->session)['data'];
    }

    /**
     * get all chats updates
     *
     * @param int $state
     * @return array API result
     */
    public function getChatsUpdates(int $state = 0): array
    {
        return $this->req->making_request('getChatsUpdates', [
            'state' => $state
        ], $this->session)['data'];
    }

    /**
     * get all chat messages (recommanded use it in async mode)
     *
     * @param string $guid
     * @param integer $middle_message_id
     * @return array API result
     */
    public function getMessagesInterval(string $guid, int $middle_message_id): array
    {
        return $this->req->making_request('getMessagesInterval', [
            'object_guid' => $guid,
            'middle_message_id' => $middle_message_id
        ], $this->session)['data'];
    }

    /**
     * get all messages from chat (recommanded use it in async mode)
     *
     * @param string $guid
     * @param integer $message_id max_id or min_id
     * @param Sort $sort
     * @return array API result
     */
    // public function getMessages(string $guid, int $message_id, Sort $sort = Sort::FromMax): array
    // {
    //     return $this->req->making_request('getMessages', [
    //         'object_guid' => $guid,
    //         'sort' => $sort->value,
    //         str_replace('from', '', strtolower($sort->value)) . '_id' => $message_id
    //     ], $this->session)['data'];
    // }

    /**
     * get groups onlines count
     *
     * @param string $group_guid
     * @return array API result
     */
    public function getGroupOnlineCount(string $group_guid): array
    {
        return $this->req->making_request('getGroupOnlineCount', [
            'group_guid' => $group_guid
        ], $this->session)['data'];
    }

    /**
     * seend chats
     *
     * @param string $guid
     * @param string $last_message_id
     * @return array API result
     */
    public function seenChats(string $guid, string $last_message_id): array
    {
        return $this->req->making_request('seenChats', [
            'seen_list' => [
                $guid => $last_message_id
            ]
        ], $this->session)['data'];
    }

    /**
     * seend chats
     *
     * @param string $start_id next user_guid u0HMRYa...
     * @return array API result
     */
    public function getContacts(string $start_id = ''): array
    {
        if ($start_id != '') {
            return $this->req->making_request('getContacts', [
                'start_id' => $start_id
            ], $this->session)['data'];
        }
        return $this->req->making_request('getContacts', [], $this->session)['data'];
    }

    /**
     * add new contact
     *
     * @param integer $phone_number 9123456789
     * @param string $first_name
     * @param string $last_name
     * @return array API result
     */
    public function addContact(int $phone_number, string $first_name, string $last_name = ''): array
    {
        return $this->req->making_request('addAddressBook', [
            'phone' => '+' . Tools::phoneNumberParse($phone_number),
            'first_name' => $first_name,
            'last_name' => $last_name
        ], $this->session)['data'];
    }

    /**
     * delete contact
     *
     * @param string $user_guid
     * @return array API result
     */
    public function deleteContact(string $user_guid): array
    {
        return $this->req->making_request('deleteContact', [
            'user_guid' => $user_guid
        ], $this->session)['data'];
    }

    /**
     * send contact
     *
     * @param string $guidchat guid
     * @param string $first_name contact first name
     * @param string $contact_guid contact guid (if exists)
     * @param integer $phone_number 9123456789 contact number
     * @param string $last_name
     * @param string $reply_to_message_id
     * @return array API result
     */
    public function sendContact(string $guid, string $first_name, int $phone_number, string $contact_guid = '', string $last_name = '', string $reply_to_message_id = '0'): array
    {
        $d = [
            'object_guid' => $guid,
            'rnd' => (string)mt_rand(10000000, 999999999),
            'message_contact' => [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'phone_number' => Tools::phoneNumberParse($phone_number)
            ]
        ];
        if ($contact_guid != '') {
            $d['message_contact']['user_guid'] = $contact_guid;
        }
        if ($reply_to_message_id != '0') {
            $d['reply_to_message_id'] = $reply_to_message_id;
        }
        return $this->req->making_request('sendMessage', $d, $this->session)['data'];
    }

    /**
     * get chat info with guid
     *
     * @param string $guid
     * @return array API result
     */
    public function getChatInfo(string $guid): array
    {
        return $this->req->making_request('get' . Tools::ChatType_guid($guid) . 'Info', [
            strtolower(Tools::ChatType_guid($guid)) . '_guid' => $guid
        ], $this->session)['data'];
    }

    /**
     * get chat info with username
     *
     * @param string $username example: @rubika_lib
     * @return array API result
     */
    public function getChatInfoByUsername(string $username): array
    {
        return $this->req->making_request('getObjectInfoByUsername', [
            'username' => str_replace('@', '', $username)
        ], $this->session)['data'];
    }

    /**
     * set account username
     *
     * @param string $username example: @rubika_lib
     * @return array API result
     */
    public function changeUsername(string $username): array
    {
        $d = $this->req->making_request('updateUsername', [
            'username' => str_replace('@', '', $username)
        ], $this->session)['data'];

        if ($d['status'] == 'OK') {
            $this->session->changeData('user', $d['user']);
        }

        return $d;
    }

    /**
     * edit account info
     *
     * @param string $first_name
     * @param string $last_name
     * @param string $bio
     * @return array API result
     */
    public function editAccount(string $first_name = '', string $last_name = '', string $bio = ''): array
    {
        $d = [];
        if ($first_name != '') {
            $d['first_name'] = $first_name;
        }
        if ($last_name != '') {
            $d['last_name'] = $last_name;
        }
        if ($bio != '') {
            $d['bio'] = $bio;
        }
        $d['updated_parameters'] = [
            "first_name",
            "last_name",
            "bio"
        ];

        $d = $this->req->making_request('updateProfile', $d, $this->session)['data'];

        if (isset($d['chat_update'])) {
            $this->session->changeData('user', $d['user']);
        }

        return $d;
    }

    /**
     * request delete account
     *
     * @return array API result
     */
    public function requestDeleteAccount(): array
    {
        return $this->req->making_request('requestDeleteAccount', [], $this->session)['data'];
    }

    /**
     * get chat avatar with guid
     *
     * @param string $object_guid
     * @return array API result
     */
    public function getAvatars(string $object_guid): array
    {
        return $this->req->making_request('getAvatars', [
            'object_guid' => $object_guid
        ], $this->session)['data'];
    }

    /**
     * get group members list
     *
     * @param string $group_guid
     * @param string $search_for searh for name
     * @param integer $start_id section
     * @return array API result
     */
    public function getGroupAllMembers(string $group_guid, string $search_for = '', int $start_id = 0): array
    {
        $d = [
            'group_guid' => $group_guid
        ];
        if ($search_for != '') {
            $d['search_text'] = $search_for;
        }
        if ($start_id != 0) {
            $d['start$start_id'] = $start_id;
        }
        return $this->req->making_request('getGroupAllMembers', $d, $this->session)['data'];
    }

    /**
     * download a file
     *
     * @param string $access_hash_rec
     * @param string $file_id
     * @param string $to_path the path to file tath will be writen
     * @param integer $DC
     * @return boolean false if file not found on server or true if file has saved
     */
    public function downloadFile(string $access_hash_rec, string $file_id, string $to_path, int $DC): bool
    {
        if ($this->settings->Optimal) {
            $f = fopen('app.pdf', 'a');
            foreach ($this->req->downloadFile($access_hash_rec, $file_id, $DC) as $data) {
                if ($data === false) {
                    fclose($f);
                    return false;
                }
                fwrite($to_path, $data);
            }
            fclose($f);
            return true;
        } else {
            $r = $this->req->downloadFile($access_hash_rec, $file_id, $DC);
            if ($r === false) {
                return false;
            }
            file_put_contents($to_path, $r);
            return true;
        }
    }

    /**
     * upload file to API
     *
     * @param string $path file path or link
     * @param boolean $isLink if $path is a link
     * @return array API result
     */
    private function sendFileToAPI(string $path, bool $isLink = false): array
    {
        $fn = basename($path);
        if ($isLink) {
            if (!$this->settings->Optimal) {
                file_put_contents("lib/$fn", file_get_contents($path));
            } else {
                $f = fopen("lib/$fn", 'a');
                foreach (Optimal::getFile($path, $this->settings->userAgent) as $part) {
                    fwrite($f, $part);
                }
                fclose($f);
            }

            $path = "lib/$fn";
        }
        $ex = explode('.', $fn);
        $data = $this->requestSendFile($fn, filesize($path), $ex[count($ex) - 1]);

        return $this->req->uploadFile($path, $data['id'], $data['access_hash_send'], $data['upload_url'])['data'];
    }

    /**
     * it will use to upload file
     *
     * @param string $file_name
     * @param integer $size
     * @param string $mime
     * @return array API result
     */
    private function requestSendFile(string $file_name, int $size, string $mime): array
    {
        return $this->req->making_request('requestSendFile', [
            'file_name' => $file_name,
            'size' => $size,
            'mime' => $mime
        ], $this->session)['data'];
    }

    /**
     * set runner class for getting updates
     *
     * @param runner $class runner Object
     * @return void
     */
    public function proccess(Runner $class): void
    {
        $this->runner = $class;
        $class->onStart($this->getMySelf());
    }

    /**
     * connect to socket and get updates
     *
     * @return void
     */
    public function run(): void
    {
        $this->getChatsUpdates();

        $loop = Loop::get();

        $default_sockets = $this->req->links['default_sockets'];
        $link = $default_sockets[mt_rand(0, count($default_sockets) - 1)];

        $link = "wss://nsocket9.iranlms.ir:80/";

        \Ratchet\Client\connect($link)->then(
            function (WebSocket $conn) use ($loop) {
                $conn->on('message', function ($msg) {
                    $rawData = $msg->getPayload();
                    $cleanData = trim($rawData, " \t\n\r\0\x0B\"");
                    $h = json_decode($cleanData, true);
                    if (isset($h['data_enc'])) {
                        $update = json_decode($this->crypto->dec($h['data_enc']), true);
                        if (isset($update['show_activities'])) {
                            $update = $update['show_activities'][0];
                            $this->runner->onAction(match ($update['type']) {
                                'Typing' => chatActivities::Typing,
                                'Recording' => chatActivities::Recording,
                                'Uploading' => chatActivities::Uploading
                            }, $update['object_guid'], $update['user_activity_guid'], $this);
                        } else {
                            $this->runner->onMessage($update, $this);
                        }
                    }
                });

                $conn->send(json_encode([
                    'api_version' => '6',
                    'auth' => $this->req->auth,
                    'data' => '',
                    'method' => 'handShake'
                ]));

                $loop->addPeriodicTimer(30, function () use ($conn) {
                    $conn->send('{}');
                });
            },
            function ($e) {
                throw new Logger("error on socket connection: {$e->getMessage()}");
            }
        );

        $loop->run();
    }
}
