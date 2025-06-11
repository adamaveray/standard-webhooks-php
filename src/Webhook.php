<?php
declare(strict_types=1);

namespace Averay\StandardWebhooks;

readonly class Webhook implements WebhookInterface
{
  public function __construct(
    public string $type,
    public string $id,
    public \DateTimeInterface $timestamp,
    public string $json,
  ) {}

  /**
   * @param array<string, mixed> $data
   * @param array<string, mixed> $metadata
   */
  public static function create(
    string $type,
    string $id,
    \DateTimeInterface $timestamp,
    array $data,
    array $metadata = [],
    bool $prettyJson = false,
  ): static {
    return new self($type, $id, $timestamp, self::buildJson($type, $timestamp, $data, $metadata, $prettyJson));
  }

  /**
   * @param array<string, mixed> $data
   * @param array<string, mixed> $metadata
   */
  final protected static function buildJson(
    string $type,
    \DateTimeInterface $timestamp,
    array $data,
    array $metadata = [],
    bool $prettyJson = false,
  ): string {
    $body =
      [
        'type' => $type,
        'timestamp' => self::encodeTimestamp($timestamp),
        'data' => $data,
      ] + $metadata;
    return \json_encode($body, \JSON_THROW_ON_ERROR | ($prettyJson ? \JSON_PRETTY_PRINT : 0));
  }

  private static function encodeTimestamp(\DateTimeInterface $dateTime): string
  {
    return \DateTimeImmutable::createFromInterface($dateTime)
      ->setTimezone(new \DateTimeZone('UTC'))
      ->format(\DateTimeInterface::RFC3339_EXTENDED);
  }
}
