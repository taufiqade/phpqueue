<?php

namespace Adsry\Tests\Unit\Utils;

use Adsry\Adapters\Redis\RedisMessage;
use Adsry\Utils\JsonSerializer;
use PHPUnit\Framework\TestCase;

class JsonSerializerTest extends TestCase
{
    /** @var JsonSerializer */
    private $serializer;

    protected function setUp(): void
    {
        $this->serializer = new JsonSerializer();
    }

    // --- toString ---

    public function testToStringEncodesBodyPropertiesAndHeaders()
    {
        $message = new RedisMessage('hello', ['key' => 'val'], ['h' => 1]);
        $data = json_decode($this->serializer->toString($message), true);

        $this->assertSame('hello', $data['body']);
        $this->assertSame(['key' => 'val'], $data['properties']);
        $this->assertSame(['h' => 1], $data['headers']);
    }

    public function testToStringThrowsOnNonUtf8Body()
    {
        $message = new RedisMessage("\x80\x81"); // invalid UTF-8
        $this->expectException(\InvalidArgumentException::class);
        $this->serializer->toString($message);
    }

    // --- toMessage ---

    public function testToMessageDeserializesAllFields()
    {
        $json = json_encode(['body' => 'world', 'properties' => ['p' => 2], 'headers' => ['h' => 3]]);
        $message = $this->serializer->toMessage($json);

        $this->assertInstanceOf(RedisMessage::class, $message);
        $this->assertSame('world', $message->getBody());
        $this->assertSame(['p' => 2], $message->getProperties());
        $this->assertSame(['h' => 3], $message->getHeaders());
    }

    /** C8: missing 'body' key must not fatal — defaults to empty string */
    public function testToMessageDefaultsMissingBodyToEmptyString()
    {
        $json = json_encode(['properties' => [], 'headers' => []]);
        $this->assertSame('', $this->serializer->toMessage($json)->getBody());
    }

    /** C8: missing 'properties' key must not fatal — defaults to empty array */
    public function testToMessageDefaultsMissingPropertiesToEmptyArray()
    {
        $json = json_encode(['body' => 'x', 'headers' => []]);
        $this->assertSame([], $this->serializer->toMessage($json)->getProperties());
    }

    /** C8: missing 'headers' key must not fatal — defaults to empty array */
    public function testToMessageDefaultsMissingHeadersToEmptyArray()
    {
        $json = json_encode(['body' => 'x', 'properties' => []]);
        $this->assertSame([], $this->serializer->toMessage($json)->getHeaders());
    }

    public function testToMessageThrowsOnInvalidJson()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->serializer->toMessage('not-json{{{');
    }

    public function testRoundTrip()
    {
        $original = new RedisMessage('test body', ['foo' => 'bar'], ['msg_id' => 'abc']);
        $restored = $this->serializer->toMessage($this->serializer->toString($original));

        $this->assertSame($original->getBody(), $restored->getBody());
        $this->assertSame($original->getProperties(), $restored->getProperties());
        $this->assertSame($original->getHeaders(), $restored->getHeaders());
    }
}
