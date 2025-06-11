<?php
declare(strict_types=1);

namespace Averay\StandardWebhooks;

interface WebhookInterface
{
  public string $id { get; }
  public \DateTimeInterface $timestamp { get;}
  public string $json { get; }
}
