<?php

declare(strict_types=1);

namespace RubikaLib;

use Exception;

/**
 * special Exception class
 */
final class Logger extends Exception
{
    /**
     * if there is need full datas
     *
     * @var array|object by default: array()
     */
    public array|object $obj = [];

    public function __construct(string $message = "", int $code = 0, \Throwable|null $previous = null, array|object|null $data = null)
    {
        parent::__construct($message, $code, $previous);

        if (!is_null($data)) {
            $this->obj = $data;
        }
    }
}
