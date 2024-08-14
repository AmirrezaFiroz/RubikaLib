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
     * this function will call when runner class is set and starts to get updates
     *
     * @param array $mySelf account info
     * @return void
     */
    public function onStart(array $mySelf): void;

    /**
     * this function will call when a new update got from API
     *
     * @param array $update
     * @param Main $class to working with methods
     * @return void
     */
    public function onMessage(array $update, Main $class): void;

    /**
     * when a chat activitie catched
     *
     * @param chatActivities $activitie
     * @param string $guid this chat which update is from (group, chat, ...)
     * @param string $from the activitie maker person (user)
     * @param Main $class to working with methods
     * @return void
     */
    public function onAction(chatActivities $activitie, string $guid, string $from, Main $class): void;
}
