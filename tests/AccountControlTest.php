<?php

use PHPUnit\Framework\TestCase;
use RubikaLib\Main;

class AccountControlTest extends TestCase
{
    const int PHONE = 9123456789;

    public function testGetAccountInfo(): void
    {
        $app = new Main(self::PHONE);

        $this->assertNotEmpty($app->Account->getMySelf());
    }

    public function testGetSessionsList(): void
    {
        $app = new Main(self::PHONE);

        $this->assertNotEmpty($app->Account->getMySelf());
    }

    public function testTerminalSession(): void
    {
        $app = new Main(self::PHONE);

        $this->assertEmpty($app->Account->TerminateSession('xztx1pMZiyjWxUNQ5UarY...')); // its just a sample
    }

    public function testChangeUsername(): void
    {
        $app = new Main(self::PHONE);

        $this->assertSame($app->Account->ChangeUsername('AA_firoz5050')['status'], 'OK'); // its just a sample
    }

    public function testEditProfile(): void
    {
        $app = new Main(self::PHONE);

        $this->assertNotEmpty($app->Account->EditProfile('Amirreza', 'Firoz'));
    }

    public function testUploadNewProfileAvatar(): void
    {
        $app = new Main(self::PHONE);

        $this->assertNotEmpty($app->Account->UploadNewProfileAvatar('https://amirrezafiroz.github.io/logo.png', true));
    }

    public function testLogout(): void
    {
        $this->markTestSkipped();
        $app = new Main(self::PHONE);

        $this->assertEmpty($app->Account->logout());
    }
}
