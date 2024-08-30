<?php

declare(strict_types=1);

namespace RubikaLib\enums;

/**
 * Choose Chat Type
 */
enum ChatTypes: string
{
    case Bot = 'Bot';
    case Group = 'Group';
    case Channel = 'Channel';
    case Contact = 'Contact';
    case NonContact = 'NonConatct';
    case Service = 'Service';
    case User = 'User';
    case Mute = 'Mute';
    case Read = 'Read';
}
