<?php
declare(strict_types=1);

namespace Averay\StandardWebhooks\Tests;

use Averay\StandardWebhooks\Exceptions\SignatureExceptionInterface;
use Averay\StandardWebhooks\SignedWebhookInterface;
use Averay\StandardWebhooks\Tests\Utils\TestCase;
use Averay\StandardWebhooks\Transmission\RequestBuilder;
use Averay\StandardWebhooks\Transmission\RequestParser;
use Averay\StandardWebhooks\Webhook;
use ParagonIE\Halite\Asymmetric\SignatureSecretKey as AsymmetricSignatureSecretKey;
use ParagonIE\Halite\Asymmetric\SignaturePublicKey as AsymmetricSignaturePublicKey;
use ParagonIE\Halite\KeyFactory;
use ParagonIE\Halite\Symmetric\SecretKey as SymmetricSecretKey;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversNothing]
final class GeneralTest extends TestCase
{
  #[DataProvider('endToEndDataProvider')]
  public function testEndToEnd(
    AsymmetricSignatureSecretKey|SymmetricSecretKey $signingKey,
    AsymmetricSignaturePublicKey|SymmetricSecretKey $verifyingKey,
  ): void {
    $now = new \DateTimeImmutable('2025-01-02T12:00:00+00:00');
    $timestampTolerance = new \DateInterval('PT2S');

    $id = 'abc123';
    $timestamp = $now->add(new \DateInterval('PT1S'));
    $data = ['hello' => 'world'];
    $metadata = [];

    // Encode & transmit
    $outboundWebhook = Webhook::create($id, $timestamp, $data, $metadata);
    $builder = (new RequestBuilder())->withKey($signingKey);
    $outboundHeaders = $builder->buildHeaders($outboundWebhook);
    $outboundBody = $builder->buildBody($outboundWebhook);
    self::assertArrayHasKeys(
      ['webhook-id', 'webhook-timestamp', 'webhook-signature'],
      $outboundHeaders,
      'The required headers should be set.',
    );

    // Receive & decode
    $parser = (new RequestParser($timestampTolerance))->withKey($verifyingKey);
    $request = self::createStubRequest($outboundHeaders, $outboundBody);
    [
      'data' => $inboundData,
      'metadata' => $inboundMetadata,
      'webhook' => $inboundWebhook,
    ] = $parser->parse($request, now: $now);

    self::assertInstanceOf(SignedWebhookInterface::class, $inboundWebhook, 'The inbound webhook should be signed.');

    self::assertEquals($outboundWebhook->id, $inboundWebhook->id, 'The webhook IDs should match.');
    self::assertEquals($outboundWebhook->timestamp, $inboundWebhook->timestamp, 'The webhook timestamps should match.');
    self::assertEquals($outboundWebhook->json, $inboundWebhook->json, 'The webhook JSON bodies should match.');

    self::assertEquals($data, $inboundData, 'The webhook data should match.');
    self::assertEquals($metadata, $inboundMetadata, 'The webhook metadata should match.');
  }

  public static function endToEndDataProvider(): iterable
  {
    $symmetricKey = KeyFactory::generateAuthenticationKey();
    yield 'Symmetric key' => [
      'signingKey' => $symmetricKey,
      'verifyingKey' => $symmetricKey,
    ];

    $keypair = KeyFactory::generateSignatureKeyPair();
    yield 'Asymmetric key' => [
      'signingKey' => $keypair->getSecretKey(),
      'verifyingKey' => $keypair->getPublicKey(),
    ];
  }

  #[DataProvider('differingKeysFailureDataProvider')]
  public function testDifferingKeysFailure(
    AsymmetricSignatureSecretKey|SymmetricSecretKey $signingKey,
    AsymmetricSignaturePublicKey|SymmetricSecretKey $verifyingKey,
  ): void {
    $this->expectException(SignatureExceptionInterface::class);
    $this->testEndToEnd($signingKey, $verifyingKey);
  }

  public static function differingKeysFailureDataProvider(): iterable
  {
    yield 'Symmetric keys' => [
      'signingKey' => KeyFactory::generateAuthenticationKey(),
      'verifyingKey' => KeyFactory::generateAuthenticationKey(),
    ];

    yield 'Asymmetric keys' => [
      'signingKey' => KeyFactory::generateSignatureKeyPair()->getSecretKey(),
      'verifyingKey' => KeyFactory::generateSignatureKeyPair()->getPublicKey(),
    ];
  }
}
