<?php
declare(strict_types=1);

namespace Averay\StandardWebhooks\Transmission;

use Averay\StandardWebhooks\Exceptions\InvalidTimestampException;
use Averay\StandardWebhooks\Exceptions\InvalidWebhookException;
use Averay\StandardWebhooks\SignedWebhook;
use Averay\StandardWebhooks\Signing\Validator as SigningValidator;
use Averay\StandardWebhooks\Webhook;
use Averay\StandardWebhooks\WebhookInterface;
use ParagonIE\Halite\Asymmetric\SignaturePublicKey as AsymmetricSignaturePublicKey;
use ParagonIE\Halite\Symmetric\SecretKey as SymmetricSecretKey;
use Psr\Http\Message\ServerRequestInterface;

final readonly class RequestParser
{
  private SigningValidator $signingValidator;

  /**
   * @param \DateInterval|null $timestampTolerance A duration to consider received webhooks' timestamps valid if falling within (past and future).
   */
  public function __construct(private ?\DateInterval $timestampTolerance = new \DateInterval('PT30S'))
  {
    $this->signingValidator = new SigningValidator();
  }

  /**
   * @param non-empty-list<AsymmetricSignaturePublicKey|SymmetricSecretKey> $keys
   */
  public function withKeys(#[\SensitiveParameter] array $keys): static
  {
    $this->signingValidator->addKeys($keys);
    return $this;
  }

  public function withKey(AsymmetricSignaturePublicKey|SymmetricSecretKey $key): static
  {
    return $this->withKeys([$key]);
  }

  /**
   * @return array{
   *   id: string,
   *   data: array<string, mixed>,
   *   metadata: array<string, mixed>,
   *   webhook: WebhookInterface,
   * }
   */
  public function parse(
    ServerRequestInterface $request,
    bool $requireSignatures = true,
    \DateTimeInterface $now = new \DateTimeImmutable(),
  ): array {
    try {
      [
        'id' => $id,
        'timestamp' => $timestamp,
        'signatures' => $signatures,
      ] = self::parseHeaders($request);
    } catch (\Throwable $exception) {
      throw new InvalidWebhookException('Invalid headers.', previous: $exception);
    }

    try {
      $json = $request->getBody()->getContents();
      $body = self::parseBodyContent($json, $request);
    } catch (\Throwable $exception) {
      throw new InvalidWebhookException('Invalid body.', previous: $exception);
    }

    if ($body['id'] !== $id || $body['timestamp']->getTimestamp() !== $timestamp->getTimestamp()) {
      throw new InvalidWebhookException('Webhook metadata does not match.');
    }

    $metadata = $body;
    unset($metadata['id'], $metadata['timestamp'], $metadata['data']);
    $data = $body['data'] ?? null;

    $webhook = new Webhook($id, $timestamp, $json);

    // Validate timestamp
    try {
      $this->validateTimestamp($timestamp, $now);
    } catch (\Throwable $exception) {
      throw new InvalidTimestampException($timestamp, previous: $exception);
    }

    // Process signatures
    if (!empty($signatures)) {
      $this->signingValidator->validate($webhook, $signatures);
      $webhook = SignedWebhook::createFromUnsigned($webhook, $signatures);
    } elseif ($requireSignatures) {
      throw new \UnexpectedValueException('No signatures found.');
    }

    return [
      'id' => $id,
      'data' => $data,
      'metadata' => $metadata,
      'webhook' => $webhook,
    ];
  }

  private function validateTimestamp(\DateTimeInterface $timestamp, \DateTimeInterface $now): void
  {
    $now = \DateTimeImmutable::createFromInterface($now);
    if ($timestamp < $now->sub($this->timestampTolerance) || $timestamp > $now->add($this->timestampTolerance)) {
      throw new \UnexpectedValueException('Invalid timestamp.');
    }
  }

  /**
   * @param ServerRequestInterface $request
   * @return array{ id: string, timestamp: \DateTimeInterface, signatures: list<string>|null }
   */
  private static function parseHeaders(ServerRequestInterface $request): array
  {
    $id =
      self::getSingleHeader($request, 'webhook-id') ??
      throw new \UnexpectedValueException('No "webhook-id" header found.');
    $timestamp =
      self::getSingleHeader($request, 'webhook-timestamp') ??
      throw new \UnexpectedValueException('No "webhook-timestamp" header found.');

    if (!\preg_match('~^\d{10,11}$~', $timestamp)) {
      throw new \UnexpectedValueException('Invalid "webhook-timestamp" header.');
    }
    $timestamp = \DateTimeImmutable::createFromTimestamp((int) $timestamp);

    $rawSignatures = self::getSingleHeader($request, 'webhook-signature') ?? '';
    $signatures = $rawSignatures === '' ? null : \explode(' ', $rawSignatures);

    return [
      'id' => $id,
      'timestamp' => $timestamp,
      'signatures' => $signatures,
    ];
  }

  /**
   * @return array{ id: string, timestamp: \DateTimeInterface, data: array<string, mixed> }
   */
  private static function parseBodyContent(string $json, ServerRequestInterface $request): array
  {
    $contentType = \explode(
      ';',
      self::getSingleHeader($request, 'content-type') ??
        throw new \UnexpectedValueException('No "content-type" header found.'),
      2,
    )[0];
    if ($contentType !== 'application/json') {
      throw new \UnexpectedValueException('Only "application/json" content-type is supported.');
    }

    $body = \json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
    if (
      !isset($body['id']) ||
      !isset($body['timestamp']) ||
      !isset($body['data']) ||
      !\is_string($body['id']) ||
      !\is_string($body['timestamp']) ||
      !\is_array($body['data'])
    ) {
      throw new \UnexpectedValueException('Invalid webhook body.');
    }
    $parsedTimestamp = \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC3339_EXTENDED, $body['timestamp']);
    if ($parsedTimestamp === false) {
      throw new \UnexpectedValueException('Invalid "timestamp" in webhook body.');
    }
    $body['timestamp'] = $parsedTimestamp;
    return $body;
  }

  private static function getSingleHeader(ServerRequestInterface $request, string $header): ?string
  {
    $values = $request->getHeader($header);
    if (\count($values) > 1) {
      throw new \InvalidArgumentException(\sprintf('Only one "%s" header is supported.', $header));
    }
    return $values[0] ?? null;
  }
}
