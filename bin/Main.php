<?php

declare(strict_types=1);

namespace RubikaLib;

use getID3;
use Ratchet\Client\WebSocket;
use React\EventLoop\Loop;
use RubikaLib\enums\{
    chatActivities,
    deleteType,
    Sort,
    HistoryForNewMembers,
    ReactionsEmoji,
    ReactionsString,
    setGroupReactions,
    groupAdminAccessList,
    pollType
};
use RubikaLib\interfaces\{
    groupDefaultAccesses,
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

    /**
     * @param integer $phone_number 989123456789 or 9123456789
     * @param string $app_name it just need in login
     * @param MainSettings $settings by default = (new MainSettings)
     */
    public function __construct(
        int $phone_number,
        string $app_name = '',
        private MainSettings $settings = new MainSettings
    ) {
        $this->phone_number = Tools::parse_true_phone_number($phone_number);

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
        $this->session->changeData('user', $this->getChatInfo($this->getMySelf()['user_guid'])['user']);
    }

    /**
     * send login code to phone number
     *
     * @param string $pass_key if account have password
     * @throws Logger INVALID_INPUT
     * @return array API result
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
            'system_version' => Tools::getOSbyUserAgent($this->req->useragent),
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



    // ======================================================= account methods ===================================================================



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
    public function editProfile(string $first_name = '', string $last_name = '', string $bio = ''): array
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
     * upload new profile picture
     *
     * @param string $file_path must be picture
     * @return array API result
     */
    public function uploadNewProfileAvatar(string $file_path): array
    {
        list($file_id, $dc_id, $access_hash_rec) = $this->sendFileToAPI($file_path);
        return $this->req->making_request('uploadAvatar', [
            'thumbnail_file_id' => $file_id,
            'main_file_id' => $file_id
        ], $this->session)['data'];
    }

    /**
     * delete profile picture
     *
     * @param string $avatar_id
     * @return void API result
     */
    public function deleteMyAvatar(string $avatar_id): array
    {
        return $this->req->making_request('deleteAvatar', [
            'object_guid' => $this->getMySelf()['user_guid'],
            'avatar_id' => $avatar_id
        ], $this->session)['data'];
    }



    // ====================================================== chating methods ==================================================================



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
     * @return array API result
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
     * seen chat
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
     * seen chat
     *
     * @param array $guids
     * @param array $last_message_ids
     * @example . here is an example:
     * seenChatsArray(['u0UBF88...', 'g0UKLD66...'],   ['91729830180', '9798103900']);
     * @return array API result
     */
    public function seenChatsArray(array $guids, array $last_message_ids): array
    {
        $list = [];
        for ($i = 0; $i < count($guids); $i++) {
            $list[] = ['guid' => $guids[$i], 'msg_id' => $last_message_ids[$i]];
        }
        return $this->req->making_request('seenChats', [
            'seen_list' => $list
        ], $this->session)['data'];
    }

    /**
     * send photo to chat
     *
     * @param string $guid
     * @param string $path image path
     * @param boolean $isLink is $path a link or not
     * @param string $caption
     * @param string $thumbnail base64 encoded picture
     * @return array API result
     */
    public function sendPhoto(string $guid, string $path, bool $isLink = false, string $caption = '', string $thumbnail = '', string $reply_to_message_id = ''): array
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

        list($width, $height, $mime) = Tools::getImageDetails($path);
        list($file_id, $dc_id, $access_hash_rec) = $this->sendFileToAPI($path);

        $d = [
            'object_guid' => $guid,
            'rnd' => (string)mt_rand(10000000, 999999999),
            'file_inline' => [
                'dc_id' => $dc_id,
                'file_id' => $file_id,
                'type' => 'Image',
                'file_name' => $fn,
                'size' => filesize($path),
                'mime' => explode('/', $mime)[1],
                'thumb_inline' => $thumbnail != '' ? $thumbnail : base64_encode(Tools::createThumbnail($path, $width)),
                'width' => $width,
                'height' => $height,
                'access_hash_rec' => $access_hash_rec
            ]
        ];
        if ($caption != '') {
            $d['text'] = $caption;
        }
        if ($reply_to_message_id != '') {
            $d['reply_to_message_id'] = $reply_to_message_id;
        }

        return $this->req->making_request('sendMessage', $d, $this->session)['data'];
    }

    /**
     * send video to guid
     *
     * @param string $guid
     * @param string $path file path or url
     * @param boolean $isLink is $path a URL or not
     * @param string $caption
     * @param string $thumbnail base64 encoded thumbnail picture
     * @return array API result
     */
    public function sendVideo(string $guid, string $path, bool $isLink = false, string $caption = '', string $thumbnail = '', string $reply_to_message_id = ''): array
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

        $getID3 = new getID3;
        $file = $getID3->analyze($path);
        if (isset($file['error'])) {
            throw new Logger("Error: " . implode("\n", $file['error']));
        }

        list($width, $height, $mime) = Tools::getImageDetails(__DIR__ . '/video.png');
        list($file_id, $dc_id, $access_hash_rec) = $this->sendFileToAPI($path);

        $d = [
            'object_guid' => $guid,
            'rnd' => (string)mt_rand(10000000, 999999999),
            'file_inline' => [
                'dc_id' => $dc_id,
                'file_id' => $file_id,
                'type' => 'Video',
                'file_name' => $fn,
                'size' => filesize($path),
                'mime' => 'mp4',
                'thumb_inline' => $thumbnail != '' ? $thumbnail : base64_encode(file_get_contents(__DIR__ . '/video.png')),
                'width' => $width,
                'height' => $height,
                'time' => $file['playtime_seconds'],
                'access_hash_rec' => $access_hash_rec
            ]
        ];
        if ($caption != '') {
            $d['text'] = $caption;
        }
        if ($reply_to_message_id != '') {
            $d['reply_to_message_id'] = $reply_to_message_id;
        }

        return $this->req->making_request('sendMessage', $d, $this->session)['data'];
    }

    /**
     * send gif to guid
     *
     * @param string $guid
     * @param string $path file path or url
     * @param boolean $isLink is $path a URL or not
     * @param string $caption
     * @param string $thumbnail base64 encoded thumbnail picture
     * @return array API result
     */
    public function sendGif(string $guid, string $path, bool $isLink = false, string $caption = '', string $thumbnail = '', string $reply_to_message_id = ''): array
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

        $getID3 = new getID3;
        $file = $getID3->analyze($path);
        if (isset($file['error'])) {
            throw new Logger("Error: " . implode("\n", $file['error']));
        }

        list($width, $height, $mime) = Tools::getImageDetails(__DIR__ . '/video.png');
        list($file_id, $dc_id, $access_hash_rec) = $this->sendFileToAPI($path);

        $d = [
            'object_guid' => $guid,
            'rnd' => (string)mt_rand(10000000, 999999999),
            'file_inline' => [
                'dc_id' => $dc_id,
                'file_id' => $file_id,
                'type' => 'Gif',
                'file_name' => $fn,
                'size' => filesize($path),
                'mime' => 'mp4',
                'thumb_inline' => $thumbnail != '' ? $thumbnail : base64_encode(file_get_contents(__DIR__ . '/video.png')),
                'width' => $width,
                'height' => $height,
                'time' => $file['playtime_seconds'],
                'access_hash_rec' => $access_hash_rec
            ]
        ];
        if ($caption != '') {
            $d['text'] = $caption;
        }
        if ($reply_to_message_id != '') {
            $d['reply_to_message_id'] = $reply_to_message_id;
        }

        return $this->req->making_request('sendMessage', $d, $this->session)['data'];
    }

    /**
     * send music to guid
     *
     * @param string $guid
     * @param string $path path or link
     * @param boolean $isLink if $path is a link
     * @param string $caption
     * @param string $thumbnail
     * @param string $reply_to_message_id
     * @return array API result
     */
    public function sendMusic(string $guid, string $path, bool $isLink = false, string $caption = '', string $thumbnail = '', string $reply_to_message_id = ''): array
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

        $getID3 = new getID3;
        $file = $getID3->analyze($path);
        if (isset($file['error'])) {
            throw new Logger("Error: " . implode("\n", $file['error']));
        }

        list($file_id, $dc_id, $access_hash_rec) = $this->sendFileToAPI($path);

        $d = [
            'object_guid' => $guid,
            'rnd' => (string)mt_rand(10000000, 999999999),
            'file_inline' => [
                'dc_id' => $dc_id,
                'file_id' => $file_id,
                'type' => 'Music',
                'file_name' => $fn,
                'size' => filesize($path),
                'mime' => 'mp3',
                'thumb_inline' => $thumbnail != '' ? $thumbnail : base64_encode(file_get_contents(__DIR__ . '/video.png')),
                'time' => $file['playtime_seconds'],
                'access_hash_rec' => $access_hash_rec,
                'music_performer' => $file['tags_html']['id3v2']['artist'][0] ?? 'rubikalib'
            ]
        ];
        if ($caption != '') {
            $d['text'] = $caption;
        }
        if ($reply_to_message_id != '') {
            $d['reply_to_message_id'] = $reply_to_message_id;
        }

        return $this->req->making_request('sendMessage', $d, $this->session)['data'];
    }

    /**
     * send message Raction
     *
     * @param string $guid
     * @param string $message_id
     * @param ReactionsEmoji|ReactionsString $reaction
     * @return array API result
     */
    public function addMessageReaction(string $guid, string $message_id, ReactionsEmoji|ReactionsString $reaction): array
    {
        return $this->req->making_request('actionOnMessageReaction', [
            'action' => 'Add',
            'reaction_id' => $reaction->value,
            'message_id' => $message_id,
            'object_guid' => $guid
        ], $this->session)['data'];
    }

    /**
     * remove message Raction
     *
     * @param string $guid
     * @param string $message_id
     * @return array API result
     */
    public function removeMessageReaction(string $guid, string $message_id): array
    {
        return $this->req->making_request('actionOnMessageReaction', [
            'action' => 'Remove',
            'message_id' => $message_id,
            'object_guid' => $guid
        ], $this->session)['data'];
    }

    /**
     * send poll to chat
     *
     * @param string $guid
     * @param string $question
     * @param boolean $is_anonymous
     * @param array $options example: ['option1', 'options2', ...]
     * @param pollType $type Regular or Quiz
     * @param string $explanation if $type if Quiz (can be empty)
     * @param integer $correct_option_index if $type is Quiz (required)
     * @param boolean $allows_multiple_answers it can't be set if $type is Quiz
     * @param string $reply_to_message_id
     * @return array API result
     */
    public function createPoll(
        string $guid,
        string $question,
        array $options,
        pollType $type,
        bool $is_anonymous = false,
        string $explanation = '',
        int $correct_option_index = 0,
        bool $allows_multiple_answers = false,
        string $reply_to_message_id = ''
    ): array {
        $d = [
            'object_guid' => $guid,
            'options' => $options,
            'rnd' => (string)mt_rand(10000000, 999999999),
            'question' => $question,
            'type' => $type->value,
            'is_anonymous' => $is_anonymous
        ];
        if ($type == pollType::Regular && $allows_multiple_answers) {
            $d['allows_multiple_answers'] = $allows_multiple_answers;
        }
        if ($type == pollType::Quiz) {
            if ($explanation != '') {
                $d['explanation'] = $explanation;
            }
            $d['correct_option_index'] = $correct_option_index;
        }
        if ($reply_to_message_id != '') {
            $d['reply_to_message_id'] = $reply_to_message_id;
        }
        return $this->req->making_request('createPoll', $d, $this->session)['data'];
    }

    /**
     * get poll statuc
     *
     * @param string $poll_id
     * @return array API result
     */
    public function getPollStatus(string $poll_id): array
    {
        return $this->req->making_request('createPoll', [
            'poll_id' => $poll_id
        ], $this->session)['data'];
    }

    /**
     * get poll option selectors
     *
     * @param string $poll_id
     * @param integer $selection_index
     * @return array API result
     */
    public function getPollOptionVoters(string $poll_id, int $selection_index): array
    {
        return $this->req->making_request('getPollOptionVoters', [
            'poll_id' => $poll_id,
            'selection_index' => $selection_index
        ], $this->session)['data'];
    }

    /**
     * send location
     *
     * @param string $guid
     * @param float $latitude
     * @param float $longitude
     * @return array API result
     */
    public function sendLocation(string $guid, float $latitude, float $longitude): array
    {
        return $this->req->making_request('sendmessage', [
            'object_guid' => $guid,
            'rnd' => (string)mt_rand(10000000, 999999999),
            'location' => [
                'latitude' => $latitude,
                'longitude' => $longitude
            ]
        ], $this->session)['data'];
    }

    /**
     * get account gifs list
     *
     * @return array API result
     */
    public function getMyGifSet(): array
    {
        return $this->req->making_request('getMyGifSet', [], $this->session)['data'];
    }

    /**
     * vote a poll
     *
     * @param string $poll_id
     * @param integer ...$selection_indexs
     * @example . votePoll('ifnaonoasd...', 0) or votePoll('ifnaonoasd...', 0, 1) or votePoll('ifnaonoasd...', 2, 3)
     * @return array
     */
    public function votePoll(string $poll_id, int ...$selection_indexs): array
    {
        $list = $selection_indexs[0];
        array_splice($selection_indexs, 0, 1);
        $selection_indexs = array_unique($selection_indexs);
        foreach ($selection_indexs as $selection) {
            $list .= ',' . (string)$selection;
        }
        return $this->req->making_request('votePoll', [
            'poll_id' => $poll_id,
            'selection_index' => $list
        ], $this->session)['data'];
    }



    // ======================================================= join and leave methods ===================================================================



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
        $chatType = strtolower((string)Tools::getChatType_byGuid($guid));
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
     * create new group
     *
     * @param string $title
     * @param array $members example: ["u0HMRZ...", "u08UBju..."]
     * @return array API result
     */
    public function createGroup(string $title, array $members): array
    {
        return $this->req->making_request('addGroup', [
            'title' => $title,
            'member_guids' => $members
        ], $this->session)['data'];
    }



    // ======================================================= folders methods ===================================================================



    /**
     * get folders list
     *
     * @return array API result
     */
    public function getFolders(): array
    {
        return $this->req->making_request('getFolders', [], $this->session)['data'];
    }



    // ======================================================= stickers methods ===================================================================



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
     * get sticker set data by sticker set id
     *
     * @param string $sticker_set_ids
     * @return array API result
     */
    public function getStickersBySetIDs(string $sticker_set_ids): array
    {
        return $this->req->making_request('getStickersBySetIDs', [
            'sticker_set_ids' => $sticker_set_ids
        ], $this->session)['data'];
    }

    // public function sendSticker(string $guid): array
    // {
    //     $d = [
    //         'object_guid' => $guid,
    //         'rnd' => (string)mt_rand(10000000, 999999999),
    //         'sticker' => [
    //             "emoji_character" => "😠",
    //             "w_h_ratio" => "1.0",
    //             "sticker_id" => "5e0cbc97b424cfe782c3f1cf",
    //             "file" => [
    //                 "file_id" => "492739289",
    //                 "mime" => "png",
    //                 "dc_id" => "32",
    //                 "access_hash_rec" => "424555119753073792996994300757",
    //                 "file_name" => "sticker.png",
    //                 "cdn_tag" => "PR3"
    //             ],
    //             "sticker_set_id" => "5e0cb9f4345de9b18b4ba1ae"
    //         ]
    //     ];
    //     return $this->req->making_request('sendMessage', $d, $this->session)['data'];
    // }



    // ======================================================= group methods ===================================================================



    /**
     * create new group
     *
     * @param string $group_guid
     * @param array $members example: ["u0HMRZ...", "u08UBju..."]
     * @return array API result
     */
    public function addGroupMembers(string $group_guid, array $members): array
    {
        return $this->req->making_request('addGroupMembers', [
            'group_guid' => $group_guid,
            'member_guids' => $members
        ], $this->session)['data'];
    }

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
     * upload new group avatar picture
     *
     * @param string $file_path must be picture
     * @param string $group_guid
     * @return array API result
     */
    public function uploadNewGroupAvatar(string $group_guid, string $file_path): array
    {
        list($file_id, $dc_id, $access_hash_rec) = $this->sendFileToAPI($file_path);
        return $this->req->making_request('uploadAvatar', [
            'object_guid' => $group_guid,
            'thumbnail_file_id' => $file_id,
            'main_file_id' => $file_id
        ], $this->session)['data'];
    }

    public function setGroupDefaultAccess(string $group_guid, groupDefaultAccesses $settings = new groupDefaultAccesses): array
    {
        $d = [
            'group_guid' => $group_guid
        ];
        if ($settings->ViewAdmins) {
            $d[] = 'ViewAdmins';
        }
        if ($settings->SendMessages) {
            $d[] = 'SendMessages';
        }
        if ($settings->ViewMembers) {
            $d[] = 'ViewMembers';
        }
        if ($settings->AddMember) {
            $d[] = 'AddMember';
        }
        return $this->req->making_request('setGroupDefaultAccess', $d, $this->session)['data'];
    }

    /**
     * delete group profile picture
     *
     * @param string $group_guid
     * @param string $avatar_id
     * @return array API result
     */
    public function deleteGroupAvatar(string $group_guid, string $avatar_id): array
    {
        return $this->req->making_request('deleteAvatar', [
            'object_guid' => $group_guid,
            'avatar_id' => $avatar_id
        ], $this->session)['data'];
    }

    /**
     * get group join link
     *
     * @param string $group_guid
     * @return array API result
     */
    public function getGroupLink(string $group_guid): array
    {
        return $this->req->making_request('getGroupLink', [
            'object_guid' => $group_guid,
        ], $this->session)['data'];
    }

    /**
     * get new group join link
     *
     * @param string $group_guid
     * @return array API result
     */
    public function getNewGroupLink(string $group_guid): array
    {
        return $this->req->making_request('setGroupLink', [
            'object_guid' => $group_guid,
        ], $this->session)['data'];
    }

    /**
     * get ngroup admins list
     *
     * @param string $group_guid
     * @return array API result
     */
    public function getGroupAdminMembers(string $group_guid): array
    {
        return $this->req->making_request('getGroupAdminMembers', [
            'object_guid' => $group_guid,
        ], $this->session)['data'];
    }

    /**
     * get chat history for new members
     *
     * @param string $group_guid
     * @param HistoryForNewMembers $chat_history_for_new_members Hidden or Visible
     * @return array API result
     */
    public function editGroupHistoryForNewMembers(string $group_guid, HistoryForNewMembers $chat_history_for_new_members): array
    {
        return $this->req->making_request('setGroupLink', [
            'object_guid' => $group_guid,
            'chat_history_for_new_members' => $chat_history_for_new_members->value,
            'updated_parameters' => ['chat_history_for_new_members']
        ], $this->session)['data'];
    }

    /**
     * get chat event messages for members
     *
     * @param string $group_guid
     * @param bool $EventMssages 
     * @return array API result
     */
    public function setGroupEventMessages(string $group_guid, bool $EventMssages): array
    {
        return $this->req->making_request('setGroupLink', [
            'object_guid' => $group_guid,
            'event_messages' => $EventMssages,
            'updated_parameters' => ['event_messages']
        ], $this->session)['data'];
    }

    /**
     * edit group account info
     *
     * @param string $group_guid
     * @param string $title
     * @param string $description bio
     * @return array API result
     */
    public function editGroupProfile(string $group_guid, string $title = '', string $description = ''): array
    {
        $d = [
            'group_guid' => $group_guid
        ];
        if ($title != '') {
            $d['title'] = $title;
        }
        if ($description != '') {
            $d['description'] = $description;
        }
        $d['updated_parameters'] = [
            "title",
            "description"
        ];

        $d = $this->req->making_request('editGroupInfo', $d, $this->session)['data'];

        return $d;
    }

    /**
     * ban group member
     *
     * @param string $group_guid
     * @param string $member_guid 
     * @return array API result
     */
    public function banGroupMember(string $group_guid, string $member_guid): array
    {
        return $this->req->making_request('banGroupMember', [
            'group_guid' => $group_guid,
            'member_guid' => $member_guid,
            'action' => 'Set'
        ], $this->session)['data'];
    }

    /**
     * unban group member
     *
     * @param string $group_guid
     * @param string $member_guid 
     * @return array API result
     */
    public function unBanGroupMember(string $group_guid, string $member_guid): array
    {
        return $this->req->making_request('banGroupMember', [
            'group_guid' => $group_guid,
            'member_guid' => $member_guid,
            'action' => 'Unset'
        ], $this->session)['data'];
    }

    /**
     * set admin or change admin accesses
     *
     * @param string $group_guid
     * @param string $member_guid
     * @param array $access_list
     * @example . setGroupAdmin('g0UBD989...', 'u0YUB78...', [groupAdminAccessList::BanMember, ...])
     * @return array API result
     */
    public function setGroupAdmin(string $group_guid, string $member_guid, array $access_list): array
    {
        $d = [
            'group_guid' => $group_guid,
            'member_guid' => $member_guid,
            'action' => 'SetAdmin'
        ];
        foreach ($access_list as $access) {
            if ($access instanceof groupAdminAccessList) {
                $d['access_list'][] = (string)$access->value;
            }
        }
        return $this->req->making_request('setGroupAdmin', $d, $this->session)['data'];
    }

    /**
     * remove admin
     *
     * @param string $group_guid
     * @param string $member_guid
     * @return array API result
     */
    public function removeGroupAdmin(string $group_guid, string $member_guid): array
    {
        return $this->req->making_request('setGroupAdmin', [
            'group_guid' => $group_guid,
            'member_guid' => $member_guid,
            'action' => 'UnsetAdmin'
        ], $this->session)['data'];
    }

    /**
     * get group admin accesses
     *
     * @param string $group_guid
     * @param string $admin_guid
     * @return array API resilt
     */
    public function getGroupAdminAccessList(string $group_guid, string $admin_guid): array
    {
        return $this->req->making_request('getGroupAdminAccessList', [
            'group_guid' => $group_guid,
            'member_guid' => $admin_guid,
        ], $this->session)['data'];
    }

    /**
     * set group slow mode
     *
     * @param string $group_guid
     * @param integer $time (in seconds). just allowed -> 0, 10, 30, 60, 300, 900, 3600
     * @return array API resilt
     */
    public function setGroupSlowModeTime(string $group_guid, int $time): array
    {
        return $this->req->making_request('editGroupInfo', [
            'group_guid' => $group_guid,
            'slow_mode' => $time,
            'updated_parameters' => ['slow_mode']
        ], $this->session)['data'];
    }

    /**
     * get group banned members
     *
     * @param string $group_guid
     * @return array API resilt
     */
    public function getBannedGroupMembers(string $group_guid): array
    {
        return $this->req->making_request('getBannedGroupMembers', [
            'group_guid' => $group_guid
        ], $this->session)['data'];
    }

    /**
     * set group allowed reactions
     *
     * @param string $group_guid
     * @param setGroupReactions $mode all or diabled or selected
     * @param array $selects if mode is set to Selected
     * @example . setGroupReactions('g0UBD989...', setGroupReactions::Selected, [ReactionsEmoji::❤️, ReactionsEmoji::👍])
     * @return array API result
     */
    public function setGroupReactions(string $group_guid, setGroupReactions $mode, array $selects = []): array
    {
        $d = [
            'group_guid' => $group_guid,
            'chat_reaction_setting' => [
                'reaction_type' => $mode->value
            ],
            'updated_parameters' => ['chat_reaction_setting']
        ];
        if ($mode == setGroupReactions::Selected) {
            foreach ($selects as $reaction) {
                if ($reaction instanceof ReactionsEmoji or $reaction instanceof ReactionsString) {
                    $d['chat_reaction_setting']['selected_reactions'][] = (string)$reaction->value;
                }
            }
        }
        return $this->req->making_request('editGroupInfo', $d, $this->session)['data'];
    }



    // ======================================================= chats methods ===================================================================



    /**
     * set another admin to group owner
     *
     * @param string $group_guid
     * @param string $new_owner_user_guid
     * @return array API result
     */
    public function requestChangeObjectOwner(string $group_guid, string $new_owner_user_guid): array
    {
        return $this->req->making_request('editGroupInfo', [
            'group_guid' => $group_guid,
            'new_owner_user_guid' => $new_owner_user_guid
        ], $this->session)['data'];
    }

    /**
     * accept onwing object
     *
     * @param string $object_guid
     * @return array API resilt
     */
    public function AcceptRequestObjectOwning(string $object_guid): array
    {
        return $this->req->making_request('editGroupInfo', [
            'object_guid' => $object_guid,
            'action' => 'Accept'
        ], $this->session)['data'];
    }

    /**
     * rejecr onwing object
     *
     * @param string $object_guid
     * @return array API resilt
     */
    public function RejectRequestObjectOwning(string $object_guid): array
    {
        return $this->req->making_request('editGroupInfo', [
            'object_guid' => $object_guid,
            'action' => 'Reject'
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



    // ======================================================= contacts methods ===================================================================



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
            'phone' => '+' . Tools::parse_true_phone_number($phone_number),
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
                'phone_number' => Tools::parse_true_phone_number($phone_number)
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



    // ======================================================= get chat info methods ===================================================================



    /**
     * get chat info with guid
     *
     * @param string $guid
     * @return array API result
     */
    public function getChatInfo(string $guid): array
    {
        return $this->req->making_request('get' . Tools::getChatType_byGuid($guid) . 'Info', [
            strtolower(Tools::getChatType_byGuid($guid)) . '_guid' => $guid
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



    // ======================================================= upload and download methods ===================================================================



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
     * @return array [$file_id, $dc_id, $access_hash_rec]
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

        return [$data['id'], $data['dc_id'], $this->req->uploadFile($path, $data['id'], $data['access_hash_send'], $data['upload_url'])['data']['access_hash_rec']];
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



    // ======================================================= MAin class methods ===================================================================



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
    public function getUpadte(): void
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
