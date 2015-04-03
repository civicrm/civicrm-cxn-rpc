<?php
namespace Civi\Cxn\Rpc\Message;

use Civi\Cxn\Rpc\Exception\InvalidMessageException;
use Civi\Cxn\Rpc\Message;
use Civi\Cxn\Rpc\UserError;
use Civi\Cxn\Rpc\CxnStore\CxnStoreInterface;
use Civi\Cxn\Rpc\BinHex;
use Civi\Cxn\Rpc\Constants;
use Civi\Cxn\Rpc\Time;

class StdMessage extends Message {
  const NAME = 'CXN-0.2-AES-CBC-HMAC';

  /**
   * @return string
   *   A secret, expressed in a series of printable ASCII characters.
   */
  public static function createSecret() {
    return base64_encode(crypt_random_string(Constants::AES_BYTES));
  }

  protected $cxnId;
  protected $secret;

  /**
   * @param string $cxnId
   * @param string $secret
   *   Base64-encoded secret.
   * @param mixed $data
   *   Serializable data.
   */
  public function __construct($cxnId, $secret, $data) {
    parent::__construct($data);
    $this->cxnId = $cxnId;
    $this->secret = $secret;
  }

  /**
   * @return string
   * @throws InvalidMessageException
   */
  public function encode() {
    $iv = crypt_random_string(Constants::AES_BYTES);

    $keys = self::deriveAesKeys($this->secret);

    $cipher = new \Crypt_AES(CRYPT_AES_MODE_CBC);
    $cipher->setKeyLength(Constants::AES_BYTES);
    $cipher->setKey($keys['enc']);
    $cipher->setIV($iv);

    // JSON string; this will be signed but not encrypted
    $jsonEnvelope = json_encode(array(
      'ttl' => Time::getTime() + Constants::REQUEST_TTL,
      'iv' => BinHex::bin2hex($iv),
    ));
    // JSON string; this will be signed and encrypted
    $jsonEncrypted = $cipher->encrypt(json_encode($this->data));
    $body = $jsonEnvelope . Constants::PROTOCOL_DELIM . $jsonEncrypted;

    return self::NAME // unsignable; determines decoder
    . Constants::PROTOCOL_DELIM . $this->cxnId // unsignable; determines key
    . Constants::PROTOCOL_DELIM . hash_hmac('sha256', $body, $keys['auth'])
    . Constants::PROTOCOL_DELIM . $body;
  }

  /**
   * @param CxnStoreInterface $cxnStore
   *   A repository that contains shared secrets.
   * @param string $message
   *   Ciphertext.
   * @return static
   * @throws InvalidMessageException
   */
  public static function decode($cxnStore, $message) {
    list ($parsedProt, $parsedCxnId, $parsedHmac, $parsedBody) = explode(Constants::PROTOCOL_DELIM, $message, 4);
    if ($parsedProt != self::NAME) {
      throw new InvalidMessageException('Incorrect coding. Expected: ' . self::NAME);
    }
    $cxn = $cxnStore->getByCxnId($parsedCxnId);
    if (empty($cxn)) {
      throw new InvalidMessageException('Unknown connection ID');
    }

    $keys = self::deriveAesKeys($cxn['secret']);

    $localHmac = hash_hmac('sha256', $parsedBody, $keys['auth']);
    if (!self::hash_compare($parsedHmac, $localHmac)) {
      throw new InvalidMessageException("Incorrect hash");
    }

    list ($jsonEnvelope, $jsonEncrypted) = explode(Constants::PROTOCOL_DELIM, $parsedBody, 2);
    if (strlen($jsonEnvelope) > Constants::MAX_ENVELOPE_BYTES) {
      throw new InvalidMessageException("Oversized envelope");
    }

    $envelope = json_decode($jsonEnvelope, TRUE);
    if (!$envelope) {
      throw new InvalidMessageException("Malformed envelope");
    }

    if (!is_numeric($envelope['ttl']) || Time::getTime() > $envelope['ttl']) {
      throw new InvalidMessageException("Invalid TTL");
    }

    if (!is_string($envelope['iv']) || strlen($envelope['iv']) !== Constants::AES_BYTES*2 || !preg_match('/^[a-f0-9]+$/', $envelope['iv'])) {
      // AES_BYTES (32) ==> bin2hex ==> 2 hex digits (4-bit) per byte (8-bit)
      throw new InvalidMessageException("Malformed initialization vector");
    }

    $jsonPlaintext = UserError::adapt('Civi\Cxn\Rpc\Exception\InvalidMessageException', function () use ($jsonEncrypted, $envelope, $cxn, $keys) {
      $cipher = new \Crypt_AES(CRYPT_AES_MODE_CBC);
      $cipher->setKeyLength(Constants::AES_BYTES);
      $cipher->setKey($keys['enc']);
      $cipher->setIV(BinHex::hex2bin($envelope['iv']));
      return $cipher->decrypt($jsonEncrypted);
    });

    return new StdMessage($parsedCxnId, $cxn['secret'], json_decode($jsonPlaintext, TRUE));
  }

  /**
   * @param $secret
   *   A secret, expressed in a series of printable ASCII characters.
   * @return array
   *   - enc: string, raw encryption key
   *   - auth: string, raw authentication key
   */
  public static function deriveAesKeys($secret) {
    $rawSecret = base64_decode($secret);
    if (Constants::AES_BYTES != strlen($rawSecret)) {
      throw new InvalidMessageException("Failed to derive keys from secret.");
    }

    $result = array(
      'enc' => BinHex::hex2bin(hash_hmac('sha256', 'dearbrutus', $rawSecret)),
      'auth' => BinHex::hex2bin(hash_hmac('sha256', 'thefaultisinourselves', $rawSecret)),
    );
    if (Constants::AES_BYTES != strlen($result['enc']) || Constants::AES_BYTES != strlen($result['auth'])) {
      throw new InvalidMessageException("Failed to derive keys from secret.");
    }
    return $result;
  }

  /**
   * Comparison function which resists timing attacks.
   *
   * @param string $a
   * @param string $b
   * @return bool
   */
  private static function hash_compare($a, $b) {
    if (!is_string($a) || !is_string($b)) {
      return FALSE;
    }

    $len = strlen($a);
    if ($len !== strlen($b)) {
      return FALSE;
    }

    $status = 0;
    for ($i = 0; $i < $len; $i++) {
      $status |= ord($a[$i]) ^ ord($b[$i]);
    }
    return $status === 0;
  }

  /**
   * @return string
   */
  public function getCxnId() {
    return $this->cxnId;
  }

  /**
   * @param string $cxnId
   */
  public function setCxnId($cxnId) {
    $this->cxnId = $cxnId;
  }

  /**
   * @return string
   */
  public function getSecret() {
    return $this->secret;
  }

  /**
   * @param string $secret
   */
  public function setSecret($secret) {
    $this->secret = $secret;
  }

}
