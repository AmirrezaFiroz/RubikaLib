<?php

declare(strict_types=1);

namespace RubikaLib;

date_default_timezone_set('Asia/Tehran');

use RubikaLib\Cryption;
use RubikaLib\Utils\Tools;

/**
 * session object of phone number
 */
final class Session
{
    public ?string $hash;
    public array $data = [];
    public array $user = [];

    /**
     * construct the object
     *
     * @param integer $phone_number
     * @param string $auth
     */
    public function __construct(
        private int $phone_number,
        public string $auth = ''
    ) {
        $this->hash = self::generatePhoneHash($phone_number);

        $this->generate_session();
    }

    /**
     * re-generate session
     *
     * @return boolean
     */
    public function regenerate_session(): void
    {
        $this->data = array(
            'phone-number' => $this->phone_number,
            'date' => [
                'generated' => $this->data['date'],
                'login' => date('Y/M/d H:m')
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
     * @param integer $phone_number
     * @return string phone hash
     */
    public static function generatePhoneHash(int $phone_number): string
    {
        return md5(Tools::phoneToString($phone_number));
    }

    /**
     * check is there a session for phone number
     *
     * @param int $phone_number
     * @return boolean true if there is a session or false if session not exists
     */
    public static function is_session(int $phone_number): bool
    {
        return file_exists("lib/" . self::generatePhoneHash($phone_number) . ".rub");
    }

    /**
     * generate session
     *
     * @return boolean
     */
    public function generate_session(): void
    {
        if (file_exists("lib/{$this->hash}.rub")) {
            $this->data = json_decode(Cryption::decode(file_get_contents("lib/{$this->hash}.rub"), $this->hash), true);
            $this->auth = $this->data['tmp_session'] ?? $this->data['auth'];
        } else {
            $this->data = array(
                'phone-number' => $this->phone_number,
                'date' => date('Y/M/d H:m'),
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
        file_put_contents("lib/{$this->hash}.rub", cryption::encode(json_encode($this->data), $this->hash));
    }

    /**
     * terminate session
     *
     * @return void
     */
    public function terminate(): void
    {
        unlink("lib/{$this->hash}.rub");
    }
}
