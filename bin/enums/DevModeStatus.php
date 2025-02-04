<?php

declare(strict_types=1);

namespace RubikaLib\enums;

/**
 * status of phone number on dev-mode
 */
enum DevModeStatus
{
    case SendCode;
    case SendPassKey;
    case SignIn;
}
