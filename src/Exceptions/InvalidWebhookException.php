<?php
declare(strict_types=1);

namespace Averay\StandardWebhooks\Exceptions;

final class InvalidWebhookException extends \RuntimeException implements WebhookExceptionInterface
{
  public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
  {
    parent::__construct($message, $code, $previous);
  }
}
