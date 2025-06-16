<?php

declare(strict_types=1);

namespace StreamIpc\Message;

use Throwable;

final readonly class ErrorMessage implements Message
{
    private string $errorMessage;

    public function __construct(string $errorMessage, ?Throwable $exception = null)
    {
        if ($exception !== null) {
            $errorMessage .= PHP_EOL . get_class($exception) . ': ' . $exception->getMessage() . PHP_EOL . $exception->getTraceAsString();
        }

        $this->errorMessage = trim($errorMessage);
    }

    public function toString(): string
    {
        return $this->errorMessage;
    }
}
