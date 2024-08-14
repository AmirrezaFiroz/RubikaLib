<?php

declare(strict_types=1);

namespace RubikaLib\enums;

/**
 * delete types
 */
enum deleteType: string
{
    /**
     * delete for all
     */
    case Global = 'Global';
    /**
     * delete just for me
     */
    case Local = 'Local';
}
