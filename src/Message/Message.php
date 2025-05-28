<?php

declare(strict_types=1);

namespace StreamIpc\Message;

/**
 * A marker interface indicating that an object can be transmitted as a message over the IPC system.
 * Implementing classes are typically data transfer objects (DTOs) or simple commands/events.
 */
interface Message
{
}
