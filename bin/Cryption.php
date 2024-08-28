<?php

declare(strict_types=1);

namespace RubikaLib;

use phpseclib3\Crypt\{
    PublicKeyLoader,
    RSA
};
use phpseclib3\Crypt\RSA\PrivateKey;

/**
 * cryption object
 */
final class Cryption
{
    public function __construct(
        public string $auth,
        public string $private_key = ''
    ) {
        $this->auth = $this->CreateSecretPhraseFromAuth($this->auth);
        $this->auth = $this->utf8ToBytes($this->auth);
    }

    /**
     * Generage 'sign' From 'data_enc'
     *
     * @param string $data_enc
     * @throws Failure invalid private key set
     * @return string digital sign
     */
    public function GenerateSign(string $data_enc): string
    {
        $privateKey = PublicKeyLoader::load($this->private_key);

        if ($privateKey instanceof PrivateKey) {
            $privateKey = $privateKey->withPadding(RSA::SIGNATURE_PKCS1);
        } else {
            throw new Failure("Invalid private key format");
        }

        $signature = $privateKey->sign($data_enc);

        return base64_encode($signature);
    }

    /**
     * Decoed Encoded Data
     *
     * @param string $data_enc encoded data
     * @param string $key key for decoding data
     * @throws Failure throws error on failer
     * @return string decoded data (may be json string)
     */
    public static function Decode(string $data_enc, string $key): string
    {
        if (!isset($key)) return $data_enc;
        $i = self::createSecretphrase($key);
        $n = self::utf8Dec($i);
        $s = $data_enc;
        $s = base64_decode($data_enc);
        if ($s === false) {
            throw new Failure("an error in data decodation !");
        }
        $iv = str_repeat("\0", 16);
        $r = openssl_decrypt($s, 'AES-256-CBC', $n, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);
        if ($r === false) {
            throw new Failure("an error in data decodation !");
        }
        $padding = ord($r[strlen($r) - 1]);
        if ($padding > 0 && $padding <= 16) {
            $r = substr($r, 0, -$padding);
        }
        return $r;
    }

    /**
     * Decoed Encoded Data
     *
     * @param string $data_enc encoded data
     * @throws Failure throws error on failer
     * @return string decoded data (may be json string)
     */
    public function dec(string $data_enc): string
    {
        $data_enc = base64_decode($data_enc);
        if ($data_enc === false) {
            throw new Failure("an error in data decodation !");
        }
        $iv = str_repeat("\0", 16);
        $r = openssl_decrypt($data_enc, 'AES-256-CBC', $this->auth, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);
        if ($r === false) {
            throw new Failure("an error in data decodation !");
        }
        $padding = ord($r[strlen($r) - 1]);
        if ($padding > 0 && $padding <= 16) {
            $r = substr($r, 0, -$padding);
        }
        return $r;
    }

    /**
     * Generate A Secret Phrase From Key
     *
     * @param string $auth key for generating phrase
     * @return string secret phrase
     */
    private function CreateSecretPhraseFromAuth(string $auth): string
    {
        $t = substr($auth, 0, 8);
        $i = substr($auth, 8, 8);
        $n = substr($auth, 16, 8) . $t . substr($auth, 24, 8) . $i;

        for ($s = 0; $s < strlen($n); $s++) {
            $char = $n[$s];
            if ($char >= '0' && $char <= '9') {
                $t = chr((ord($char) - ord('0') + 5) % 10 + ord('0'));
                $n = $this->replaceCharAt($n, $s, $t);
            } else {
                $t = chr((ord($char) - ord('a') + 9) % 26 + ord('a'));
                $n = $this->replaceCharAt($n, $s, $t);
            }
        }
        return $n;
    }

