<?php

declare(strict_types=1);

namespace RubikaLib\Utils;

use RubikaLib\Enums\ChatTypes, RubikaLib\Folders;

/**
 * suggested folders for rubika or shad
 */
final class SuggestedFolders
{
    public function __construct(
        private Folders $obj
    ) {}

    /**
     * Add Channels Folders
     *
     * @return array API result
     */
    public function SetUpChannelsFolder(): array
    {
        return $this->obj->AddFolder('کانال ها', include_chat_types: [ChatTypes::Channel]);
    }

    /**
     * Add Groups Folders
     *
     * @return array API result
     */
    public function SetUpGroupsFolder(): array
    {
        return $this->obj->AddFolder('گروه ها', include_chat_types: [ChatTypes::Group]);
    }

    /**
     * Add Personal Chats Folders
     *
     * @return array API result
     */
    public function SetUpPersonalFolder(): array
    {
        return $this->obj->AddFolder('پیام های شخصی', include_chat_types: [ChatTypes::Contact, ChatTypes::NonContact]);
    }

    /**
     * Add Unreaded Chats Folders
     *
     * @return array API result
     */
    public function SetUpUnReadFolder(): array
    {
        return $this->obj->AddFolder(
            'خوانده نشده ها',
            include_chat_types: [ChatTypes::Contact, ChatTypes::NonContact, ChatTypes::Group, ChatTypes::Channel, ChatTypes::Bot],
            exclude_chat_types: [ChatTypes::Mute, ChatTypes::Read]
        );
    }

    /**
     * Add Bots Folders
     *
     * @return array API result
     */
    public function SetUpBotsFolder(): array
    {
        return $this->obj->AddFolder('ربات ها', include_chat_types: [ChatTypes::Bot]);
    }
}
