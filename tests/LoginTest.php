<?php

use PHPUnit\Framework\TestCase;
use RubikaLib\Main;
use RubikaLib\Session;

class LoginTEst extends TestCase
{
    const int PHONE = 9123456789;

    public function testLogin()
    {
        new Main(self::PHONE);
        $this->assertTrue(Session::is_session(self::PHONE));
    }
}
