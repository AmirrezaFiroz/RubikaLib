<?php

declare(strict_types=1);

namespace RubikaLib;

use getID3;
use Ratchet\Client\WebSocket;
use RubikaLib\Utils\Tools;
use React\EventLoop\Loop;
use RubikaLib\Tools\Optimal;
use RubikaLib\enums\{
    chatActivities,
    ChatTypes,
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
    GroupDefaultAccesses,
    MainSettings,
    Runner
};

/**
 * class for working with API
 */
final class Main
{
    private ?Runner $Runner;
    private ?int $phone_number;
    private ?Requests $req;
    private ?Session $session;
    public static $VERSION = '2.0.0';
    private ?Cryption $crypto;
    public ?Folders $Folders;

    /**
     * @param integer $phone_number 989123456789 or 9123456789
     * @param string $app_name it just need in login
     * @param MainSettings $settings by default = (new MainSettings)
     */
    public function __construct(
        int $phone_number = 0,
        string $app_name = '',
        private MainSettings $settings = new MainSettings
    ) {
        while (!in_array(strlen((string)$phone_number), [10, 12])) {
            $phone_number = (int)readline("Enter Phone Number: ");
        }

        $this->phone_number = Tools::ReplaceTruePhoneNumber($phone_number);

        if (!Session::is_session($this->phone_number)) {
            $this->req = new Requests($settings->UserAgent, $settings->tmp_session, mainSettings: $settings);
            $this->session = new Session($this->phone_number, $settings->tmp_session, $settings->Base);
            $this->session->changeData('useragent', $this->req->useragent);
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
                ->changeData('code_digits_count', $send_code['code_digits_count']);

            list($signIn, $private_key) = [[], ''];
            while (true) {
                $code = (int)readline("enter code ({$send_code['code_digits_count']}-digits) : ");

                if (strlen((string)$code) == $send_code['code_digits_count']) {
                    list($signIn, $private_key) = $this->signIn($send_code['phone_code_hash'], $code);
                    break;
                }
            }

            $auth = Cryption::Decrypt_RSAEncodedAuth($private_key, $signIn['auth']);
            unset($signIn['user']['online_time']);
            $this->session->ReGenerateSession();
            $this->session
                ->changeData('auth', $auth)
                ->changeData('user', $signIn['user'])
                ->changeData('private_key', $private_key)
                ->changeData('useragent', $this->req->useragent)
                ->setAuth($auth);
            $this->req = new Requests(auth: $auth, private_key: $private_key, useragent: $this->req->useragent, mainSettings: $settings);

            $this->registerDevice($app_name);
        } else {
            $this->session = new Session($this->phone_number, workDir: $settings->Base);
            $p = $this->session->getPartOfSessionKey();
            $this->req = new Requests(
                auth: Cryption::Decode($p[0], $p[1]),
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

                    $auth = Cryption::Decrypt_RSAEncodedAuth($private_key, $signIn['auth']);
                    unset($signIn['online_time']);
                    $this->session->ReGenerateSession();
                    $this->session
                        ->changeData('auth', $auth)
                        ->changeData('user', $signIn['user'])
                        ->changeData('private_key', $private_key)
                        ->changeData('useragent', $this->req->useragent)
                        ->setAuth($auth);
                    $this->req = new Requests(auth: $auth, private_key: $private_key, useragent: $this->req->useragent, mainSettings: $settings);

                    $this->registerDevice($app_name);
                    break;
            }
        }

        $p = $this->req->getPartOfSessionKey();
        $this->crypto = new Cryption(Cryption::Decode($p[0], $p[1]), $this->session->data['private_key']);
        $this->session->changeData('user', $this->getChatInfo($this->getMySelf()['user_guid'])['user']);

        $this->Folders = new Folders($this->req, $this->session);
    }

