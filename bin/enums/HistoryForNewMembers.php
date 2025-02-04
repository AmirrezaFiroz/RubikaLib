<?php

declare(strict_types=1);

namespace RubikaLib\enums;

/**
 * chat history for new members
 */
enum HistoryForNewMembers: string
{
    case Hidden = 'Hidden';
    case Visible = 'Visible';
}
