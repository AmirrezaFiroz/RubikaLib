<?php

declare(strict_types=1);

namespace RubikaLib\Interfaces;

use RubikaLib\Cryption;
use RubikaLib\enums\AppType;
use RubikaLib\Utils\userAgent;

/**
 * settings for library
 */
final class MainSettings
{
    /**
     * Default UserAgent For Library (it just used in login and will save in session for next uses)
     *
     * @var string you can generate one by using RubikaLib\Utils\userAgent::generate()
     */
    public ?string $UserAgent;

    /**
     * tmp_session For Sign In (it will be changes with API)
     *
     * @var string
     */
    public ?string $tmp_session;

    /**
     * Use Optimal Mode For CPU and RAM Sources
     *
     * @var boolean
     */
    public bool $Optimal = true;

    /**
     * Where Library Files Will Save And Use
     *
     * @var string default: lib/
     */
    public string $Base = 'lib/';

    /**
     * App Type
     *
     * @var AppType Rubika or Shad
     */
    public AppType $AppType = AppType::Rubika;

    /**
     * show progress bar on file uploading to API
     *
     * @var boolean
     */
    public bool $ShowProgressBar = false;

    /**
     * Keep Everything Updated
     *
     * @var boolean
     */
    public bool $KeepUpdated = true;

    public function __construct()
    {
        $this->setUserAgent(
            userAgent::generate()
        );
        $this->setTmp_session(
            Cryption::GenerateRandom_tmp_session()
        );
    }

    /**
     * Set Default UserAgent For Library (just for login)
     *
     * @param string $UserAgent you can generate one by using RubikaLib\Utils\userAgent::generate()
     * @return self
     */
    public function setUserAgent(string $UserAgent): self
    {
        $this->UserAgent = $UserAgent;
        return $this;
    }

    /**
     * Set tmp_session For Sign In (just for login)
     *
     * @param string $tmp_session you can generate one by using RubikaLib\Cryption::GenerateRandom_tmp_session() 
     * @return self
     */
    public function setTmp_session(string $tmp_session): self
    {
        $this->tmp_session = $tmp_session;
        return $this;
    }

    /**
     * Turn Optimal Mode On Or Off
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
     * @param string $Base
     * @return self
     */
    public function setBase(string $Base): self
    {
        $this->Base = $Base;
        return $this;
    }

    /**
     * Set App Type
     *
     * @param AppType $AppType
     * @return self
     */
    public function setAppType(AppType $AppType): self
    {
        $this->$AppType = $AppType;
        return $this;
    }

    /**
     * Set Show Progress Bar
     *
     * @param bool $ShowProgressBar
     * @return self
     */
    public function setShowProgressBar(bool $ShowProgressBar): self
    {
        $this->ShowProgressBar = $ShowProgressBar;
        return $this;
    }

    /**
     * Set Keep Everything Updated
     *
     * @param bool $KeepUpdated
     * @return self
     */
    public function setKeepUpdated(bool $KeepUpdated): self
    {
        $this->KeepUpdated = $KeepUpdated;
        return $this;
    }
}
