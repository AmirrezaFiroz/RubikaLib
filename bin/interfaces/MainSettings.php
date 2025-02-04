<?php

declare(strict_types=1);

namespace RubikaLib\interfaces;

use Exception;
use RubikaLib\Cryption;
use RubikaLib\enums\AppType;
use RubikaLib\Failure;
use RubikaLib\utils\userAgent;

/**
 * settings for library
 */
final class MainSettings
{
    /**
     * Default UserAgent For Library (it just used in login and will save in session for next uses)
     *
     * @var string you can generate one by using RubikaLib\utils\userAgent::generate()
     */
    private ?string $UserAgent;

    /**
     * tmp_session For Sign In (it will be changes with API)
     *
     * @var string
     */
    private ?string $tmp_session;

    /**
     * Use Optimal Mode For CPU and RAM Sources
     *
     * @var boolean
     */
    private bool $Optimal = true;

    /**
     * Where Library Files Will Be Saved And Used
     *
     * @var string default: lib/
     */
    private string $Base = 'lib/';

    /**
     * App Type
     *
     * @var AppType Rubika or Shad
     */
    private AppType $AppType = AppType::Rubika;

    /**
     * show progress bar on file uploading to API
     *
     * @var boolean
     */
    private bool $ShowProgresses = false;

    /**
     * Keep Everything Updated
     *
     * @var boolean
     */
    private bool $KeepUpdated = false;

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
     * @param string $UserAgent you can generate one by using RubikaLib\utils\userAgent::generate()
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
     * Set WorkDir
     *
     * @param string $Base must be end with slash:
     * @example . lib/ , dir/ , dir/mybot/files/
     * @return self
     */
    public function setBase(string $Base): self
    {
        if (!str_ends_with($Base, '/')) $Base .= '/';
        $this->Base = $Base;
        return $this;
    }

    public function __set($name, $value)
    {
        if (property_exists($this, $name)) {
            if (gettype($this->{$name}) == gettype($value)) {
                switch ($name) {
                    case 'Base':
                        if (!str_ends_with($value, '/')) $value .= '/';
                        $this->{$name} = $value;
                        break;

                    case 'UserAgent':
                        if ($this->ShowProgresses) echo "checking UserAgent: $value\n";
                        $res = json_decode(file_get_contents("https://useragentstring.com/?uas=" . urlencode($value) . "&getJSON=all"), true);
                        if (!isset($res['agent_type']) or $res['agent_type'] == 'unknown') {
                            throw new Failure("can't trust this useragent: $value\n");
                        }
                        $this->{$name} = $value;
                        break;

                    case 'tmp_session':
                        if (preg_match('/^[a-z]+$/', $value)) {
                            $this->{$name} = $value;
                        } else {
                            throw new Failure("can't accept this tmp_session(just accepted => 'abcdefghijklmnopqrstuvwxyz'): $value\n");
                        }
                        break;

                    default:
                        $this->{$name} = $value;
                        break;
                }
            } else {
                throw new Failure("invalid data type for $name : " . gettype($this->{$name}) . ' , exceped ' . gettype($value) . " .\n");
            }
        } else {
            throw new Exception("PHP Warning:  Undefined property: MainSettings::\${$name} in {$_SERVER['DOCUMENT_ROOT']} \n\nWarning: Undefined property: MainSettings::\${$name} in {$_SERVER['DOCUMENT_ROOT']}");
        }
    }

    public function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->{$name};
        } else {
            throw new Exception("PHP Warning:  Undefined property: MainSettings::\${$name} in {$_SERVER['DOCUMENT_ROOT']} \n\nWarning: Undefined property: MainSettings::\${$name} in {$_SERVER['DOCUMENT_ROOT']}");
        }
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
     * @param bool $ShowProgresses
     * @return self
     */
    public function setShowProgresses(bool $ShowProgresses): self
    {
        $this->ShowProgresses = $ShowProgresses;
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
