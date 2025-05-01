<?php
declare(strict_types=1);

namespace PhpStreamIpc;

use Amp\Future;
use PhpStreamIpc\Message\Message;

/**
 * Common interface for an IPC session (sync or async).
 *
 * Provides methods to:
 *  - fire-and-forget notifications,
 *  - send requests and await responses,
 *  - register/unregister handlers for incoming messages.
 *
 * Implementations must respect configured timeouts and propagate errors
 * (e.g. TimeoutException when a request exceeds its deadline).
 */
interface IpcSession
{
    /**
     * Send a fire-and-forget notification to the peer.
     *
     * Notifications do not expect any reply.
     *
     * @param Message $msg The payload to send.
     */
    public function notify(Message $msg): void;

    /**
     * Send a request and receive a Future resolving to the response.
     *
     * The returned Future will complete with the peer’s response Message,
     * or fail with a TimeoutException if no response arrives in time.
     *
     * @param Message $msg The request payload.
     * @return Future<Message> Future resolving to the response.
     * @throws \Amp\TimeoutException If the response is not received within the timeout.
     */
    public function request(Message $msg): Future;

    /**
     * Register a handler for incoming notifications.
     *
     * Handlers are invoked for any message that is neither a request
     * nor a response to an outstanding request.
     *
     * @param \Closure(Message $message, IpcSession $session): void $handler
     *        Callback receiving the message and session.
     */
    public function onMessage(\Closure $handler): void;

    /**
     * Unregister a previously registered notification handler.
     *
     * @param \Closure(Message $message, IpcSession $session): void $handler
     *        The handler to remove.
     */
    public function offMessage(\Closure $handler): void;

    /**
     * Register a handler for incoming requests.
     *
     * The first handler that returns a non-null Message will have its
     * return value sent back as the response. Returning null means “I don’t handle this.”
     *
     * @param \Closure(Message $request, IpcSession $session): ?Message $handler
     *        Callback that returns a response Message or null.
     */
    public function onRequest(\Closure $handler): void;

    /**
     * Unregister a previously registered request handler.
     *
     * @param \Closure(Message $request, IpcSession $session): ?Message $handler
     *        The handler to remove.
     */
    public function offRequest(\Closure $handler): void;
}
