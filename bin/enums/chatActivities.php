<?php

declare(strict_types=1);

namespace RubikaLib\enums;

/**
 * chat activities
 */
enum chatActivities: string
{
    case Typing = 'Typing';
    case Uploading = 'Uploading';
    case Recording = 'Recording';
}
