<?php

declare(strict_types=1);

namespace RubikaLib\Interfaces;

/**
 * settings for group-users-default-accesses
 */
final class GroupDefaultAccesses
{
    public bool $ViewAdmins = true;
    public bool $SendMessages = true;
    public bool $ViewMembers = true;
    public bool $AddMember = false;

    public function setViewAdmins(bool $ViewAdmins): self
    {
        $this->ViewAdmins = $ViewAdmins;
        return $this;
    }

    public function setSendMessages(bool $SendMessages): self
    {
        $this->SendMessages = $SendMessages;
        return $this;
    }

    public function setViewMembers(bool $ViewMembers): self
    {
        $this->ViewMembers = $ViewMembers;
        return $this;
    }

    public function setAddMember(bool $AddMember): self
    {
        $this->AddMember = $AddMember;
        return $this;
    }
}
