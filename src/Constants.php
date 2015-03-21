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
  const AGENT_ID_MIN = 16;

}
