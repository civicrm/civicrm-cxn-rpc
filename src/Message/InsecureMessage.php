<?php
namespace Civi\Cxn\Rpc\Message;

use Civi\Cxn\Rpc\Exception\InvalidMessageException;
use Civi\Cxn\Rpc\Constants;
use Civi\Cxn\Rpc\Message;

class InsecureMessage extends Message {
  const NAME = 'CXN-0.2-INSECURE';

  /**
   * @return string
   */
  public function encode() {
    return self::NAME . Constants::PROTOCOL_DELIM . json_encode($this->data);
  }

  /**
   * @param string $message
   * @return array
   * @throws InvalidMessageException
   */
  public static function decode($message) {
    list ($parsedProt, $parsedJson) = explode(Constants::PROTOCOL_DELIM, $message, 2);
    if ($parsedProt != self::NAME) {
      throw new InvalidMessageException('Incorrect coding. Expected: ' . self::NAME);
    }
    $data = json_decode($parsedJson, TRUE);
    if (!$data) {
      throw new InvalidMessageException("Invalid message");
    }
    return $data;
  }

}
