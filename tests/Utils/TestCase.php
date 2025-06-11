<?php
declare(strict_types=1);

namespace Averay\StandardWebhooks\Tests\Utils;

use PHPUnit\Framework\MockObject\Stub;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
  final protected static function createStubRequest(array $headers, string $body): ServerRequestInterface&Stub
  {
    $mock = static::createStub(ServerRequestInterface::class);
    $mock->method('getHeaders')->willReturn($headers);
    $mock->method('getHeader')->willReturnCallback(static fn(string $name): array => [$headers[$name]] ?? []);
    $mock->method('getBody')->willReturn(self::createStubStream($body));
    return $mock;
  }

  private static function createStubStream(string $body): StreamInterface&Stub
  {
    $mock = static::createStub(StreamInterface::class);
    $mock->method('getContents')->willReturn($body);
    return $mock;
  }

  final protected static function assertArrayHasKeys(array $expectedKeys, array $array, string $message = ''): void
  {
    foreach ($expectedKeys as $expectedKey) {
      self::assertArrayHasKey($expectedKey, $array, $message);
    }
  }
}
