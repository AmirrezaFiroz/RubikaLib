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
    public static function phoneNumberParse(int $phoneNumber): int
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
     * @param integer $int
     * @return string
     */
    public static function phoneToString(int $int): string
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

        foreach (str_split((string)$int) as $char) {
            $res .= $array[$char];
        }

        return $res;
    }

    /**
     * get hash of useragent for device login
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
    public static function getOS(string $userAgent)
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
    public static function ChatType_guid(string $guid): string|false
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
}
