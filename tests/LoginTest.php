<?php

use PHPUnit\Framework\TestCase;
use RubikaLib\Enums\AppType;
use RubikaLib\Interfaces\MainSettings;
use RubikaLib\Main;
use RubikaLib\Session;

class LoginTEst extends TestCase
{
    const int PHONE = 9123456789;

    public function testLogin()
    {
        new Main(self::PHONE);
        $this->assertTrue(Session::is_session(self::PHONE, AppType::Rubika));
    }

    public function testLoginWithAppName()
    {
        new Main(self::PHONE, 'NewApp');
        $this->assertTrue(Session::is_session(self::PHONE, AppType::Rubika));
    }

    public function testLoginWithCLI()
    {
        new Main('NewApp');
        $this->assertTrue(Session::is_session(self::PHONE, AppType::Rubika));
    }

    public function testLoginToShad()
    {
        new Main(
            self::PHONE,
            settings: (new MainSettings)->setAppType(AppType::Shad)
        );
        $this->assertTrue(Session::is_session(self::PHONE, AppType::Rubika));
    }
}
