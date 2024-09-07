<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use RubikaLib\Main;

class ContactsTest extends TestCase
{
    const int PHONE = 9123456789;

    public function testGetAllContacts(): void
    {
        $app = new Main(self::PHONE);

        $c = $app->Contacts->getContacts();
        $contacts = [];
        foreach ($c['users'] as $con) {
            $contacts[] = $con['user_guid'];
        }
        while ($c['has_continue']) {
            $c = $app->Contacts->getContacts($c['next_start_id']);
            foreach ($c['users'] as $con) {
                $contacts[] = $con['user_guid'];
            }
        }

        $this->assertNotEmpty($c);
    }

    public function testAddContact(): void
    {
        $app = new Main(self::PHONE);

        $data = $app->Contacts->AddContact(
            9123456789, // its just a sample
            'test'
        );

        $this->assertNotEmpty($data);
    }

    public function testDeleteContact(): void
    {
        $app = new Main(self::PHONE);

        $data = $app->Contacts->DeleteContact('u0HMR...'); // its just a sample

        $this->assertNotEmpty($data);
    }
}
