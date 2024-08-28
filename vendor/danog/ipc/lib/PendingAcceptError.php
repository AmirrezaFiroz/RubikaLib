<?php declare(strict_types=1);

namespace Amp\Ipc;

/**
 * Thrown in case a second read operation is attempted while another read operation is still pending.
 */
final class PendingAcceptError extends \Error
{
    public function __construct(
        string $message = 'The previous accept operation must complete before accept can be called again',
        int $code = 0,
        \Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
