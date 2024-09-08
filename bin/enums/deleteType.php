<?php

declare(strict_types=1);

namespace RubikaLib\Enums;

/**
 * delete types
 */
enum DeleteType: string
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
