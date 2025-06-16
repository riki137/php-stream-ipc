<?php

declare(strict_types=1);

namespace StreamIpc\Message;

use Throwable;

final readonly class ErrorMessage implements Message
{
    private string $errorMessage;

    public function __construct(
        string $errorMessage,
        ?Throwable $exception = null,
    ) {
        if ($exception !== null) {
            $this->errorMessage = trim($errorMessage . PHP_EOL . get_class($exception) . ': ' . $exception->getMessage() . PHP_EOL . $exception->getTraceAsString());
        } else {
            $this->errorMessage = $errorMessage;
        }
    }

    public function toString(): string
    {
        if (isset($this->exceptionClass, $this->exceptionMessage, $this->stackTrace)) {
            return $this->errorMessage . PHP_EOL . $this->exceptionClass . ': ' . $this->exceptionMessage . ', Stack Trace: ' . $this->stackTrace;
        }
        return $this->errorMessage;
    }
}
