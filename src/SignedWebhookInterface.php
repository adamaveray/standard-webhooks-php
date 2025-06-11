<?php
declare(strict_types=1);

namespace Averay\StandardWebhooks;

interface SignedWebhookInterface extends WebhookInterface
{
  /** @var list<string> $signatures */
  public array $signatures { get; }
}
