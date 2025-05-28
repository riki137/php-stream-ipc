<?php
namespace StreamIpc\Tests\Unit;

use PHPUnit\Framework\TestCase;
use StreamIpc\Transport\FrameCodec;
use StreamIpc\Serialization\NativeMessageSerializer;
use StreamIpc\Tests\Fixtures\SimpleMessage;
use StreamIpc\Message\LogMessage;

final class FrameCodecTest extends TestCase
{
    public function testFeedPreservesOverlapAndLogsJunk(): void
    {
        $serializer = new NativeMessageSerializer();
        $codec = new FrameCodec($serializer);

        $prefixLen = 2;
        $junkChunk = 'junk' . substr(FrameCodec::MAGIC, 0, $prefixLen);
        $msgs = $codec->feed($junkChunk);

        $this->assertCount(1, $msgs);
        $this->assertInstanceOf(LogMessage::class, $msgs[0]);
        $this->assertSame('junk', $msgs[0]->message);

        $frame = $codec->pack(new SimpleMessage('ok'));
        $msgs = $codec->feed(substr($frame, $prefixLen));

        $this->assertCount(1, $msgs);
        $this->assertInstanceOf(SimpleMessage::class, $msgs[0]);
        $this->assertSame('ok', $msgs[0]->text);
    }
}
