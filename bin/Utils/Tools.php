<?php

declare(strict_types=1);

namespace RubikaLib\Utils;

use RubikaLib\Enums\ChatTypes, RubikaLib\Failure;

/**
 * library tool functions
 */
final class Tools
{
    /**
     * @param int $phoneNumber
     * @throws Failure throws an error when phone number is incorrect
     * @return int true phone number in this format: 989123456789
     */
    public static function ReplaceTruePhoneNumber(int $phoneNumber): int
    {
        $phoneNumber = preg_replace('/[^\d+]/', '', (string)$phoneNumber);

        $length = strlen($phoneNumber);
        if ($length < 10 || $length > 13) {
            throw new Failure("there is an error with phone number format: " . $phoneNumber);
        }

        $patterns = [
            // '/^0[9]\d{9}$/',    // 09123456789 =====> not needed for now
            '/^\+98[9]\d{9}$/', // +989123456789
            '/^98[9]\d{9}$/',   // 989123456789
            '/^[9]\d{9}$/'      // 9123456789
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $phoneNumber)) {
                $phoneNumber = preg_replace('/^0|^98|\+98/', '', $phoneNumber);
                return (int)('98' . $phoneNumber);
            }
        }

        throw new Failure("there is an error with phone number format: " . $phoneNumber);
    }

    /**
     * Will Used For Sessions
     * 
     * @param integer $phoneNumber 989123456789
     * @return string
     */
    public static function GeneratePhoneHash(int $phoneNumber): string
    {
        $array = [
            '0' => 'q',
            '1' => 'w',
            '2' => 'e',
            '3' => 'z',
            '4' => 't',
            '5' => 'y',
            '6' => 'd',
            '7' => 'g',
            '8' => 'a',
            '9' => 'l'
        ];
        $res = '';
        foreach (str_split((string)$phoneNumber) as $char) {
            $res .= $array[$char];
        }
        return $res;
    }

    /**
     * Get Hash From UserAgent For Device Registering
     *
     * @param string $userAgent
     * @return string device hash
     */
    public static function GenerateDeviceHash(string $userAgent): string
    {
        $userAgent = preg_replace('/\D+/', '', $userAgent);
        return $userAgent;
    }

    /**
     * Get OS From UserAgent For Device Registering
     *
     * @param string $userAgent
     * @return string OS name
     */
    public static function getOSbyUserAgent(string $userAgent)
    {
        $os = "Unknown";

        if (strpos($userAgent, 'Windows NT 10.0') !== false) {
            $os = "Windows 10";
        } elseif (strpos($userAgent, 'Windows NT 6.2') !== false) {
            $os = "Windows 8";
        } elseif (strpos($userAgent, 'Windows NT 6.1') !== false) {
            $os = "Windows 7";
        } elseif (strpos($userAgent, 'Windows NT 6.0') !== false) {
            $os = "Windows Vista";
        } elseif (strpos($userAgent, 'Windows NT 5.1') !== false) {
            $os = "Windows XP";
        } elseif (strpos($userAgent, 'Windows NT 5.0') !== false) {
            $os = "Windows 2000";
        } elseif (strpos($userAgent, 'Mac') !== false) {
            $os = "Mac/iOS";
        } elseif (strpos($userAgent, 'X11') !== false) {
            $os = "UNIX";
        } elseif (strpos($userAgent, 'Linux') !== false) {
            $os = "Linux";
        }

        return $os;
    }

    /**
     * Find Chat Type By Looking At Guid
     *
     * @param string $guid
     * @return ChatTypes|false 'Group', 'Channel', 'User', 'Service' or false on no-one
     */
    public static function ChatTypeByGuid(string $guid): ChatTypes|false
    {
        if (str_starts_with($guid, 'g0')) {
            return ChatTypes::Group;
        } elseif (str_starts_with($guid, 'u0')) {
            return ChatTypes::User;
        } elseif (str_starts_with($guid, 'c0')) {
            return ChatTypes::Channel;
        } elseif (str_starts_with($guid, 's0')) {
            return ChatTypes::Service;
        } else {
            return false;
        }
    }

    /**
     * Get All Metadatas From String
     *
     * @param string $text
     * @return array|false return [$metadata, $cleanText]; or return false; if text is empty
     */
    public static function ProccessMetaDatas(string $text): array|false
    {
        if (empty($text)) {
            return false;
        }

        $metadata = [];
        $cleanText = '';
        $patterns = [
            "Mono" => '/\`([^`]+)\`/',
            "Bold" => '/\*\*([^*]+)\*\*/',
            "Italic" => '/\_\_([^_]+)\_\_/',
            // "Strike" => '/\~\~([^~]+)\~\~/',
            // "Underline" => '/\_\_([^-]+)\_\_/',
            // "Mention" => '/\@\@([^@]+)\@\@/',
            "Spoiler" => '/\|\|([^#]+)\|\|/',
        ];
        $offset = 0;

        $pattern = '/(\`[^`]+\`|\*\*[^*]+\*\*|\_\_[^_]+\_\_|\~\~[^~]+\~\~|\-\-[^-]+\-\-|\@\@[^@]+\@\@|\#\#[^#]+\#\#)/';
        while (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $full_match = $match[0][0];
                $start = mb_strlen(substr($text, 0, (int)$match[0][1]), 'UTF-8');

                $cleanText .= mb_substr($text, $offset, $start - $offset, 'UTF-8');

                foreach ($patterns as $patternName => $pattern) {
                    if (preg_match($pattern, $full_match, $inner_match)) {
                        $length = mb_strlen($inner_match[1], 'UTF-8');
                        $metadata[] = [
                            "type" => $patternName,
                            "from_index" => mb_strlen($cleanText, 'UTF-8'),
                            "length" => $length,
                        ];

                        $cleanText .= $inner_match[1];
                        break;
                    }
                }

                $offset = $start + mb_strlen($full_match, 'UTF-8');
            }

            $text = mb_substr($text, $offset, null, 'UTF-8');
            $offset = 0;
        }

        $cleanText .= mb_substr($text, $offset, null, 'UTF-8');

        foreach ($metadata as &$item) {
            if ($item["type"] === "Mention") {
                preg_match('/\@\(([^)]+)\)/', $text, $mentionMatch);

                if ($mentionMatch) {
                    $mentionType = self::ChatTypeByGuid($mentionMatch[1])->value;
                    $mentionType = !$mentionType ? 'Link' : $mentionType;

                    if ($mentionType === "Link") {
                        $item = [
                            "from_index" => $item["from_index"],
                            "length" => $item["length"],
                            "link" => [
                                "hyperlink_data" => [
                                    "url" => $mentionMatch[1]
                                ],
                                "type" => "hyperlink",
                            ],
                            "type" => $mentionType,
                        ];
                    } else {
                        $item = [
                            "type" => "MentionText",
                            "from_index" => $item["from_index"],
                            "length" => $item["length"],
                            "mention_text_object_guid" => $mentionMatch[1],
                            "mention_text_object_type" => $mentionType
                        ];
                    }
                }
            }
        }

        var_dump([$metadata, $cleanText]);
        return [$metadata, $cleanText];
    }

    /**
     * Craate Photo Thumbnail
     *
     * @param string $file_path
     * @param integer $thumb_width
     * @return string thumbnail data
     */
    public static function CreateThumbnail(string $file_path, int $thumb_width): string
    {
        $image_info = getimagesize($file_path);
        if ($image_info === false) {
            return "Not a valid image file.";
        }

        $width = $image_info[0];
        $height = $image_info[1];
        $thumb_height = floor($height * ($thumb_width / $width));

        $image_type = $image_info[2];
        switch ($image_type) {
            case IMAGETYPE_JPEG:
                $source_image = imagecreatefromjpeg($file_path);
                break;
            case IMAGETYPE_PNG:
                $source_image = imagecreatefrompng($file_path);
                break;
            case IMAGETYPE_GIF:
                $source_image = imagecreatefromgif($file_path);
                break;
            default:
                return "Unsupported image type.";
        }

        $thumb_image = imagecreatetruecolor($thumb_width, (int)$thumb_height);

        imagecopyresampled($thumb_image, $source_image, 0, 0, 0, 0, $thumb_width, (int)$thumb_height, $width, $height);

        ob_start();
        switch ($image_type) {
            case IMAGETYPE_JPEG:
                imagejpeg($thumb_image);
                break;
            case IMAGETYPE_PNG:
                imagepng($thumb_image);
                break;
            case IMAGETYPE_GIF:
                imagegif($thumb_image);
                break;
        }
        $thumb_content = ob_get_contents();
        ob_end_clean();

        imagedestroy($source_image);
        imagedestroy($thumb_image);

        return $thumb_content;
    }

    /**
     * Get Image Datas
     *
     * @param string $file_path
     * @return array return [$Width, $Height, $Mime]
     */
    public static function getImageDetails(string $file_path): array
    {
        $image_info = getimagesize($file_path);
        if ($image_info === false) {
            return "Not a valid image file.";
        }

        return [
            $image_info[0],
            $image_info[1],
            $image_info['mime']
        ];
    }
}
