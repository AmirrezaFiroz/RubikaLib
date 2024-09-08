<?php

declare(strict_types=1);

namespace RubikaLib;

use Generator;
use getID3;
use RubikaLib\Enums\{
    ChatActivities,
    DeleteType,
    PollType,
    ReactionsEmoji,
    ReactionsString
};
use RubikaLib\Helpers\Optimal;
use RubikaLib\Interfaces\Gif;
use RubikaLib\Interfaces\MainSettings;
use RubikaLib\Utils\Tools;

/**
 * messages object
 */
final class Messages
{
    public function __construct(
        private Session $session,
        private Requests $req,
        private MainSettings $settings
    ) {}

    /**
     * get stickers list
     *
     * @return array API result
     */
    public function getMyStickerSets(): array
    {
        return $this->req->SendRequest('getMyStickerSets', array(), $this->session)['data'];
    }

    /**
     * get sticker set data by sticker set id
     *
     * @param string $sticker_set_ids
     * @return array API result
     */
    public function getStickerSetByID(string $sticker_set_id): array
    {
        return $this->req->SendRequest('getStickerSetByID', [
            'sticker_set_id' => $sticker_set_id
        ], $this->session)['data'];
    }

    // TODO
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

    /**
     * send text to someone or somewhere
     *
     * @param string $guid object_guid
     * @param string $text message (can be with markworn metadatas)
     * @param int $reply_to_message_id if you have to reply
     * @return array API result
     */
    public function SendMessage(string $guid, string $text, int $reply_to_message_id = 0): array
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
        if ($m != false && $m[0] != []) {
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
        if ($m != false && $m[0] != []) {
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
    public function ForwardMessages(string $from_object_guid, array $message_ids, string $to_object_guid): array
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
     * @param DeleteType $type local or global
     * @return array API result
     */
    public function DeleteMessages(string $object_guid, array $message_ids, DeleteType $type = DeleteType::Local): array
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
     * @param ChatActivities $activity
     * @return array API result
     */
    public function SendChatActivity(string $guid, ChatActivities $activity): array
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
    public function SendPhoto(string $guid, string $path, bool $isLink = false, string $caption = '', string $thumbnail = '', string $reply_to_message_id = ''): array
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
            if ($m != false && $m[0] != []) {
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
    public function SendDocument(string $guid, string $path, bool $isLink = false, string $caption = '', string $reply_to_message_id = ''): array
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
            if ($m != false && $m[0] != []) {
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
    public function SendVideo(string $guid, string $path, bool $isLink = false, string $caption = '', string $thumbnail = '', string $reply_to_message_id = ''): array
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
            if ($m != false && $m[0] != []) {
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
     * get account gifs list
     *
     * @return Generator Gifs as Generator function(in Rubikalib\Interfaces\Gif types)
     */
    public function getMyGifSet(): Generator
    {
        $data = $this->req->SendRequest('getMyGifSet', array(), $this->session)['data'];

        foreach ($data['gifs'] as $gif) {
            yield new Gif((string)$gif['file_id'], $gif['dc_id'], $gif['access_hash_rec'], $gif['file_name'], $gif['width'], $gif['height'], $gif['time'], $gif['size'], $gif['thumb_inline']);
        }
    }

    /**
     * send gif to guid
     *
     * @param string $guid
     * @param string $path file path or url
     * @param Gif $gif if you want to send an uploaded gif
     * @param boolean $isLink is $path a URL or not
     * @param string $caption
     * @param string $thumbnail base64 encoded thumbnail picture
     * @return array API result
     */
    public function sendGif(string $guid, Gif|null $gif = null, string $path = '', bool $isLink = false, string $caption = '', string $thumbnail = '', string $reply_to_message_id = ''): array
    {
        if ($path != '' && is_null($gif)) {
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
        } elseif ($path == '' && is_null($gif)) {
            throw new Failure('send what?');
        } elseif ($path == '' && !is_null($gif)) {
            $d = [
                'object_guid' => $guid,
                'rnd' => (string)mt_rand(10000000, 999999999),
                'file_inline' => [
                    'file_id' => $gif->fileId,
                    'dc_id' => $gif->dcID,
                    'mime' => 'mp4',
                    'type' => 'Gif',
                    'file_name' => $gif->file_name,
                    'size' => $gif->size,
                    'thumb_inline' => $gif->thumb_inline != '' ? $gif->thumb_inline : base64_encode(file_get_contents(__DIR__ . '/video.png')),
                    'width' => $gif->width,
                    'height' => $gif->height,
                    'time' => $gif->time,
                    'access_hash_rec' => $gif->access_hash_rec
                ]
            ];
        } elseif ($path != '' && !is_null($gif)) {
            throw new Failure('send which?');
        }

        if ($caption != '') {
            $m = Tools::ProccessMetaDatas($caption);
            if ($m != false && $m[0] != []) {
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
            if ($m != false && $m[0] != []) {
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
        PollType $type,
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
    public function SendContact(string $guid, string $first_name, int $phone_number, string $contact_guid = '', string $last_name = '', string $reply_to_message_id = '0'): array
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

    /**
     * download a file
     *
     * @param string $access_hash_rec
     * @param string $file_id
     * @param string $to_path the path to file tath will be writen
     * @param integer $DC
     * @throws Failure on downloading error (like file not found)
     * @return boolean false if file not found on server or true if file has saved
     */
    public function DownloadFile(string $access_hash_rec, string $file_id, int $DC, string $to_path): bool
    {
        if ($this->settings->Optimal) {
            if (file_exists($to_path . '.lock')) {
                $lines = explode("\n", file_get_contents($to_path . '.lock'));
                $last_line = $lines[count($lines) - 1];
                $last_line = $last_line == '' ? $lines[count($lines) - 2] : $last_line;
                $ex = explode(' --- ', $last_line);

                if ($ex[1] == $access_hash_rec && $ex[2] == $file_id && (int)$ex[3] == $DC) {
                    $f = fopen($to_path, 'a');
                    $f2 = fopen($to_path . '.lock', 'a');
                    foreach ($this->req->DownloadFileFromAPI($access_hash_rec, $file_id, $DC, (int)$ex[4]) as $data) {
                        if ($data === false) {
                            fclose($f);
                            throw new Failure('an error occured.');
                            return false;
                        }
                        fwrite($f2, "writing --- $access_hash_rec --- $file_id --- $DC --- {$data[1]} (countinue downloading).");
                        fwrite($f, $data[0]);
                        fwrite($f2, " --- done.\n");
                    }
                    fclose($f);
                    fclose($f2);
                    unlink($to_path . '.lock');
                } else {
                    $f = fopen($to_path, 'a');
                    unlink($to_path . '.lock');
                    $f2 = fopen($to_path . '.lock', 'a');
                    foreach ($this->req->DownloadFileFromAPI($access_hash_rec, $file_id, $DC) as $data) {
                        if ($data === false) {
                            fclose($f);
                            throw new Failure('an error occured.');
                            return false;
                        }
                        fwrite($f2, "writing --- $access_hash_rec --- $file_id --- $DC --- {$data[1]}");
                        fwrite($f, $data[0]);
                        fwrite($f2, "--- done.\n");
                    }
                    fclose($f);
                    fclose($f2);
                    unlink($to_path . '.lock');
                }

                return true;
            }

            $f = fopen($to_path, 'a');
            $f2 = fopen($to_path . '.lock', 'a');
            foreach ($this->req->DownloadFileFromAPI($access_hash_rec, $file_id, $DC) as $data) {
                if ($data === false) {
                    fclose($f);
                    throw new Failure('an error occured.');
                    return false;
                }
                fwrite($f2, "writing --- $access_hash_rec --- $file_id --- $DC --- {$data[1]}");
                fwrite($f, $data[0]);
                fwrite($f2, "--- done.\n");
            }
            fclose($f);
            fclose($f2);
            unlink($to_path . '.lock');

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
}
