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
    /**
     * auth for sign up (it will be changes with API)
     *
     * @var string|null
     */
    public ?string $auth;
    /**
     * use optimal mode
     *
     * @var boolean
     */
    public bool $Optimal = true;
    /**
     * Where Library Files Will Save And Use
     *
     * @var string default: lib/
     */
    public string $base = 'lib/';

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
     * set default auth for library as tmp_session in login step
     *
     * @param string $auth you can generate one by using RubikaLib\Cryption::azRand() 
     * @return self
     */
    public function setAuth(string $auth): self
    {
        $this->auth = $auth;
        return $this;
    }

    /**
     * set optimal mode
     *
     * @param boolean $Optimal
     * @return self
     */
    public function setOptimal(bool $Optimal): self
    {
        $this->Optimal = $Optimal;
        return $this;
    }

    /**
     * Set Base Dir
     *
     * @param string $base
     * @return self
     */
    public function setBase(string $base): self
    {
        $this->base = $base;
        return $this;
    }
}
