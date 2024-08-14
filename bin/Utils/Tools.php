<?php

declare(strict_types=1);

namespace RubikaLib\Utils;

use RubikaLib\Logger;

/**
 * library tool functions
 */
final class Tools
{
    /**
     * make sure about phone number
     *
     * @param int $phoneNumber
     * @throws Logger throws an error when phone number is incorrect
     * @return int true phone number in this format: 989123456789
     */
    public static function parse_true_phone_number(int $phoneNumber): int
    {
        $phoneNumber = preg_replace('/[^\d+]/', '', (string)$phoneNumber);

        $length = strlen($phoneNumber);
        if ($length < 10 || $length > 13) {
            throw new Logger("the is an error with phone number format: " . $phoneNumber);
        }

        $patterns = [
            // '/^0[9]\d{9}$/',    // 09123456789
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

        throw new Logger("the is an error with phone number format: " . $phoneNumber);
    }

    /**
     * this will use to hash phone number sessions
     *
     * @param integer $phoneNumber 989123456789
     * @return string
     */
    public static function generate_phone_hash(int $phoneNumber): string
    {
        $array = [
            '0' => 'q',
            '1' => 'w',
            '2' => 'e',
            '3' => 'r',
            '4' => 't',
            '5' => 'y',
            '6' => 'u',
            '7' => 'i',
            '8' => 'o',
            '9' => 'p'
        ];
        $res = '';
        foreach (str_split((string)$phoneNumber) as $char) {
            $res .= $array[$char];
        }
        return $res;
    }

    /**
     * get hash of useragent for device registering
     *
     * @param string $userAgent
     * @return string
     */
    public static function generate_device_hash(string $userAgent): string
    {
        $userAgent = preg_replace('/\D+/', '', $userAgent);
        return $userAgent;
    }

    /**
     * get os of useragent for device login
     *
     * @param string $userAgent
     * @return string
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
     * find chat type by looking at guid
     *
     * @param string $guid
     * @return string|false 'Group', 'Channel', 'User', 'Service' or false
     */
    public static function getChatType_byGuid(string $guid): string|false
    {
        if (str_starts_with($guid, 'g0')) {
            return 'Group';
        } elseif (str_starts_with($guid, 'u0')) {
            return 'User';
        } elseif (str_starts_with($guid, 'c0')) {
            return 'Channel';
        } elseif (str_starts_with($guid, 's0')) {
            return 'Service';
        } else {
            return false;
        }
    }

    /**
     * get metadatas from text
     *
     * @param string $text text with metadatas
     * @return array|false array of metadatas or false if no metadata found
     */
    public static function loadMetaData(string $text) {}

    /**
     * craate photo thumbnail
     *
     * @param string $file_path
     * @param integer $thumb_width
     * @return string thumbnail data
     */
    public static function createThumbnail(string $file_path, int $thumb_width): string
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
     * get image data
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
