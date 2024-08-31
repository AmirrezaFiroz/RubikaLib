<?php

use PHPUnit\Framework\TestCase;
use RubikaLib\enums\AppType;
use RubikaLib\interfaces\MainSettings;
use RubikaLib\Main;
use RubikaLib\Session;

class LoginTEst extends TestCase
{
    const int PHONE = 9123456789;

    public function testLogin()
    {
        new Main(self::PHONE);
        // TODO
        $this->assertTrue(Session::is_session(self::PHONE));
    }

    public function testLoginWithAppName()
    {
        new Main(self::PHONE, 'NewApp');
        $this->assertTrue(Session::is_session(self::PHONE));
    }

    public function testLoginWithCLI()
    {
        new Main('NewApp');
        $this->assertTrue(Session::is_session(self::PHONE));
    }

    public function testLoginToShad()
    {
        new Main(
            self::PHONE,
            settings: (new MainSettings)->setAppType(AppType::Shad)
        );
        // TODO
        $this->assertTrue(Session::is_session(self::PHONE));
    }
}
