<?php

declare(strict_types=1);

namespace RubikaLib;

use RubikaLib\enums\ChatTypes, RubikaLib\Utils\SuggestedFolders;

/**
 * folders object
 */
final class Folders
{
    public ?SuggestedFolders $SuggestedFolders;

    public function __construct(
        private Requests $req,
        private Session $session,
        private Main $main
    ) {
        $this->SuggestedFolders = new SuggestedFolders($this);
    }

    /**
     * get folders list
     *
     * @return array API result
     */
    public function getFolders(): array
    {
        return $this->req->SendRequest('getFolders', array(), $this->session)['data'];
    }

    /**
     * Delete Folder
     *
     * @param string $folder_id
     * @return array API result
     */
    public function DeleteFolder(string $folder_id): array
    {
        return $this->req->SendRequest('deleteFolder', [
            'folder_id' => $folder_id
        ], $this->session)['data'];
    }

    /**
     * add new folder
     *
     * @param string $name
     * @param array $guids
     * @param array $exclude_object_guids
     * @param boolean $is_add_to_top
     * @param array $include_chat_types
     * @param array $exclude_chat_types
     * @return array
     */
    public function AddFolder(
        string $name,
        array $guids = [],
        array $exclude_object_guids = [],
        bool $is_add_to_top = true,
        array $include_chat_types = [],
        array $exclude_chat_types = []
    ): array {
        $included = [];

        foreach ($include_chat_types as $includeChatType) {
            if (!in_array($includeChatType, [ChatTypes::Mute, ChatTypes::Read, ChatTypes::User])) {
                $included[] = $includeChatType->value . 's';
            } else {
                $included[] = $includeChatType->value;
            }
        }

        /*if (count($guids) != 0) {
            $c = $this->main->getContacts();
            $contacts = [];
            foreach ($c['users'] as $con) {
                $contacts[] = $con['user_guid'];
            }
            while ($c['has_continue']) {
                $c = $this->main->getContacts($c['next_start_id']);
                foreach ($c['users'] as $con) {
                    $contacts[] = $con['user_guid'];
                }
            }

            foreach ($guids as $guid) {
                $x = Tools::ChatTypeByGuid($guid);
                if ($x === false) throw new Failure('unknowen guid for: ' . $guid);

                if ($x == ChatTypes::User) {
                    if (in_array($guid, $contacts)) {
                        if (isset($includeChatType[ChatTypes::Contact->value . 's'])) continue;
                        $included[] = ChatTypes::Contact->value . 's';
                    } else {
                        if (isset($includeChatType[ChatTypes::NonContact->value . 's'])) continue;
                        $included[] = ChatTypes::NonContact->value . 's';
                    }
                } else {
                    if (isset($includeChatType[$x->value . 's'])) continue;
                    $included[] = $x->value . 's';
                }
            }
        }*/

        return $this->req->SendRequest('addFolder', [
            'name' => $name,
            'include_chat_types' => $included,
            'exclude_chat_types' => $exclude_chat_types,
            'include_object_guids' => $guids,
            'exclude_object_guids' => $exclude_object_guids,
            'is_add_to_top' => $is_add_to_top
        ], $this->session)['data'];
    }
}
