<?php

declare(strict_types=1);

namespace RubikaLib\interfaces;

use RubikaLib\enums\chatActivities;
use RubikaLib\Main;

/**
 * interface for Main class to pass updates
 */
interface Runner
{
    /**
     * This Function Will Call When \Runner Class Is Set And Starts To Get Updates
     *
     * @param array $mySelf account info
     * @return void
     */
    public function onStart(array $mySelf): void;

    /**
     * This Function Will Call When A New Update Got From API
     *
     * @param array $update
     * @param Main $class to working with methods
     * @return void
     */
    public function onMessage(array $update, Main $class): void;

    /**
     * When A Chat Activitie Catched
     *
     * @param chatActivities $activitie
     * @param string $guid this chat which update is from (group, chat, ...)
     * @param string $from the activitie maker person (user)
     * @param Main $class to working with methods
     * @return void
     */
    public function onAction(chatActivities $activitie, string $guid, string $from, Main $class): void;
}
