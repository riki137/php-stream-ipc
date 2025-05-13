<?php
declare(strict_types=1);

namespace Tests\PhpStreamIpc\Unit;

use PHPUnit\Framework\TestCase;
use PhpStreamIpc\Envelope\RequestEnvelope;
use PhpStreamIpc\Envelope\ResponseEnvelope;
use PhpStreamIpc\Message\LogMessage;

final class EnvelopeTest extends TestCase
{
    public function testRequestEnvelopeStoresProperties(): void
    {
        $msg      = new LogMessage('payload', 'info');
        $envelope = new RequestEnvelope('abc-123', $msg);

        $this->assertSame('abc-123', $envelope->id);
        $this->assertSame($msg, $envelope->request);
    }

    public function testResponseEnvelopeStoresProperties(): void
    {
        $msg      = new LogMessage('reply', 'error');
        $envelope = new ResponseEnvelope('req-456', $msg);

        $this->assertSame('req-456', $envelope->id);
        $this->assertSame($msg, $envelope->response);
    }

    public function testRequestEnvelopePropertiesArePubliclyReadable(): void
    {
        $msg      = new LogMessage('foo', 'debug');
        $envelope = new RequestEnvelope('r1', $msg);

        // direct property access
        $this->assertSame('r1', $envelope->id);
        $this->assertSame($msg, $envelope->request);
    }

    public function testResponseEnvelopePropertiesArePubliclyReadable(): void
    {
        $msg      = new LogMessage('bar', 'error');
        $envelope = new ResponseEnvelope('r2', $msg);

        $this->assertSame('r2', $envelope->id);
        $this->assertSame($msg, $envelope->response);
    }
}
