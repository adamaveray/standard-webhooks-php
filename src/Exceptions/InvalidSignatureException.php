<?php
declare(strict_types=1);

namespace Averay\StandardWebhooks\Exceptions;

final class InvalidSignatureException extends \RuntimeException implements SignatureExceptionInterface
{
  public function __construct(
    public readonly string $signature,
    string $message = 'Invalid signature.',
    int $code = 0,
    ?\Throwable $previous = null,
  ) {
    parent::__construct($message, $code, $previous);
  }
}
