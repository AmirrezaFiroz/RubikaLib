<?php

declare(strict_types=1);

namespace RubikaLib\Enums;

/**
 * chat activities (on top of chats)
 */
enum ChatActivities: string
{
    case Typing = 'Typing';
    case Uploading = 'Uploading';
    case Recording = 'Recording';
}
