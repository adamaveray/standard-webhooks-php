<?php
declare(strict_types=1);

namespace Averay\StandardWebhooks\Signing;

use Averay\StandardWebhooks\Exceptions\InvalidSignatureException;
use Averay\StandardWebhooks\Exceptions\NoMatchingSignatureException;
use Averay\StandardWebhooks\Exceptions\SignatureExceptionInterface;
use Averay\StandardWebhooks\WebhookInterface;
use ParagonIE\Halite\Asymmetric\Crypto;
use ParagonIE\Halite\Asymmetric\SignaturePublicKey as AsymmetricSignaturePublicKey;
use ParagonIE\Halite\Symmetric\SecretKey as SymmetricSecretKey;
use function Averay\StandardWebhooks\getTypeForKey;

final class Validator
{
  /** @var list<array{ type: SignatureType, key: AsymmetricSignaturePublicKey|SymmetricSecretKey }> */
  private array $signingKeys = [];

  /**
   * @param non-empty-list<AsymmetricSignaturePublicKey|SymmetricSecretKey> $keys
   */
  public function addKeys(#[\SensitiveParameter] array $keys): void
  {
    foreach ($keys as $key) {
      $this->signingKeys[] = ['type' => getTypeForKey($key), 'key' => $key];
    }
  }

  /**
   * @param list<string> $signatures
   * @throws SignatureExceptionInterface
   */
  public function validate(WebhookInterface $webhook, array $signatures): void
  {
    foreach ($signatures as $signature) {
      ['type' => $signatureType, 'value' => $signatureValue] = self::parseSignature($signature);

      // Test against each key
      foreach ($this->signingKeys as ['type' => $keyType, 'key' => $key]) {
        if ($keyType === $signatureType && self::testSignature($webhook, $key, $signatureType, $signatureValue)) {
          return;
        }
      }
    }

    throw new NoMatchingSignatureException($webhook, $signatures);
  }

  private static function testSignature(
    WebhookInterface $webhook,
    AsymmetricSignaturePublicKey|SymmetricSecretKey $key,
    SignatureType $type,
    string $signature,
  ): bool {
    switch ($type) {
      case SignatureType::V1Symmetric:
        if (!($key instanceof SymmetricSecretKey)) {
          // Wrong key type
          return false;
        }
        $expectedSignature = Signer::buildSignature($webhook, $type, $key);
        return \hash_equals($expectedSignature, $signature);

      case SignatureType::V1Asymmetric:
        if (!($key instanceof AsymmetricSignaturePublicKey)) {
          // Wrong key type
          return false;
        }
        $message = Signer::buildSignatureMessage($webhook);
        return Crypto::verify($message, $key, $signature);
    }
  }

  /**
   * @param string $signature
   * @return array{ type: SignatureType, value: string }
   */
  private static function parseSignature(string $signature): array
  {
    $parts = \explode(',', $signature, 2);
    if (\count($parts) !== 2) {
      throw new InvalidSignatureException($signature, 'Invalid signature format.');
    }
    $type =
      SignatureType::from($parts[0]) ?? throw new InvalidSignatureException($signature, 'Invalid signature type.');
    return ['type' => $type, 'value' => $parts[1]];
  }
}
