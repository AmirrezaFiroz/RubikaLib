<?php

declare(strict_types=1);

namespace RubikaLib;

use phpseclib3\Crypt\Common\AsymmetricKey;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use phpseclib3\Crypt\RSA\PrivateKey;

/**
 * cryption object
 */
final class Cryption
{
    public static bool $colors = false;
    public ?AsymmetricKey $digital_key;

    public function __construct(
        public string $auth,
        public string $private_key = ''
    ) {
        $this->auth = $this->createSecretPassphrase($this->auth);
        $this->auth = $this->utf8ToBytes($this->auth);

        if ($private_key != '') {
            $this->digital_key = PublicKeyLoader::load($private_key);
        }
    }

    /**
     * make sign from data_enc
     *
     * @param string $data_enc
     * @throws Logger invalid private key set
     * @return string digital sign
     */
    public function generateSign(string $data_enc): string
    {
        $privateKey = PublicKeyLoader::load($this->private_key);

        if ($privateKey instanceof PrivateKey) {
            $privateKey = $privateKey->withPadding(RSA::SIGNATURE_PKCS1);
        } else {
            throw new Logger("Invalid private key format");
        }

        $signature = $privateKey->sign($data_enc);

        return base64_encode($signature);
    }

    /**
     * decoed encoded data
     *
     * @param string $data_enc encoded data
     * @param string $key key for decoding data
     * @throws Logger throws error on failer
     * @return string decoded data (may be json string)
     */
    public static function decode(string $data_enc, string $key): string
    {
        if (!isset($key)) return $data_enc;
        $i = self::createSecretphrase($key);
        $n = self::utf8Dec($i);
        $s = $data_enc;
        $s = base64_decode($data_enc);
        if ($s === false) {
            throw new Logger("an error in data decodation !");
        }
        $iv = str_repeat("\0", 16);
        $r = openssl_decrypt($s, 'AES-256-CBC', $n, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);
        if ($r === false) {
            throw new Logger("an error in data decodation !");
        }
        $padding = ord($r[strlen($r) - 1]);
        if ($padding > 0 && $padding <= 16) {
            $r = substr($r, 0, -$padding);
        }
        return $r;
    }

    /**
     * decoed encoded data
     *
     * @param string $data_enc encoded data
     * @throws Logger throws error on failer
     * @return string decoded data (may be json string)
     */
    public function dec(string $data_enc): string
    {
        $data_enc = base64_decode($data_enc);
        if ($data_enc === false) {
            throw new Logger("an error in data decodation !");
        }
        $iv = str_repeat("\0", 16);
        $r = openssl_decrypt($data_enc, 'AES-256-CBC', $this->auth, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);
        if ($r === false) {
            throw new Logger("an error in data decodation !");
        }
        $padding = ord($r[strlen($r) - 1]);
        if ($padding > 0 && $padding <= 16) {
            $r = substr($r, 0, -$padding);
        }
        return $r;
    }

    /**
     * generate a secret phrase from key
     *
     * @param string $key key for generating phrase
     * @return string secret phrase
     */
    public function createSecretPassphrase(string $key): string
    {
        $t = substr($key, 0, 8);
        $i = substr($key, 8, 8);
        $n = substr($key, 16, 8) . $t . substr($key, 24, 8) . $i;

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
     * generate a secret phrase from key
     *
     * @param string $key key for generating phrase
     * @return string secret phrase
     */
    public static function createSecretphrase(string $key): string
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

    /**
     * replace a character in string
     *
     * @return string
     */
    private function replaceCharAt($e, $t, $i): string
    {
        return substr($e, 0, $t) . $i . substr($e, $t + strlen($i));
    }

    /**
     * replace a character in string
     *
     * @return string
     */
    public static function replaceChar($e, $t, $i): string
    {
        return substr($e, 0, $t) . $i . substr($e, $t + strlen($i));
    }

    /**
     * decode utf8 string
     *
     * @param string $string
     * @return string
     */
    private function utf8ToBytes(string $string): string
    {
        return $string;  // PHP handles UTF-8 strings natively
    }

    /**
     * decode utf8 string
     *
     * @param string $string
     * @return string
     */
    public static function utf8Dec(string $string): string
    {
        return $string;  // PHP handles UTF-8 strings natively
    }

    /**
     * generate random auth
     *
     * @param integer $e length
     * @return string
     */
    public static function azRand(int $e = 32): string
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
     * encode array data
     *
     * @param string $data data for encoding
     * @param string $key key for decoding data
     * @throws Logger throws error on failer
     * @return string encoded data
     */
    public static function encode(string $data, string $key): string
    {
        if (!$key) return $data;
        $i = self::createSecretphrase($key);
        $n = self::utf8Dec($i);
        $iv = str_repeat("\0", 16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $n, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            throw new Logger("an error in data encodation !");
        }
        return base64_encode($encrypted);
    }

    /**
     * encode array data
     *
     * @param string $data data for encoding
     * @throws Logger throws error on failer
     * @return string encoded data
     */
    public function enc(string $data): string
    {
        $iv = str_repeat("\0", 16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->auth, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            throw new Logger("an error in data encodation !");
        }
        return base64_encode($encrypted);
    }

    /**
     * some proccesses on auth to convert it to RSA public key
     *
     * @param string $auth
     * @return string
     */
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
     * reverse of re_auth function if it needen
     *
     * @param string $re_auth
     * @return string
     */
    public static function reverse_re_auth(string $re_auth): string
    {
        $n = '';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $uppercase = strtoupper($lowercase);
        $digits = '0123456789';
        for ($i = 0; $i < strlen($re_auth); $i++) {
            $s = $re_auth[$i];
            if (strpos($lowercase, $s) !== false) {
                $n .= chr((32 - ((ord($s) - 97 + 26) % 26)) % 26 + 97);
            } elseif (strpos($uppercase, $s) !== false) {
                $n .= chr((29 - ((ord($s) - 65 + 26) % 26)) % 26 + 65);
            } elseif (strpos($digits, $s) !== false) {
                $n .= chr((13 - ((ord($s) - 48 + 10) % 10)) % 10 + 48);
            } else {
                $n .= $s;
            }
        }
        return $n;
    }

    /**
     * generating public and private RSA key
     *
     * @return array array of keys: array($public_Key, $private_Key)
     */
    public static function RSA_KeyGenerate(): array
    {
        $key = RSA::createKey(1024);

        $publicKey = $key->getPublicKey();
        $publicKeyString = base64_encode($publicKey->toString('PKCS8'));
        $publicKeyModified = self::re_auth($publicKeyString);

        $privateKeyString = $key->toString('PKCS8');

        return array($publicKeyModified, $privateKeyString);
    }

    /**
     * decode RSA data with private key
     *
     * @param string $private_Key
     * @param string $data_enc
     * @throws Logger on error
     * @return string decoded result
     */
    public static function decrypt_RSA_by_key(string $private_Key, string $data_enc): string
    {
        $private_Key = openssl_pkey_get_private($private_Key);
        if (!$private_Key) {
            throw new Logger("error on private key loading");
        }
        $data = base64_decode($data_enc);
        $decrypted = '';
        $result = openssl_private_decrypt($data, $decrypted, $private_Key, OPENSSL_PKCS1_OAEP_PADDING);
        if (!$result) {
            throw new Logger("error on decryption RSA data");
        }
        return $decrypted;
    }
}
