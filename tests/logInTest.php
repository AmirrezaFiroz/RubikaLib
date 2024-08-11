<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use RubikaLib\{
    Main,
    Session
};

final class logInTest extends TestCase
{
    public function testLogin(): void
    {
        $app = new Main(9123456789, 'app-name');

        $this->assertIsArray($app->getMySelf());
    }

    public function testCheckIsSessionCreated(): void
    {
        $this->assertTrue(Session::is_session(989123456789));
    }

    public function testLogOut(): void
    {
        $this->assertIsArray((new Main(9123456789))->logout());
    }
}
