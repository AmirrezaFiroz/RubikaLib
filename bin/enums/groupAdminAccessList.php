<?php

declare(strict_types=1);

namespace RubikaLib\Enums;

/**
 * group admin list
 */
enum GroupAdminAccessList: string
{
    case PinMessages = 'PinMessages';
    case DeleteGlobalAllMessages = 'DeleteGlobalAllMessages';
    case BanMember = 'BanMember';
    case SetAdmin = 'SetAdmin';
    case SetJoinLink = 'SetJoinLink';
    case SetMemberAccess = 'SetMemberAccess';
    case ChangeInfo = 'ChangeInfo';
}
