<?php

declare(strict_types=1);

namespace RubikaLib;

use RubikaLib\Utils\Tools;

/**
 * contacts object
 */
final class Contacts
{
    public function __construct(
        private Session $session,
        private Requests $req
    ) {}

    /**
     * seend chats
     *
     * @param string $start_id next user_guid u0HMRYa...
     * @return array API result
     */
    public function getContacts(string $start_id = ''): array
    {
        if ($start_id != '') {
            return $this->req->SendRequest('getContacts', [
                'start_id' => $start_id
            ], $this->session)['data'];
        }

        return $this->req->SendRequest('getContacts', array(), $this->session)['data'];
    }

    /**
     * add new contact
     *
     * @param integer $phone_number 9123456789 or 989123456789
     * @param string $first_name
     * @param string $last_name
     * @return array API result
     */
    public function AddContact(int $phone_number, string $first_name, string $last_name = ''): array
    {
        return $this->req->SendRequest('addAddressBook', [
            'phone' => '+' . Tools::ReplaceTruePhoneNumber($phone_number),
            'first_name' => $first_name,
            'last_name' => $last_name
        ], $this->session)['data'];
    }

    /**
     * delete contact
     *
     * @param string $user_guid
     * @return array API result
     */
    public function DeleteContact(string $user_guid): array
    {
        return $this->req->SendRequest('deleteContact', [
            'user_guid' => $user_guid
        ], $this->session)['data'];
    }
}
