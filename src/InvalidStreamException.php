<?php

declare(strict_types=1);

namespace StreamIpc;

use RuntimeException;
use Throwable;

/**
 * Exception thrown when a registered stream becomes invalid.
 */
class InvalidStreamException extends RuntimeException
{
    private IpcSession $session;

    public function __construct(IpcSession $session, ?string $message = null, int $code = 0, ?Throwable $previous = null)
    {
        $this->session = $session;
        parent::__construct($message ?? 'Invalid stream resource', $code, $previous);
    }

    public function getSession(): IpcSession
    {
        return $this->session;
    }
}
