<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use RubikaLib\Main;

class FoldersTest extends TestCase
{
    const int PHONE = 9365199010; // TODO

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

    public function testGetFoldersList(): void
    {
        $app = new Main(self::PHONE);
        $folders = $app->Folders->getFolders();
        file_put_contents('Folders.json', json_encode($folders));

        $this->assertIsArray($folders);
    }
}
