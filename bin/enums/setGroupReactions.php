<?php

declare(strict_types=1);

namespace RubikaLib\Enums;

/**
 * group reactions mode
 */
enum SetGroupReactions: string
{
    case Disabled = 'Disabled';
    case All = 'All';
    case Selected = 'Selected';
}
