<?php
declare(strict_types=1);

namespace PhpStreamIpc;

use PhpStreamIpc\Transport\StreamMessageTransport;
use RuntimeException;

final class StreamIpcPeer extends IpcPeer
{
    public function createStreamSession($write, $read, $read2 = null): IpcSession
    {
        $reads = [$read];
        if ($read2 !== null) {
            $reads[] = $read2;
        }
        return $this->createSession(
            new StreamMessageTransport(
                $write,
                $reads,
                $this->defaultSerializer
            )
        );
    }

    public function createStdioSession(): IpcSession
    {
        return $this->createStreamSession(STDOUT, STDIN);
    }

    public function createSessionFromTransport(StreamMessageTransport $transport): IpcSession
    {
        return $this->createSession($transport);
    }

    public function createCommandSession(string $command, array $args, ?string $cwd = null): IpcSession
    {
        $descriptors = [
            0 => ['pipe', 'r'], // child STDIN
            1 => ['pipe', 'w'], // child STDOUT
            2 => ['pipe', 'w'], // child STDERR
        ];

        $pipes = [];
        $process = @proc_open($command, $descriptors, $pipes, $cwd, $args);

        if (!is_resource($process)) {
            $error = error_get_last();
            $message = 'Failed to start command process';
            if ($error && isset($error['message'])) {
                $message .= ': ' . $error['message'];
            }
            throw new RuntimeException($message);
        }

        if (!isset($pipes[0], $pipes[1], $pipes[2])) {
            proc_close($process);
            throw new RuntimeException('Failed to open all required process pipes (STDIN, STDOUT, STDERR)');
        }

        return $this->createStreamSession($pipes[0], $pipes[1], $pipes[2]);
    }

    public function tick(?float $timeout = null): void
    {
        $streams = [];
        $sessionStreams = [];

        foreach ($this->sessions as $session) {
            $transport = $session->getTransport();
            if (!$transport instanceof StreamMessageTransport) {
                continue;
            }
            foreach ($transport->getReadStreams() as $stream) {
                $streams[(int)$stream] = $stream;
                $sessionStreams[(int)$stream] = [$session, $transport];
            }
        }

        if ($streams === []) {
            return;
        }

        $reads = array_values($streams);
        $writes = $except = [];

        $sec = $usec = null;
        if ($timeout !== null) {
            $sec = (int)floor($timeout);
            $usec = (int)(($timeout - $sec) * 1e6);
        }
        $ready = stream_select($reads, $writes, $except, $sec, $usec);

        if ($ready > 0) {
            foreach ($reads as $stream) {
                [$session, $transport] = $sessionStreams[(int)$stream];
                foreach ($transport->readFromStream($stream) as $message) {
                    $session->dispatch($message);
                }
            }
        }
    }
}
