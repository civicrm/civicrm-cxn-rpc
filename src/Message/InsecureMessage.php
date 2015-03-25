<?php
namespace Civi\Cxn\Rpc\Message;

use Civi\Cxn\Rpc\Exception\InvalidMessageException;
use Civi\Cxn\Rpc\Constants;

class InsecureMessage {
  const NAME = 'CXN-0.2-INSECURE';

  public static function encode($data) {
    return self::NAME . Constants::PROTOCOL_DELIM . json_encode($data);
  }

  /**
   * @param string $message
   * @return array
   * @throws InvalidMessageException
   */
  public static function decode($message) {
    list ($parsedProt, $parsedJson) = explode(Constants::PROTOCOL_DELIM, $message, 2);
    if ($parsedProt != self::NAME) {
      throw new InvalidMessageException('Incorrect coding. Expected: ' . self::PREFIX_AES);
    }
    $data = json_decode($parsedJson, TRUE);
    if (!$data) {
      throw new InvalidMessageException("Invalid message");
    }
    return $data;
  }
}
