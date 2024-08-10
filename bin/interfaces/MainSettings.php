<?php

declare(strict_types=1);

namespace RubikaLib\interfaces;

use RubikaLib\Cryption;
use RubikaLib\Utils\userAgent;

/**
 * settings for library
 */
final class MainSettings
{
    /**
     * default useragent for library (it just used in login and will save in session for next uses)
     *
     * @var string|null
     */
    public ?string $userAgent;
    public ?string $auth;

    public function __construct()
    {
        $this->setUserAgent(
            userAgent::generate()
        );
        $this->setAuth(
            Cryption::azRand()
        );
    }

    /**
     * set default useragent for library
     *
     * @param string $userAgent you can generate one by using RubikaLib\Utils\userAgent::generate()
     * @return self
     */
    public function setUserAgent(string $userAgent): self
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    /**
     * set default auth for library
     *
     * @param string $auth you can generate one by using RubikaLib\Cryption::azRand() 
     * @return self
     */
    public function setAuth(string $auth): self
    {
        $this->auth = $auth;

        return $this;
    }
}
