<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase, RubikaLib\Main;
use RubikaLib\enums\ChatTypes;

class FoldersTest extends TestCase
{
    const int PHONE = 9123456789;

    public function testDeleteAllGroups(): void
    {
        $app = new Main(self::PHONE);
        $folders = $app->Folders->getFolders()['folders'];

        foreach ($folders as $folder) {
            $app->Folders->DeleteFolder($folder['folder_id']);
        }

        $this->assertEmpty($app->Folders->getFolders()['folders']);
    }

    public function testSetUpBotsFolder(): void
    {
        $app = new Main(self::PHONE);
        $folders = $app->Folders->SuggestedFolders->SetUpBotsFolder();

        $this->assertIsArray($folders);
    }

    public function testSetUpGroupsFolder(): void
    {
        $app = new Main(self::PHONE);
        $folders = $app->Folders->SuggestedFolders->SetUpGroupsFolder();

        $this->assertIsArray($folders);
    }

    public function testSetUpChannelsFolder(): void
    {
        $app = new Main(self::PHONE);
        $folders = $app->Folders->SuggestedFolders->SetUpChannelsFolder();

        $this->assertIsArray($folders);
    }

    public function testSetUpPersonalFolder(): void
    {
        $app = new Main(self::PHONE);
        $folders = $app->Folders->SuggestedFolders->SetUpPersonalFolder();

        $this->assertIsArray($folders);
    }

    public function testSetUpUnReadFolder(): void
    {
        $app = new Main(self::PHONE);
        $folders = $app->Folders->SuggestedFolders->SetUpUnReadFolder();

        $this->assertIsArray($folders);
    }

    public function testManuelAddFolder1(): void
    {
        $app = new Main(self::PHONE);
        $folders = $app->Folders->AddFolder('manuel folder1', guids: [
            // TODO
            $app->getMySelf()['user_guid'], // saved messages
            'c0CNO9G00048b5b09ebc63be0181686a' // our channel
        ]);

        $this->assertNotEmpty($folders);
    }

    public function testManuelAddFolder2(): void
    {
        $app = new Main(self::PHONE);
        $folders = $app->Folders->AddFolder(
            'manuel folder 2',
            include_chat_types: [ChatTypes::Channel],
            exclude_object_guids: ['c0CNO9G00048b5b09ebc63be0181686a']
        );

        $this->assertNotEmpty($folders);
    }

    public function testGetFoldersList(): void
    {
        $app = new Main(self::PHONE);
        $folders = $app->Folders->getFolders();
        file_put_contents('Folders.json', json_encode($folders));

        $this->assertIsArray($folders);
    }
}
