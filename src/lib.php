<?php
declare(strict_types=1);

namespace Averay\StandardWebhooks;

use ParagonIE\Halite\Asymmetric\PublicKey as AsymmetricPublicKey;
use ParagonIE\Halite\Asymmetric\SecretKey as AsymmetricSecretKey;
use ParagonIE\Halite\Symmetric\SecretKey as SymmetricSecretKey;
use Averay\StandardWebhooks\Signing\SignatureType;

/**
 * @internal
 * @return SignatureType
 */
function getTypeForKey(
  #[\SensitiveParameter] AsymmetricPublicKey|AsymmetricSecretKey|SymmetricSecretKey $key,
): SignatureType {
  return match (true) {
    $key instanceof AsymmetricPublicKey, $key instanceof AsymmetricSecretKey => SignatureType::V1Asymmetric,
    $key instanceof SymmetricSecretKey => SignatureType::V1Symmetric,
  };
}
