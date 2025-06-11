<?php
declare(strict_types=1);

namespace Averay\StandardWebhooks\Transmission;

use Averay\StandardWebhooks\Signing\Signer;
use Averay\StandardWebhooks\WebhookInterface;
use ParagonIE\Halite\Asymmetric\SignatureSecretKey as AsymmetricSignatureSecretKey;
use ParagonIE\Halite\Symmetric\SecretKey as SymmetricSecretKey;

final readonly class RequestBuilder
{
  private Signer $signer;

  public function __construct()
  {
    $this->signer = new Signer();
  }

  /**
   * @param non-empty-list<AsymmetricSignatureSecretKey|SymmetricSecretKey> $keys
   */
  public function withKeys(#[\SensitiveParameter] array $keys): static
  {
    $this->signer->addKeys($keys);
    return $this;
  }

  public function withKey(AsymmetricSignatureSecretKey|SymmetricSecretKey $key): static
  {
    return $this->withKeys([$key]);
  }

  /**
   * @return array<string, string>
   */
  public function buildHeaders(WebhookInterface $webhook): array
  {
    $headers = [
      'content-type' => 'application/json; charset=utf-8',
      'content-length' => (string) \strlen($webhook->json),
      'webhook-id' => $webhook->id,
      'webhook-timestamp' => (string) $webhook->timestamp->getTimestamp(),
    ];
    if ($this->signer->hasKeys()) {
      $headers['webhook-signature'] = \implode(' ', $this->signer->buildSignatures($webhook));
    }
    return $headers;
  }

  public function buildBody(WebhookInterface $webhook): string
  {
    return $webhook->json;
  }
}
