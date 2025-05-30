<?php
declare(strict_types=1);

namespace StreamIpc;

use StreamIpc\Transport\NativeMessageTransport;
use RuntimeException;

/**
 * IPC peer implementation that works with standard PHP stream resources.
 */
final class NativeIpcPeer extends IpcPeer
{
    /**
     * Create a session using the given write stream and one or two read streams.
     *
     * @param resource      $write Stream used for writing
     * @param resource      $read  Primary read stream
     * @param resource|null $read2 Optional secondary read stream
     */
    public function createStreamSession($write, $read, $read2 = null): IpcSession
    {
        $reads = [$read];
        if ($read2 !== null) {
            $reads[] = $read2;
        }
        return $this->createSession(
            new NativeMessageTransport(
                $write,
                $reads,
                $this->defaultSerializer
            )
        );
    }

    /**
     * Convenience helper for communicating over STDOUT/STDIN.
     */
    public function createStdioSession(): IpcSession
    {
        return $this->createStreamSession(STDOUT, STDIN);
    }

    /**
     * Register an existing {@see NativeMessageTransport} instance.
     */
    public function createSessionFromTransport(NativeMessageTransport $transport): IpcSession
    {
        return $this->createSession($transport);
    }

    /**
     * Spawn a command and attach to its stdio pipes as a session.
     *
     * @param string              $command Command to execute
     * @param array<string,string> $args    Environment variables passed to the process
     * @param string|null          $cwd     Working directory
     */
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
            if ($error !== null) {
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

    /**
     * Wait for input on all sessions using {@see stream_select()}.
     */
    public function tick(?float $timeout = null): void
    {
        $streams = [];
        $sessionStreams = [];

        foreach ($this->sessions as $session) {
            $transport = $session->getTransport();
            if (!$transport instanceof NativeMessageTransport) {
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
