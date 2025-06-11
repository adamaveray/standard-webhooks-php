<?php
declare(strict_types=1);

namespace Averay\StandardWebhooks\Tests\Signing;

use Averay\StandardWebhooks\Exceptions\SignatureExceptionInterface;
use Averay\StandardWebhooks\Signing\SignatureType;
use Averay\StandardWebhooks\Signing\Signer;
use Averay\StandardWebhooks\Signing\Validator;
use Averay\StandardWebhooks\Tests\Utils\TestCase;
use Averay\StandardWebhooks\Webhook;
use Averay\StandardWebhooks\WebhookInterface;
use ParagonIE\Halite\Asymmetric\SignaturePublicKey as AsymmetricSignaturePublicKey;
use ParagonIE\Halite\Asymmetric\SignatureSecretKey as AsymmetricSignatureSecretKey;
use ParagonIE\Halite\KeyFactory;
use ParagonIE\Halite\Symmetric\SecretKey as SymmetricSecretKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;

#[CoversClass(Validator::class)]
final class ValidatorTest extends TestCase
{
  #[DataProvider('validateSuccessDataProvider')]
  #[DoesNotPerformAssertions]
  public function testValidateSuccess(
    WebhookInterface $webhook,
    string $signature,
    AsymmetricSignaturePublicKey|SymmetricSecretKey $key,
  ): void {
    $validator = new Validator();
    $validator->addKeys([$key]);
    $validator->validate($webhook, [$signature]);
  }

  public static function validateSuccessDataProvider(): iterable
  {
    $webhook = Webhook::create('test-webhook', 'abc123', new \DateTimeImmutable('2025-01-01T00:00:00+00:00'), [
      'hello' => 'world',
    ]);

    $asymmetricKeypair = KeyFactory::generateSignatureKeyPair();
    yield 'Asymmetric' => [
      'webhook' => $webhook,
      'signature' => self::buildSignature($webhook, SignatureType::V1Asymmetric, $asymmetricKeypair->getSecretKey()),
      'key' => $asymmetricKeypair->getPublicKey(),
    ];

    $symmetricKey = KeyFactory::generateAuthenticationKey();
    yield 'Symmetric' => [
      'webhook' => $webhook,
      'signature' => self::buildSignature($webhook, SignatureType::V1Symmetric, $symmetricKey),
      'key' => $symmetricKey,
    ];
  }

  #[DataProvider('validateWrongKeyDataProvider')]
  public function testValidateWrongKey(
    WebhookInterface $webhook,
    string $signature,
    AsymmetricSignaturePublicKey|SymmetricSecretKey $key,
  ): void {
    $validator = new Validator();
    $validator->addKeys([$key]);

    $this->expectException(SignatureExceptionInterface::class);
    $validator->validate($webhook, [$signature]);
  }

  public static function validateWrongKeyDataProvider(): iterable
  {
    $webhook = Webhook::create('test-webhook', 'abc123', new \DateTimeImmutable('2025-01-01T00:00:00+00:00'), [
      'hello' => 'world',
    ]);

    yield 'Asymmetric' => [
      'webhook' => $webhook,
      'signature' => self::buildSignature(
        $webhook,
        SignatureType::V1Asymmetric,
        KeyFactory::generateSignatureKeyPair()->getSecretKey(),
      ),
      'key' => KeyFactory::generateSignatureKeyPair()->getPublicKey(),
    ];

    yield 'Symmetric' => [
      'webhook' => $webhook,
      'signature' => self::buildSignature(
        $webhook,
        SignatureType::V1Symmetric,
        KeyFactory::generateAuthenticationKey(),
      ),
      'key' => KeyFactory::generateAuthenticationKey(),
    ];
  }

  #[DataProvider('validateWrongSignatureDataProvider')]
  public function testValidateWrongSignature(
    WebhookInterface $webhook,
    string $signature,
    AsymmetricSignaturePublicKey|SymmetricSecretKey $key,
  ): void {
    $validator = new Validator();
    $validator->addKeys([$key]);

    $this->expectException(SignatureExceptionInterface::class);
    $validator->validate($webhook, [$signature]);
  }

  public static function validateWrongSignatureDataProvider(): iterable
  {
    $webhook = Webhook::create('test-webhook', 'abc123', new \DateTimeImmutable('2025-01-01T00:00:00+00:00'), [
      'hello' => 'world',
    ]);

    $alternateWebhook = new Webhook('test-webhook', 'xyz987', $webhook->timestamp, $webhook->json);

    $asymmetricKeypair = KeyFactory::generateSignatureKeyPair();
    yield 'Asymmetric' => [
      'webhook' => $webhook,
      'signature' => self::buildSignature(
        $alternateWebhook,
        SignatureType::V1Asymmetric,
        $asymmetricKeypair->getSecretKey(),
      ),
      'key' => $asymmetricKeypair->getPublicKey(),
    ];

    $symmetricKey = KeyFactory::generateAuthenticationKey();
    yield 'Symmetric' => [
      'webhook' => $webhook,
      'signature' => self::buildSignature($alternateWebhook, SignatureType::V1Symmetric, $symmetricKey),
      'key' => $symmetricKey,
    ];
  }

  private static function buildSignature(
    WebhookInterface $webhook,
    SignatureType $type,
    #[\SensitiveParameter] AsymmetricSignatureSecretKey|SymmetricSecretKey $key,
  ): string {
    return $type->value . ',' . Signer::buildSignature($webhook, $type, $key);
  }
}
