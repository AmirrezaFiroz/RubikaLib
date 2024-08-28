<?php

declare(strict_types=1);

namespace RubikaLib;

use RubikaLib\Cryption;
use RubikaLib\Utils\Tools;

/**
 * session object of phone number
 */
final class Session
{
    /**
     * session hash that created with Tools::GeneratePhoneHash()
     *
     * @var string|null
     */
    public ?string $hash;
    public array $data = [];
    private static string $workDirStatic = 'lib/';
    private ?string $k;

    /**
     * construct the object
     *
     * @param integer $phone_number 989123456789
     * @param string $auth
     */
    public function __construct(
        private int $phone_number,
        private string $auth = '',
        private string $workDir = 'lib/'
    ) {
        if ($workDir != 'lib/') self::$workDirStatic = $workDir;

        $this->hash = self::generatePhoneHash($phone_number);
        $this->generate_session();
        $this->k = md5(sha1(Cryption::GenerateRandom_tmp_ession(5)));
    }

    /**
     * Set Auth
     *
     * @param string $auth
     * @return self
     */
    public function setAuth(string $auth): self
    {
        $this->auth = $auth;
        return $this;
    }

    /**
     * re-generate session
     *
     * @return boolean
     */
    public function regenerate_session(): void
    {
        date_default_timezone_set('Asia/Tehran');

        $this->data = array(
            'phone-number' => $this->phone_number,
            'date' => [
                'generated' => $this->data['date'],
                'login' => time()
            ],
            'step' => 'none',
        );
        $this->saveData();
    }

    /**
     * change session datas
     *
     * @param string $which_data which parameter
     * @param string|integer|array $to_what new value
     * @return self
     */
    public function changeData(string $which_data, string|int|array $to_what): self
    {
        $this->data[$which_data] = $to_what;
        $this->saveData();

        return $this;
    }

    /**
     * generate hash for phone number
     *
     * @param integer $phone_number 989123456789
     * @return string phone hash
     */
    public static function generatePhoneHash(int $phone_number): string
    {
        return md5(Tools::GeneratePhoneHash($phone_number));
    }

    /**
     * check is there a session for phone number
     *
     * @param int $phone_number must be like 989123456789 or 9123456789
     * @return boolean true if there is a session or false if session not exists
     */
    public static function is_session(int $phone_number): bool
    {
        return file_exists(self::$workDirStatic . self::generatePhoneHash(strlen((string)$phone_number) == 10 ? 98 . $phone_number : $phone_number) . ".rub");
    }

    /**
     * generate session
     *
     * @return boolean
     */
    public function generate_session(): void
    {
        if (file_exists("{$this->workDir}{$this->hash}.rub")) {
            $this->data = json_decode(Cryption::Decode(file_get_contents("{$this->workDir}{$this->hash}.rub"), $this->hash), true);
            $this->auth = $this->data['tmp_session'] ?? $this->data['auth'];
        } else {
            date_default_timezone_set('Asia/Tehran');

            $this->data = array(
                'phone-number' => $this->phone_number,
                'date' => time(),
                'step' => 'none',
                'tmp_session' => $this->auth
            );
            $this->auth = $this->data['tmp_session'];
            $this->saveData();
        }
    }

    /**
     * save data into session file
     *
     * @return void
     */
    private function saveData(): void
    {
        file_put_contents("{$this->workDir}{$this->hash}.rub", cryption::Encode(json_encode($this->data), $this->hash));
    }

    public function getPartOfSessionKey(): array
    {
        return [Cryption::Encode($this->auth, $this->k), $this->k];
    }

    /**
     * terminate session
     *
     * @return void
     */
    public function terminate(): void
    {
        unlink("{$this->workDir}{$this->hash}.rub");
    }
}
