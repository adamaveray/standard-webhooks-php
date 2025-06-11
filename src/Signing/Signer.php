<?php
declare(strict_types=1);

namespace Averay\StandardWebhooks\Signing;

use Averay\StandardWebhooks\WebhookInterface;
use ParagonIE\Halite\Asymmetric\Crypto as AsymmetricCrypto;
use ParagonIE\Halite\Asymmetric\SignatureSecretKey as AsymmetricSignatureSecretKey;
use ParagonIE\Halite\Symmetric\SecretKey as SymmetricSecretKey;
use function Averay\StandardWebhooks\getTypeForKey;

final class Signer
{
  /** @var list<array{ type: SignatureType, key: AsymmetricSignatureSecretKey|SymmetricSecretKey }> */
  private array $signingKeys = [];

  public function hasKeys(): bool
  {
    return !empty($this->signingKeys);
  }

  /**
   * @param non-empty-list<AsymmetricSignatureSecretKey|SymmetricSecretKey> $keys
   */
  public function addKeys(#[\SensitiveParameter] array $keys): void
  {
    foreach ($keys as $key) {
      if (!$key->isSigningKey()) {
        throw new \InvalidArgumentException('Key must be a signing key.');
      }
      $this->signingKeys[] = ['type' => getTypeForKey($key), 'key' => $key];
    }
  }

  /**
   * @return list<string>
   */
  public function buildSignatures(WebhookInterface $webhook): array
  {
    if (empty($this->signingKeys)) {
      throw new \RuntimeException('No signing keys provided.');
    }

    return \array_map(
      static fn(array $key) => $key['type']->value . ',' . self::buildSignature($webhook, $key['type'], $key['key']),
      $this->signingKeys,
    );
  }

  public static function buildSignatureMessage(WebhookInterface $webhook): string
  {
    $id = $webhook->id;
    if (\str_contains($id, '.')) {
      throw new \UnexpectedValueException('Signed webhook IDs cannot contain periods.');
    }
    return $webhook->id . '.' . $webhook->timestamp->getTimestamp() . '.' . $webhook->json;
  }

  public static function buildSignature(
    WebhookInterface $webhook,
    SignatureType $type,
    #[\SensitiveParameter] AsymmetricSignatureSecretKey|SymmetricSecretKey $key,
  ): string {
    $message = self::buildSignatureMessage($webhook);
    return match ($type) {
      SignatureType::V1Asymmetric => self::buildAsymmetricSignature($message, $key),
      SignatureType::V1Symmetric => self::buildSymmetricSignature($message, $key),
    };
  }

  private static function buildAsymmetricSignature(
    string $message,
    #[\SensitiveParameter] AsymmetricSignatureSecretKey|SymmetricSecretKey $key,
  ): string {
    \assert($key instanceof AsymmetricSignatureSecretKey && $key->isSigningKey(), 'Invalid key.');
    return AsymmetricCrypto::sign($message, $key);
  }

  private static function buildSymmetricSignature(
    string $message,
    #[\SensitiveParameter] AsymmetricSignatureSecretKey|SymmetricSecretKey $key,
  ): string {
    \assert($key instanceof SymmetricSecretKey && $key->isSigningKey(), 'Invalid key.');
    return \base64_encode(\hash_hmac('sha256', $message, $key->getRawKeyMaterial(), true));
  }
}
