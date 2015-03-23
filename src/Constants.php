<?php

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

  const RSA_ENC_MODE = CRYPT_RSA_ENCRYPTION_OAEP;

  const RSA_HASH = 'sha256';

  const RSA_SIG_MODE = CRYPT_RSA_SIGNATURE_PSS;

  const RSA_KEYLEN = 2048;

  const MIME_TYPE = 'application/x-civi-cxn';

  const PROTOCOL_VERSION = 'CXN-0.1';

  const PROTOCOL_DELIM = "";

  const CA_DURATION = '+10 years';

  const APP_DURATION = '+1 year';

  const AES_CHARS = 32; // todo: verify

  const CXN_ID_CHARS = 16;

}
