<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use RubikaLib\Main;

class MessagesTest extends TestCase
{
    const int PHONE = 9123456789;

    public function testGetMyStickerSets(): void
    {
        $app = new Main(self::PHONE);

        $data = $app->Messages->getMyStickerSets();

        $this->assertNotEmpty($data);
    }

    public function testGetStickerSetByID(): void
    {
        $app = new Main(self::PHONE);

        $data = $app->Messages->getStickerSetByID('5e0cb9f4345de9b18b4ba1ae');

        $this->assertNotEmpty($data);
    }

    public function testSendMessage(): void
    {
        $app = new Main(self::PHONE);

        $this->assertIsArray($app->Messages->SendMessage($app->Account->getMySelf()['user_guid'], '**text to saved messages**'));
    }

    public function testSendPhoto(): void
    {
        $app = new Main(self::PHONE);

        $this->assertIsArray($app->Messages->SendPhoto(
            $app->Account->getMySelf()['user_guid'],
            'lib/video.png',
            caption: '__image__'
        ));
    }

    public function testSendDocument(): void
    {
        $app = new Main(self::PHONE);

        $this->assertIsArray($app->Messages->SendDocument(
            $app->Account->getMySelf()['user_guid'],
            'lib/video.png',
            caption: '`file`'
        ));
    }

    public function testSendVideo(): void
    {
        $app = new Main(self::PHONE);

        $this->assertIsArray($app->Messages->SendVideo(
            $app->Account->getMySelf()['user_guid'],
            'lib/video.mp4',
            caption: '*video*'
        ));
    }

    public function testGetMyGifSetAndSendAllThatGif(): void
    {
        $app = new Main(self::PHONE);

        $data = $app->Messages->getMyGifSet();
        foreach ($data as $gif) {
            $app->Messages->sendGif($app->Account->getMySelf()['user_guid'], $gif);
        }

        $this->assertInstanceOf('Generator', $data);
    }
}
