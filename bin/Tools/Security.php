<?php

declare(strict_types=1);

namespace RubikaLib\Tools;

use ParagonIE\Sodium\Compat;

/**
 * class for session files cryption
 */
final class Security
{
    /**
     * Encrypt File
     *
     * @param string $data
     * @param string $path
     * @param string $key
     * @return string the $key
     */
    public static function encryptFile(string $data, string $path, string $key = ''): string
    {
        $key = $key != '' ? substr($key, 0, 32) : random_bytes(32);
        $nonce = random_bytes(24);
        $ciphertext = Compat::crypto_secretbox($data, $nonce, $key);

        file_put_contents($path, $nonce . $ciphertext);
        return $key;
    }

    /**
     * Decrypt File
     *
     * @param string $filePath
     * @param string $key
     * @return string decoded file data
     */
    public static function decryptFile(string $filePath, string $key): string
    {
        $fileContent = file_get_contents($filePath);
        $nonce = mb_substr($fileContent, 0, 24, '8bit');
        $ciphertext = mb_substr($fileContent, 24, null, '8bit');

        return Compat::crypto_secretbox_open($ciphertext, $nonce, $key);
    }
}
