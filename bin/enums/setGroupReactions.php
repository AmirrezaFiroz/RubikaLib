<?php

declare(strict_types=1);

namespace RubikaLib\enums;

/**
 * group reactions mode
 */
enum setGroupReactions: string
{
    case Disabled = 'Disabled';
    case All = 'All';
    case Selected = 'Selected';
}
