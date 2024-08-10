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
        $app = new Main(9365199010, 'app-name');

        $this->assertIsArray($app->getMySelf());
    }

    public function testCheckIsSessionCreated(): void
    {
        $this->assertTrue(Session::is_session(989365199010));
    }
}
