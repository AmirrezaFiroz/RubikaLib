<?php

declare(strict_types=1);

namespace RubikaLib\Enums;

/**
 * status of phone number on dev-mode
 */
enum DevModeStatus
{
    case SendCode;
    case SendPassKey;
    case SignIn;
}
