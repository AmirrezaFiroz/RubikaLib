<?php

use PHPUnit\Framework\TestCase;
use RubikaLib\Main;
use RubikaLib\Session;

class LoginTEst extends TestCase
{
    public function testLogin()
    {
        new Main(9123456789);
        $this->assertTrue(Session::is_session(9123456789));
    }
}
