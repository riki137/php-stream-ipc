<?php
declare(strict_types=1);

namespace StreamIpc;

use RuntimeException;
use TypeError;
use ValueError;
use StreamIpc\Transport\MessageTransport;
use StreamIpc\Transport\NativeMessageTransport;

/**
 * IPC peer implementation that works with standard PHP stream resources.
 */
final class NativeIpcPeer extends IpcPeer
{
    private const USEC = 1e6;

    /** @var resource[] keyed by (int)$stream */
    private array $readSet = [];

    /** @var array<int, array{IpcSession, NativeMessageTransport}> */
    private array $fdMap = [];

    /**
     * Create a session using the given write stream and one or two read streams.
     *
     * @param resource $write Stream used for writing
     * @param resource $read Primary read stream
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
     * @param string $command Command to execute
     * @param array<string,string> $args Environment variables passed to the process
     * @param string|null $cwd Working directory
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

    protected function createSession(MessageTransport $transport): IpcSession
    {
        $session = parent::createSession($transport);
        $this->addSessionStreams($session);
        return $session;
    }

    public function removeSession(IpcSession $session): void
    {
        $this->removeSessionStreams($session);
        parent::removeSession($session);
    }

    private function addSessionStreams(IpcSession $session): void
    {
        $transport = $session->getTransport();
        if (!$transport instanceof NativeMessageTransport) {
            return;
        }

        foreach ($transport->getReadStreams() as $stream) {
            $key = (int)$stream;
            $this->readSet[$key] = $stream;
            $this->fdMap[$key] = [$session, $transport];
        }
    }

    private function removeSessionStreams(IpcSession $session): void
    {
        foreach ($this->fdMap as $key => [$sess, $_]) {
            if ($sess === $session) {
                unset($this->fdMap[$key], $this->readSet[$key]);
            }
        }
    }

    public function tick(?float $timeout = null): void
    {
        if ($this->readSet === []) {
            return;
        }

        // copy because stream_select() will modify it
        $reads = $this->readSet;

        $sec = $usec = null;
        if ($timeout !== null) {
            $sec = (int)floor($timeout);
            $usec = (int)(($timeout - $sec) * self::USEC);
        }

        $writes = $except = null;
        try {
            if (@stream_select($reads, $writes, $except, $sec, $usec) <= 0) {
                // no streams ready or error occurred
                return;
            }
        } catch (TypeError|ValueError $e) {
            $handled = false;
            foreach ($this->readSet as $key => $stream) {
                $test = [$stream];
                $w = $ex = null;
                try {
                    stream_select($test, $w, $ex, 0, 0);
                } catch (TypeError|ValueError) {
                    [$session] = $this->fdMap[$key];
                    $session->triggerException(new InvalidStreamException($session, null, 0, $e));
                    $handled = true;
                }
            }

            if (!$handled) {
                throw $e;
            }

            return;
        }

        foreach ($reads as $stream) {
            $key = (int)$stream;
            [$session, $transport] = $this->fdMap[$key];

            // drain everything currently available
            $messages = $transport->readFromStream($stream);
            foreach ($messages as $m) {
                $session->dispatch($m);
            }
        }
    }
}
