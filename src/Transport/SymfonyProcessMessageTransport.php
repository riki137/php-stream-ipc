<?php

declare(strict_types=1);

namespace StreamIpc\Transport;

use Closure;
use StreamIpc\Message\Message;
use StreamIpc\Serialization\MessageSerializer;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

/**
 * Message transport that communicates with a Symfony Process using an InputStream
 * and the callback mechanism from Process::start().
 */
final class SymfonyProcessMessageTransport implements MessageTransport
{
    private readonly InputStream $input;

    private readonly FrameCodec $codec;

    /** @var array<string, FrameCodec> */
    private array $codecs = [];

    /** @var Message[][] */
    private array $pending = [];

    private Closure $callback;

    /**
     * @param ?int $frameLimit Maximum allowed size of a single message frame.
     */
    public function __construct(
        private readonly Process $process,
        MessageSerializer $serializer,
        ?int $frameLimit = null,
    ) {
        $this->codec = new FrameCodec($serializer, $frameLimit);
        $this->input = new InputStream();
        $this->callback = function (string $type, string $data) use ($serializer, $frameLimit): void {
            $codec = $this->codecs[$type] ??= new FrameCodec($serializer, $frameLimit);
            foreach ($codec->feed($data) as $msg) {
                $this->pending[$type][] = $msg;
            }
        };
        $process->setInput($this->input);
        $process->start($this->callback);
    }

    /**
     * Write a message to the child process.
     */
    public function send(Message $message): void
    {
        $this->input->write($this->codec->pack($message));
    }

    /**
     * Check whether the process is still running.
     */
    public function isRunning(): bool
    {
        return $this->process->isRunning();
    }

    /**
     * Retrieve and clear any messages captured from the process output.
     *
     * @return Message[][] keyed by output type
     */
    public function takePending(): array
    {
        $pending = $this->pending;
        $this->pending = [];

        return $pending;
    }
}
