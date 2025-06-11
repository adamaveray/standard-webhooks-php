<?php
declare(strict_types=1);

namespace Averay\StandardWebhooks;

final readonly class SignedWebhook extends Webhook implements SignedWebhookInterface
{
  /**
   * @param list<string> $signatures
   */
  public function __construct(
    string $type,
    string $id,
    \DateTimeInterface $timestamp,
    string $json,
    public array $signatures,
  ) {
    parent::__construct($type, $id, $timestamp, $json);
  }

  /**
   * @param array<string, mixed> $data
   * @param array<string, mixed> $metadata
   * @param list<string> $signatures
   */
  #[\Override]
  public static function create(
    string $type,
    string $id,
    \DateTimeInterface $timestamp,
    array $data,
    array $metadata = [],
    bool $prettyJson = false,
    array $signatures = [],
  ): static {
    return new self($type, $id, $timestamp, self::buildJson($type, $timestamp, $data), $signatures);
  }

  public static function createFromUnsigned(WebhookInterface $webhook, array $signatures): static
  {
    return new self($webhook->type, $webhook->id, $webhook->timestamp, $webhook->json, $signatures);
  }
}
