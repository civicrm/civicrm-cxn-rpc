<?php

/*
 * This file is part of the civicrm-cxn-rpc package.
 *
 * Copyright (c) CiviCRM LLC <info@civicrm.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this package.
 */

namespace Civi\Cxn\Rpc;

/**
 * Class Constants
 *
 * These values have been represented as constants for simplicity. At some point, it
 * may be desirable to convert them to configuration options.
 *
 * @package Civi\Cxn\Rpc
 */
class Constants {
  /**
   * Number of seconds during which a signed request is considered valid.
   */
  const REQUEST_TTL = 7200;

  /**
   * Number of characters in an agent ID.
   */
  const APP_ID_CHARS = 16;

  const RSA_ENC_MODE = \phpseclib\Crypt\RSA::ENCRYPTION_OAEP;

  const RSA_HASH = 'sha256';

  const RSA_SIG_MODE = \phpseclib\Crypt\RSA::SIGNATURE_PSS;

  const RSA_KEYLEN = 2048;

  // 2048 bits / 8 bits-per-byte = 256 bytes
  const RSA_MSG_BYTES = 256;

  const CERT_SIGNATURE_ALGORITHM = 'sha256WithRSAEncryption';

  const MIME_TYPE = 'application/x-civi-cxn';

  // ^A, not visible in some editors
  const PROTOCOL_DELIM = "";

  const CA_DURATION = '+10 years';

  const APP_DURATION = '+1 year';

  // 32 bytes = 256 bits
  const AES_BYTES = 32;

  const CXN_ID_CHARS = 16;

  // (TTL (10byte) + IV (32byte)) * leeway-for-json-inefficency (10x) =~ 512
  const MAX_ENVELOPE_BYTES = 512;

  /**
   * We only trust metadata lists generated by cxn.civicrm.org. This implementation is a bit
   * ham-handed, but it's simple. Ideally, we might have a special "usage" flag in the cert.
   */
  const OFFICIAL_APPMETAS_CN = 'core:DirectoryService';

  const OFFICIAL_APPMETAS_URL = 'https://cxn.civicrm.org/cxn/apps';

  /**
   * @return string
   *   The path to the PEM-encode X.509 certificate of the
   *   live CiviCRM Certificate Authority.
   */
  public static function getCert() {
    return dirname(__DIR__) . '/certs/CiviConnectRootCA.crt';
  }

  /**
   * @return string
   *   The path to the PEM-encode X.509 certificate of the
   *   test CiviCRM Certificate Authority.
   */
  public static function getTestCert() {
    return dirname(__DIR__) . '/certs/CiviTestRootCA.crt';
  }

  /**
   * @return string
   *   The path to the PEM-encode X.509 certificate of the
   *   live CiviCRM Certificate Authority.
   */
  public static function getOldCert() {
    return dirname(__DIR__) . '/certs/CiviRootCA.crt';
  }

}
