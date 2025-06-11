<?php
declare(strict_types=1);

namespace Averay\StandardWebhooks\Signing;

enum SignatureType: string
{
  case V1Asymmetric = 'v1a';
  case V1Symmetric = 'v1';
}
