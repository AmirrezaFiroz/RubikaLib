<?php

declare(strict_types=1);

namespace RubikaLib\Enums;

/**
 * poll types
 */
enum PollType: string
{
    /**
     * regular poll (can be for voting)
     */
    case Regular = 'Regular';
    /**
     * Quiz ooptions (good for matchs)
     */
    case Quiz = 'Quiz';
}
