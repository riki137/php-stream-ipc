<?php

declare(strict_types=1);

namespace PhpStreamIpc\Transport;

use Closure;
use PhpStreamIpc\Message\Message;
use PhpStreamIpc\Serialization\MessageSerializer;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

/**
 * Message transport that communicates with a Symfony Process using an InputStream
 * and the callback mechanism from Process::start().
 */
final class SymfonyProcessMessageTransport implements MessageTransport
{
    private const DEFAULT_SLEEP_TICK = 500;
    private readonly InputStream $input;
    private readonly FrameCodec $codec;
    private array $codecs = [];
    /** @var Message[][] */
    private array $pending = [];
    private Closure $callback;
    private int $sleepTick;

    public function __construct(
        private readonly Process $process,
        MessageSerializer $serializer,
        ?int $frameLimit = null,
        ?int $sleepTick = null
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
        $this->sleepTick = $sleepTick ?? self::DEFAULT_SLEEP_TICK;
    }

    public function send(Message $message): void
    {
        $this->input->write($this->codec->pack($message));
    }

    public function tick(array $sessions, ?float $timeout = null): void
    {
        $end = $timeout === null ? null : microtime(true) + $timeout;
        while ($sessions !== [] && microtime(true) < $end) {
            foreach ($sessions as $key => $session) {
                $transport = $session->getTransport();
                if (!$transport instanceof self) {
                    unset($sessions[$key]);
                    continue;
                }
                if (!$transport->process->isRunning()) {
                    unset($sessions[$key]);
                    continue;
                }
                if ($transport->pending === []) {
                    continue;
                }
                foreach ($transport->pending as $messages) {
                    foreach ($messages as $message) {
                        $session->dispatch($message);
                    }
                }
                $transport->pending = [];
                break 2;
            }
            usleep($this->sleepTick);
        }

    }
}
