<?php

declare(strict_types=1);

namespace RubikaLib\interfaces;

use RubikaLib\enums\chatActivities;
use RubikaLib\Main;

/**
 * interface for Main class to pass updates
 */
interface runner
{
    /**
     * this is a function which callen when wunner is started
     *
     * @return void
     */
    public function onStart(array $mySelf): void;

    /**
     * this function callen when API send you a new update
     *
     * @param array $update
     * @param Main $class to working with methods
     * @return void
     */
    public function onMessage(array $update, Main $class): void;

    /**
     * when a chat activitie send
     *
     * @param chatActivities $activitie
     * @param string $guid this chat which update is from (group, chat, ...)
     * @param string $from the activitie maker person (user)
     * @return void
     */
    public function onAction(chatActivities $activitie, string $guid, string $from, Main $class): void;
}
