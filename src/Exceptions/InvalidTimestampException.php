<?php
declare(strict_types=1);

namespace Averay\StandardWebhooks\Exceptions;

final class InvalidTimestampException extends \RuntimeException implements ValidationExceptionInterface
{
  public function __construct(
    public readonly \DateTimeInterface $timestamp,
    string $message = '',
    int $code = 0,
    ?\Throwable $previous = null,
  ) {
    parent::__construct($message, $code, $previous);
  }
}
