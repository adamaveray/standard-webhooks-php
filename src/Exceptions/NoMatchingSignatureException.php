<?php
declare(strict_types=1);

namespace Averay\StandardWebhooks\Exceptions;

use Averay\StandardWebhooks\WebhookInterface;

final class NoMatchingSignatureException extends \RuntimeException implements SignatureExceptionInterface
{
  public function __construct(
    public readonly WebhookInterface $webhook,
    public readonly array $signatures,
    string $message = 'No matching signature found.',
    int $code = 0,
    ?\Throwable $previous = null,
  ) {
    parent::__construct($message, $code, $previous);
  }
}