    /**
     * send login code to phone number
     *
     * @param string $pass_key if account have password
     * @throws Failure INVALID_INPUT
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

        $r = $this->req->SendRequest('sendCode', $d, $this->session, true)['data'];

        if (!in_array($r['status'], ['OK', 'SendPassKey'])) {
            throw new Failure('there is an error in result: ' . json_encode(['status' => 'OK', 'status_det' => $r['status']]));
        } else {
            return $r;
        }
    }

    /**
     * sign in to API
     *
     * @param string $phone_code_hash
     * @param integer $code
     * @throws Failure CodeIsExpired, CodeIsInvalid, CodeIsUsed
     * @return array [API result, private_key]
     */
    private function signIn(string $phone_code_hash, int $code): array
    {
        list($publicKey, $privateKey) = cryption::Generate_RSAkey();

        $r = $this->req->SendRequest('signIn', [
            "phone_number" => (string)$this->phone_number,
            "phone_code_hash" => $phone_code_hash,
            "phone_code" => $code,
            "public_key" => $publicKey
        ], $this->session, true)['data'];

        if ($r['status'] != 'OK') {
            throw new Failure('there is an error in result: ' . json_encode(['status' => 'OK', 'status_det' => $r['status']]));
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
            'device_hash' => Tools::GenerateDeviceHash($this->req->useragent)
        ];
        $r = $this->req->SendRequest('registerDevice', $d, $this->session);

        if ($r['status'] != 'OK') {
            throw new Failure('there is an error in result: ' . json_encode(['status' => 'OK', 'status_det' => $r['status']]));
        } else {
            return $r;
        }
    }



    // ======================================================= account methods ===================================================================



    /**
     * Terminate This Session
     *
     * @return array API result
     */
    public function logout(): array
    {
        $this->session->terminate();
        return $this->req->SendRequest('logout', array(), $this->session)['data'];
    }

    /**
     * get Account Sessions List
     *
     * @return array API result
     */
    public function getMySessions(): array
    {
        return $this->req->SendRequest('getMySessions', array(), $this->session)['data'];
    }

    /**
     * Terminate a Session
     *
     * @param string $session_key session key
     * @return array API result
     */
    public function TerminateSession(string $session_key): array
    {
        return $this->req->SendRequest('terminateSession', [
            'session_key' => $session_key
        ], $this->session)['data'];
    }

    /**
     * get Session Data
     *
     * @return array user data of session
     */
    public function getMySelf(): array
    {
        return $this->session->data['user'];
    }

    /**
     * Set Account Username
     *
     * @param string $newUserName example: @rubika_lib or rubika_lib
     * @return array API result
     */
    public function ChangeUsername(string $newUserName): array
    {
        $d = $this->req->SendRequest('updateUsername', [
            'username' => str_replace('@', '', $newUserName)
        ], $this->session)['data'];

        if ($d['status'] == 'OK') {
            $this->session->changeData('user', $d['user']);
        }

        return $d;
    }

    /**
     * Edit Account Info
     *
     * @param string $first_name new first name (if want to change)
     * @param string $last_name new last name (if want to change)
     * @param string $bio new bio (if want to change)
     * @return array API result
     */
    public function EditProfile(string $first_name = '', string $last_name = '', string $bio = ''): array
    {
        $d = [
            'updated_parameters' => []
        ];
        if ($first_name != '') {
            $d['first_name'] = mb_substr($first_name, 0, 32);
            $d['updated_parameters'][] = 'first_name';
        }
        if ($last_name != '') {
            $d['last_name'] = mb_substr($last_name, 0, 32);
            $d['updated_parameters'][] = 'last_name';
        }
        if ($bio != '') {
            $d['bio'] = $bio;
            $d['updated_parameters'][] = 'bio';
        }

        $d = $this->req->SendRequest('updateProfile', $d, $this->session)['data'];

        if (isset($d['chat_update'])) {
            $this->session->changeData('user', $d['user']);
        }

        return $d;
    }

    /**
     * Request Delete Account
     *
     * @return array API result
     */
    public function RequestDeleteAccount(): array
    {
        return $this->req->SendRequest('requestDeleteAccount', [], $this->session)['data'];
    }

    /**
     * Upload New Profile Picture
     *
     * @param string $file_path must be a picture (png/jpg/jpeg)
     * @return array API result
     */
    public function UploadNewProfileAvatar(string $file_path): array
    {
        list($file_id, $dc_id, $access_hash_rec) = $this->sendFileToAPI($file_path);

        return $this->req->SendRequest('uploadAvatar', [
            'thumbnail_file_id' => $file_id,
            'main_file_id' => $file_id
        ], $this->session)['data'];
    }

    /**
     * Delete Profile Picture
     *
     * @param string $avatar_id
     * @return void API result
     */
    public function DeleteMyAvatar(string $avatar_id): array
    {
        return $this->req->SendRequest('deleteAvatar', [
            'object_guid' => $this->getMySelf()['user_guid'],
            'avatar_id' => $avatar_id
        ], $this->session)['data'];
    }

