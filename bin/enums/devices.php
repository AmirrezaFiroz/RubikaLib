<?php

declare(strict_types=1);

namespace RubikaLib\Enums;

/**
 * for choose userAgents
 */
enum devices: string
{
    case firefox = 'firefox';
    case chrome = 'chrome';
    case mobile = 'mobile';
    case windows = 'windows';
    case mac = 'mac';
    case iphone = 'iphone';
    case ipad = 'ipad';
    case ipod = 'ipod';
    case android = 'android';
}
