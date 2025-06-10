<?php

namespace StreamIpc\Tests\Unit;

use PHPUnit\Framework\TestCase;
use StreamIpc\Serialization\MessageSerializer;
use StreamIpc\Transport\FrameCodec;
use StreamIpc\Serialization\NativeMessageSerializer;
use StreamIpc\Tests\Fixtures\SimpleMessage;
use StreamIpc\Message\LogMessage;
use RuntimeException;
use StreamIpc\Message\Message;
use Throwable;

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

    /** Helpers ****************************************************************/

    private function makeCodec(?int $max = null): FrameCodec
    {
        return new FrameCodec(new NativeMessageSerializer(), $max);
    }

    private function assertSimple(Message $msg, string $expected): void
    {
        self::assertInstanceOf(SimpleMessage::class, $msg);
        self::assertSame($expected, $msg->text);
    }

    /** 1. Handles *very* large frames in a single chunk ************************/

    public function testSingleHugeFrame(): void
    {
        $limit   = 15 * 1024 * 1024;                    // 15 MB limit
        $codec   = $this->makeCodec($limit);

        // Generate a payload with exactly 10 million bytes (10 MB)
        $payload = str_repeat('A', 10_000_000);

        $frame   = $codec->pack(new SimpleMessage($payload));
        $msgs    = $codec->feed($frame);

        self::assertCount(1, $msgs);
        $this->assertSimple($msgs[0], $payload);
        self::assertFalse($codec->hasBufferedData());
    }

    /** 2. Same huge frame, but arriving byte-by-byte ***************************/

    public function testHugeFrameArrivingSlowly(): void
    {
        $codec = $this->makeCodec();
        $payload = str_repeat('x', 3_000_000);
        $frame = $codec->pack(new SimpleMessage($payload));

        // feed in random-sized pieces (1-7 bytes)
        $offset = 0;
        $msgs = [];
        while ($offset < strlen($frame)) {
            $len = random_int(1, 7);
            $chunk = substr($frame, $offset, $len);
            $msgs = array_merge($msgs, $codec->feed($chunk));
            $offset += $len;
        }

        self::assertCount(1, $msgs);
        $this->assertSimple($msgs[0], $payload);
    }

    /** 3. Many frames crammed in one TCP packet ********************************/

    public function testMultipleFramesInOneChunk(): void
    {
        $codec = $this->makeCodec();
        $frames = '';
        $texts = [];

        for ($i = 0; $i < 50; $i++) {
            $text = "msg#$i";
            $texts[$i] = $text;
            $frames .= $codec->pack(new SimpleMessage($text));
        }

        $msgs = $codec->feed($frames);

        self::assertCount(50, $msgs);
        foreach ($msgs as $i => $m) {
            $this->assertSimple($m, $texts[$i]);
        }
    }

    /** 4. Junk + header overlap of every possible length (1-3 bytes) **********/

    public function testOverlapPreservationForAllPrefixLengths(): void
    {
        $codec = $this->makeCodec();

        foreach ([1, 2, 3] as $prefixLen) {
            $codec = $this->makeCodec(); // fresh buffer every loop
            $junk = 'JUNK' . substr(FrameCodec::MAGIC, 0, $prefixLen);
            $msgs = $codec->feed($junk);
            self::assertCount(1, $msgs);
            self::assertInstanceOf(LogMessage::class, $msgs[0]);
            self::assertSame('JUNK', $msgs[0]->message);

            $frame = $codec->pack(new SimpleMessage("ok$prefixLen"));
            $msgs = $codec->feed(substr($frame, $prefixLen));

            self::assertCount(1, $msgs);
            $this->assertSimple($msgs[0], "ok$prefixLen");
        }
    }

    /** 5. Junk *between* two valid frames *************************************/

    public function testJunkBetweenFrames(): void
    {
        $codec = $this->makeCodec();
        $good1 = $codec->pack(new SimpleMessage('left'));
        $good2 = $codec->pack(new SimpleMessage('right'));
        $data = $good1 . 'garbage!' . $good2;

        $msgs = $codec->feed($data);

        self::assertCount(3, $msgs);
        $this->assertSimple($msgs[0], 'left');
        self::assertInstanceOf(LogMessage::class, $msgs[1]);
        self::assertSame('garbage!', $msgs[1]->message);
        $this->assertSimple($msgs[2], 'right');
    }

    /** 6. Frame length > maxFrame should raise immediately ********************/

    public function testFrameLengthExceedsMax(): void
    {
        $this->expectException(RuntimeException::class);

        $codec = $this->makeCodec(10); // 10-byte max
        $frame = $codec->pack(new SimpleMessage('too-long'));
        $codec->feed($frame);
    }

    /** 7. Deserializer throws → LogMessage produced ***************************/

    public function testDeserializeFailureLogsError(): void
    {
        $serializer = new class implements MessageSerializer {
            public function serialize(Message $data): string
            {
                return serialize($data);
            }

            public function deserialize(string $data): Message
            {
                throw new \LogicException('bad payload');
            }
        };

        $codec = new FrameCodec($serializer);
        $frame = $codec->pack(new SimpleMessage('irrelevant'));

        $msgs = $codec->feed($frame);

        self::assertCount(1, $msgs);
        self::assertInstanceOf(LogMessage::class, $msgs[0]);
    }

    /** 8. Feeding an empty string must be a no-op ******************************/

    public function testEmptyFeedReturnsNothing(): void
    {
        $codec = $this->makeCodec();
        $this->assertSame([], $codec->feed(''));
        self::assertFalse($codec->hasBufferedData());
    }

    /** 9. Header exactly at buffer end (partial header) ************************/

    public function testPartialHeaderAtBufferTail(): void
    {
        $codec = $this->makeCodec();

        // Send first 3 bytes of MAGIC only
        $part = substr(FrameCodec::MAGIC, 0, 3);
        $msgs = $codec->feed($part);
        self::assertSame([], $msgs);
        self::assertTrue($codec->hasBufferedData());

        // Complete the header + length + payload
        $frame = $codec->pack(new SimpleMessage('tail'));
        $msgs = $codec->feed(substr($frame, 3)); // remaining bytes

        self::assertCount(1, $msgs);
        $this->assertSimple($msgs[0], 'tail');
        self::assertFalse($codec->hasBufferedData());
    }
}