    /**
     * get account gifs list
     *
     * @return array API result
     */
    public function getMyGifSet(): array
    {
        return $this->req->SendRequest('getMyGifSet', [], $this->session)['data'];
    }

    /**
     * check login password
     *
     * @param string $password
     * @return array API result
     */
    public function checkTwoStepPasscode(string $password): array
    {
        return $this->req->SendRequest('checkTwoStepPasscode', [
            'password' => $password
        ], $this->session)['data'];
    }

    // TODO
    /**
     * change account verify email
     *
     * @param string $password
     * @param string $email
     * @throws Failure if not correct email address
     * @return array API result
     */
    private function requestRecoveryEmail(string $password, string $email): array
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Failure('not valid email address');

        return $this->req->SendRequest('requestRecoveryEmail', [
            'password' => $password
        ], $this->session)['data'];
    }

    /**
     * verify email code
     *
     * @param string $password
     * @param integer $code
     * @return array API result
     */
    private function verifyRecoveryEmail(string $password, int $code): array
    {
        return $this->req->SendRequest('verifyRecoveryEmail', [
            'password' => $password,
            'code' => (string)$code
        ], $this->session)['data'];
    }

    /**
     * chnage account password
     *
     * @param string $current_password
     * @param string $new_password
     * @param string $hint
     * @return array API result
     */
    public function changePassword(string $current_password, string $new_password, string $hint = 'password hint'): array
    {
        return $this->req->SendRequest('changePassword', [
            'password' => $current_password,
            'new_password' => $new_password,
            'hint' => $hint
        ], $this->session)['data'];
    }

    /**
     * turn off account passwrod
     *
     * @return array API result
     */
    public function turnOffTwoStep(): array
    {
        return $this->req->SendRequest('turnOffTwoStep', [], $this->session)['data'];
    }



    // ======================================================= stickers methods ===================================================================



    /**
     * get stickers list
     *
     * @return array API result
     */
    public function getMyStickerSets(): array
    {
        return $this->req->SendRequest('getMyStickerSets', [], $this->session)['data'];
    }

    /**
     * get sticker set data by sticker set id
     *
     * @param string $sticker_set_ids
     * @return array API result
     */
    public function getStickersBySetIDs(string $sticker_set_ids): array
    {
        return $this->req->SendRequest('getStickersBySetIDs', [
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
    //     return $this->req->SendRequest('sendMessage', $d, $this->session)['data'];
    // }



    // ====================================================== chating methods ==================================================================



    /**
     * send text to someone or somewhere
     *
     * @param string $guid object_guid
     * @param string $text message
     * @param int $reply_to_message_id if you have to reply
     * @return array API result
     */
    public function sendMessage(string $guid, string $text, int $reply_to_message_id = 0): array
    {
        $m = Tools::ProccessMetaDatas($text);
        $d = [
            'object_guid' => $guid,
            'rnd' => (string)mt_rand(10000000, 999999999),
            'text' => $m == false ? $text : $m[1]
        ];
        if ($reply_to_message_id != 0) {
            $d['reply_to_message_id'] = (string)$reply_to_message_id;
        }
        if ($m != false) {
            $d['metadata']['meta_data_parts'] = $m[0];
        }
        return $this->req->SendRequest('sendMessage', $d, $this->session)['data'];
    }

    /**
     * Edit Message Text
     *
     * @param string $guid object_guid
     * @param string $NewText message
     * @param int $message_id
     * @return array API result
     */
    public function EditMessage(string $guid, string $NewText, int $message_id): array
    {
        $m = Tools::ProccessMetaDatas($NewText);
        $d = [
            'object_guid' => $guid,
            'text' => $m == false ? $NewText : $m[1],
            'message_id' => (string)$message_id
        ];
        if ($m != false) {
            $d['metadata']['meta_data_parts'] = $m[0];
        }
        return $this->req->SendRequest('EditMessage', $d, $this->session)['data'];
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
        return $this->req->SendRequest('forwardMessages', [
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
        return $this->req->SendRequest('deleteMessages', [
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
        return $this->req->SendRequest('sendChatActivity', [
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
        return $this->req->SendRequest('seenChats', [
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
        return $this->req->SendRequest('seenChats', [
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
                file_put_contents("{$this->settings->Base}$fn", file_get_contents($path));
            } else {
                $f = fopen("{$this->settings->Base}$fn", 'a');
                foreach (Optimal::getFile($path, $this->settings->UserAgent) as $part) {
                    fwrite($f, $part);
                }
                fclose($f);
            }

            $path = "{$this->settings->Base}$fn";
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
                'thumb_inline' => $thumbnail != '' ? $thumbnail : base64_encode(Tools::CreateThumbnail($path, $width)),
                'width' => $width,
                'height' => $height,
                'access_hash_rec' => $access_hash_rec
            ]
        ];
        if ($caption != '') {
            $m = Tools::ProccessMetaDatas($caption);
            if ($m != false) {
                $d['metadata']['meta_data_parts'] = $m[0];
            }
            $d['text'] = ($m == false) ? $caption : $m[1];
        }
        if ($reply_to_message_id != '') {
            $d['reply_to_message_id'] = $reply_to_message_id;
        }

        return $this->req->SendRequest('sendMessage', $d, $this->session)['data'];
    }

    /**
     * Send Document To Chat
     *
     * @param string $guid
     * @param string $path file path or link
     * @param boolean $isLink is $path a link or not
     * @param string $caption
     * @param string $reply_to_message_id
     * @return array API result
     */
    public function sendDocument(string $guid, string $path, bool $isLink = false, string $caption = '', string $reply_to_message_id = ''): array
    {
        $fn = basename($path);
        if ($isLink) {
            if (!$this->settings->Optimal) {
                file_put_contents("{$this->settings->Base}$fn", file_get_contents($path));
            } else {
                $f = fopen("{$this->settings->Base}$fn", 'a');
                foreach (Optimal::getFile($path, $this->settings->UserAgent) as $part) {
                    fwrite($f, $part);
                }
                fclose($f);
            }

            $path = "{$this->settings->Base}$fn";
        }

        list($file_id, $dc_id, $access_hash_rec) = $this->sendFileToAPI($path);

        $d = [
            'object_guid' => $guid,
            'rnd' => (string)mt_rand(10000000, 999999999),
            'file_inline' => [
                'dc_id' => $dc_id,
                'file_id' => $file_id,
                'type' => 'File',
                'file_name' => $fn,
                'size' => filesize($path),
                'mime' => explode('.', $fn)[count(explode('.', $fn)) - 1],
                'access_hash_rec' => $access_hash_rec
            ]
        ];
        if ($caption != '') {
            $m = Tools::ProccessMetaDatas($caption);
            if ($m != false) {
                $d['metadata']['meta_data_parts'] = $m[0];
            }
            $d['text'] = ($m == false) ? $caption : $m[1];
        }
        if ($reply_to_message_id != '') {
            $d['reply_to_message_id'] = $reply_to_message_id;
        }

        return $this->req->SendRequest('sendMessage', $d, $this->session)['data'];
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
                file_put_contents("{$this->settings->Base}$fn", file_get_contents($path));
            } else {
                $f = fopen("{$this->settings->Base}$fn", 'a');
                foreach (Optimal::getFile($path, $this->settings->UserAgent) as $part) {
                    fwrite($f, $part);
                }
                fclose($f);
            }

            $path = "{$this->settings->Base}$fn";
        }

        $getID3 = new getID3;
        $file = $getID3->analyze($path);
        if (isset($file['error'])) {
            throw new Failure("Error: " . implode("\n", $file['error']));
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
            $m = Tools::ProccessMetaDatas($caption);
            if ($m != false) {
                $d['metadata']['meta_data_parts'] = $m[0];
            }
            $d['text'] = ($m == false) ? $caption : $m[1];
        }
        if ($reply_to_message_id != '') {
            $d['reply_to_message_id'] = $reply_to_message_id;
        }

        return $this->req->SendRequest('sendMessage', $d, $this->session)['data'];
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
                file_put_contents("{$this->settings->Base}$fn", file_get_contents($path));
            } else {
                $f = fopen("{$this->settings->Base}$fn", 'a');
                foreach (Optimal::getFile($path, $this->settings->UserAgent) as $part) {
                    fwrite($f, $part);
                }
                fclose($f);
            }

            $path = "{$this->settings->Base}$fn";
        }

        $getID3 = new getID3;
        $file = $getID3->analyze($path);
        if (isset($file['error'])) {
            throw new Failure("Error: " . implode("\n", $file['error']));
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
            $m = Tools::ProccessMetaDatas($caption);
            if ($m != false) {
                $d['metadata']['meta_data_parts'] = $m[0];
            }
            $d['text'] = ($m == false) ? $caption : $m[1];
        }
        if ($reply_to_message_id != '') {
            $d['reply_to_message_id'] = $reply_to_message_id;
        }

        return $this->req->SendRequest('sendMessage', $d, $this->session)['data'];
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
                file_put_contents("{$this->settings->Base}$fn", file_get_contents($path));
            } else {
                $f = fopen("{$this->settings->Base}$fn", 'a');
                foreach (Optimal::getFile($path, $this->settings->UserAgent) as $part) {
                    fwrite($f, $part);
                }
                fclose($f);
            }

            $path = "{$this->settings->Base}$fn";
        }

        $getID3 = new getID3;
        $file = $getID3->analyze($path);
        if (isset($file['error'])) {
            throw new Failure("Error: " . implode("\n", $file['error']));
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
            $m = Tools::ProccessMetaDatas($caption);
            if ($m != false) {
                $d['metadata']['meta_data_parts'] = $m[0];
            }
            $d['text'] = ($m == false) ? $caption : $m[1];
        }
        if ($reply_to_message_id != '') {
            $d['reply_to_message_id'] = $reply_to_message_id;
        }

        return $this->req->SendRequest('sendMessage', $d, $this->session)['data'];
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
        return $this->req->SendRequest('actionOnMessageReaction', [
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
        return $this->req->SendRequest('actionOnMessageReaction', [
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
        return $this->req->SendRequest('createPoll', $d, $this->session)['data'];
    }

    /**
     * get poll statuc
     *
     * @param string $poll_id
     * @return array API result
     */
    public function getPollStatus(string $poll_id): array
    {
        return $this->req->SendRequest('createPoll', [
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
        return $this->req->SendRequest('getPollOptionVoters', [
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
        return $this->req->SendRequest('sendmessage', [
            'object_guid' => $guid,
            'rnd' => (string)mt_rand(10000000, 999999999),
            'location' => [
                'latitude' => $latitude,
                'longitude' => $longitude
            ]
        ], $this->session)['data'];
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
        return $this->req->SendRequest('votePoll', [
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
        return $this->req->SendRequest($method, filter_var($enterKey, FILTER_VALIDATE_URL) ? [
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
        $chatType = strtolower((string)Tools::ChatTypeByGuid($guid)->value);
        $d = [
            "{$chatType}_guid" => $guid
        ];
        if ($chatType == 'Channel') {
            $d['action'] = 'Leave';
        }
        return $this->req->SendRequest($chatType == 'group' ? 'leaveGroup' : 'joinChannelAction', $d, $this->session)['data'];
    }

    /**
     * delete group for all users
     *
     * @param string $group_guid
     * @return array API result
     */
    public function deleteGroup(string $group_guid): array
    {
        return $this->req->SendRequest('removeGroup', [
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
        return $this->req->SendRequest('addGroup', [
            'title' => $title,
            'member_guids' => $members
        ], $this->session)['data'];
    }




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
        return $this->req->SendRequest('addGroupMembers', [
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
        return $this->req->SendRequest('getGroupOnlineCount', [
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
        return $this->req->SendRequest('getGroupAllMembers', $d, $this->session)['data'];
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
        return $this->req->SendRequest('uploadAvatar', [
            'object_guid' => $group_guid,
            'thumbnail_file_id' => $file_id,
            'main_file_id' => $file_id
        ], $this->session)['data'];
    }

    public function setGroupDefaultAccess(string $group_guid, GroupDefaultAccesses $settings = new GroupDefaultAccesses): array
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
        return $this->req->SendRequest('setGroupDefaultAccess', $d, $this->session)['data'];
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
        return $this->req->SendRequest('deleteAvatar', [
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
        return $this->req->SendRequest('getGroupLink', [
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
        return $this->req->SendRequest('setGroupLink', [
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
        return $this->req->SendRequest('getGroupAdminMembers', [
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
        return $this->req->SendRequest('setGroupLink', [
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
        return $this->req->SendRequest('setGroupLink', [
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

        $d = $this->req->SendRequest('editGroupInfo', $d, $this->session)['data'];

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
        return $this->req->SendRequest('banGroupMember', [
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
        return $this->req->SendRequest('banGroupMember', [
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
        return $this->req->SendRequest('setGroupAdmin', $d, $this->session)['data'];
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
        return $this->req->SendRequest('setGroupAdmin', [
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
        return $this->req->SendRequest('getGroupAdminAccessList', [
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
        return $this->req->SendRequest('editGroupInfo', [
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
        return $this->req->SendRequest('getBannedGroupMembers', [
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
        return $this->req->SendRequest('editGroupInfo', $d, $this->session)['data'];
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
        return $this->req->SendRequest('editGroupInfo', [
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
        return $this->req->SendRequest('editGroupInfo', [
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
        return $this->req->SendRequest('editGroupInfo', [
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
        return $this->req->SendRequest('getChats', [
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
        return $this->req->SendRequest('getChatsUpdates', [
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
        return $this->req->SendRequest('getMessagesInterval', [
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
    //     return $this->req->SendRequest('getMessages', [
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
            return $this->req->SendRequest('getContacts', [
                'start_id' => $start_id
            ], $this->session)['data'];
        }
        return $this->req->SendRequest('getContacts', [], $this->session)['data'];
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
        return $this->req->SendRequest('addAddressBook', [
            'phone' => '+' . Tools::ReplaceTruePhoneNumber($phone_number),
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
        return $this->req->SendRequest('deleteContact', [
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
                'phone_number' => Tools::ReplaceTruePhoneNumber($phone_number)
            ]
        ];
        if ($contact_guid != '') {
            $d['message_contact']['user_guid'] = $contact_guid;
        }
        if ($reply_to_message_id != '0') {
            $d['reply_to_message_id'] = $reply_to_message_id;
        }
        return $this->req->SendRequest('sendMessage', $d, $this->session)['data'];
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
        return $this->req->SendRequest('get' . Tools::ChatTypeByGuid($guid)->value . 'Info', [
            strtolower(Tools::ChatTypeByGuid($guid)->value) . '_guid' => $guid
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
        return $this->req->SendRequest('getObjectInfoByUsername', [
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
        return $this->req->SendRequest('getAvatars', [
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
    public function DownloadFile(string $access_hash_rec, string $file_id, string $to_path, int $DC): bool
    {
        if ($this->settings->Optimal) {
            $f = fopen($to_path, 'a');
            foreach ($this->req->DownloadFileFromAPI($access_hash_rec, $file_id, $DC) as $data) {
                if ($data === false) {
                    fclose($f);
                    return false;
                }
                fwrite($f, $data);
            }
            fclose($f);
            return true;
        } else {
            $r = $this->req->DownloadFileFromAPI($access_hash_rec, $file_id, $DC);
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
     * @return array [$file_id, $dc_id, $access_hash_rec]
     */
    private function sendFileToAPI(string $path): array
    {
        $fn = basename($path);
        $ex = explode('.', $fn);
        $data = $this->RequestSendFile($fn, filesize($path), $ex[count($ex) - 1]);

        return [$data['id'], $data['dc_id'], $this->req->SendFileToAPI($path, $data['id'], $data['access_hash_send'], $data['upload_url'])['data']['access_hash_rec']];
    }

    /**
     * it will use to upload file
     *
     * @param string $file_name
     * @param integer $size
     * @param string $mime
     * @return array API result
     */
    private function RequestSendFile(string $file_name, int $size, string $mime): array
    {
        return $this->req->SendRequest('requestSendFile', [
            'file_name' => $file_name,
            'size' => $size,
            'mime' => $mime
        ], $this->session)['data'];
    }



    // ======================================================= Main class methods ===================================================================



    /**
     * set Runner class for getting updates
     *
     * @param Runner $class Runner Object
     * @return self
     */
    public function proccess(Runner $class): self
    {
        $this->Runner = $class;
        $class->onStart($this->getMySelf());

        return $this;
    }

    /**
     * connect to socket and get updates
     *
     * @return void
     */
    public function RunAndLoop(): void
    {
        if (is_null($this->Runner)) throw new Failure("App Runner Class Isn't Set");

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
                            $this->Runner->onAction(match ($update['type']) {
                                'Typing' => chatActivities::Typing,
                                'Recording' => chatActivities::Recording,
                                'Uploading' => chatActivities::Uploading
                            }, $update['object_guid'], $update['user_activity_guid'], $this);
                        } else {
                            $this->Runner->onMessage($update, $this);
                        }
                    }
                });

                $p = $this->req->getPartOfSessionKey();
                $conn->send(json_encode([
                    'api_version' => '6',
                    'auth' => Cryption::Decode($p[0], $p[1]),
                    'data' => '',
                    'method' => 'handShake'
                ]));

                $loop->addPeriodicTimer(30, function () use ($conn) {
                    $conn->send('{}');
                });
            },
            function ($e) {
                throw new Failure("error on socket connection: {$e->getMessage()}");
            }
        );

        $loop->run();
    }
}