    /**
     * Generate A Secret Phrase From Key
     *
     * @param string $key key for generating phrase
     * @return string secret phrase
     */
    private static function createSecretphrase(string $key): string
    {
        $t = substr($key, 0, 8);
        $i = substr($key, 8, 8);
        $n = substr($key, 16, 8) . $t . substr($key, 24, 8) . $i;

        for ($s = 0; $s < strlen($n); $s++) {
            $char = $n[$s];
            if ($char >= '0' && $char <= '9') {
                $t = chr((ord($char) - ord('0') + 5) % 10 + ord('0'));
                $n = self::replaceChar($n, $s, $t);
            } else {
                $t = chr((ord($char) - ord('a') + 9) % 26 + ord('a'));
                $n = self::replaceChar($n, $s, $t);
            }
        }
        return $n;
    }

    private function replaceCharAt($e, $t, $i): string
    {
        return substr($e, 0, $t) . $i . substr($e, $t + strlen($i));
    }

    private static function replaceChar($e, $t, $i): string
    {
        return substr($e, 0, $t) . $i . substr($e, $t + strlen($i));
    }

    private function utf8ToBytes(string $string): string
    {
        return $string;
    }

    private static function utf8Dec(string $string): string
    {
        return $string;
    }

    /**
     * Generate Random 'tmp_session'
     *
     * @param integer $e length
     * @return string
     */
    public static function GenerateRandom_tmp_ession(int $e = 32): string
    {
        $r = 'abcdefghijklmnopqrstuvwxyz';
        $t = '';
        $i = strlen($r);
        for ($n = 0; $n < $e; $n++) {
            $t .= $r[mt_rand(0, $i - 1)];
        }
        return $t;
    }

    /**
     * Encode Json Data
     *
     * @param string $data data for encoding
     * @param string $key key for decoding data
     * @throws Failure throws error on failer
     * @return string encoded data
     */
    public static function Encode(string $data, string $key): string
    {
        if (!$key) return $data;
        $i = self::createSecretphrase($key);
        $n = self::utf8Dec($i);
        $iv = str_repeat("\0", 16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $n, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            throw new Failure("an error in data encodation !");
        }
        return base64_encode($encrypted);
    }

    /**
     * Encode Json Data
     *
     * @param string $data data for encoding
     * @throws Failure throws error on failer
     * @return string encoded data
     */
    public function enc(string $data): string
    {
        $iv = str_repeat("\0", 16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->auth, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            throw new Failure("an error in data encodation !");
        }
        return base64_encode($encrypted);
    }

    public static function re_auth(string $auth): string
    {
        $n = '';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $uppercase = strtoupper($lowercase);
        $digits = '0123456789';

        for ($i = 0; $i < strlen($auth); $i++) {
            $s = $auth[$i];
            if (strpos($lowercase, $s) !== false) {
                $n .= chr(((32 - (ord($s) - 97)) % 26) + 97);
            } elseif (strpos($uppercase, $s) !== false) {
                $n .= chr(((29 - (ord($s) - 65)) % 26) + 65);
            } elseif (strpos($digits, $s) !== false) {
                $n .= chr(((13 - (ord($s) - 48)) % 10) + 48);
            } else {
                $n .= $s;
            }
        }

        return $n;
    }

    /**
     * Generate Public And Private RSA-key
     *
     * @return array array($public_Key, $private_Key)
     */
    public static function Generate_RSAkey(): array
    {
        $key = RSA::createKey(1024);

        $publicKey = $key->getPublicKey();
        $publicKeyString = base64_encode($publicKey->toString('PKCS8'));
        $publicKeyModified = self::re_auth($publicKeyString);

        $privateKeyString = $key->toString('PKCS8');

        return array($publicKeyModified, $privateKeyString);
    }

    /**
     * Decode RSA Encoded Auth With Private-key
     *
     * @param string $private_Key
     * @param string $data_enc
     * @throws Failure on error
     * @return string decoded result
     */
    public static function Decrypt_RSAEncodedAuth(string $private_Key, string $data_enc): string
    {
        $private_Key = openssl_pkey_get_private($private_Key);
        if (!$private_Key) {
            throw new Failure("error on private key loading");
        }

        $data = base64_decode($data_enc);
        $decrypted = '';

        $result = openssl_private_decrypt($data, $decrypted, $private_Key, OPENSSL_PKCS1_OAEP_PADDING);
        if (!$result) {
            throw new Failure("error on decryption RSA data");
        }

        return $decrypted;
    }
}
