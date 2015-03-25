<?php
namespace Civi\Cxn\Rpc;

use Civi\Cxn\Rpc\Exception\InvalidMessageException;
use Civi\Cxn\Rpc\Exception\UserErrorException;

class Message {

  /**
   * @return string
   *   A secret, expressed in a series of printable ASCII characters.
   */
  public static function createSecret() {
    return base64_encode(crypt_random_string(Constants::AES_BYTES));
  }

  /**
   * @param $secret
   *   A secret, expressed in a series of printable ASCII characters.
   * @return array
   *   - enc: string, raw encryption key
   *   - auth: string, raw authentication key
   */
  protected static function deriveAesKeys($secret) {
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

  protected static function getRsa($key, $type) {
    $rsa = new \Crypt_RSA();
    $rsa->loadKey($key);
    if ($type == 'public') {
      $rsa->setPublicKey();
    }
    $rsa->setEncryptionMode(Constants::RSA_ENC_MODE);
    $rsa->setSignatureMode(Constants::RSA_SIG_MODE);
    $rsa->setHash(Constants::RSA_HASH);
    return $rsa;
  }

  /**
   * @param string $appId
   * @param string $appPubKey
   * @param array $data
   * @return string
   *   Ciphertext.
   */
  public static function encodeCxn02Registration($appId, $appPubKey, $data) {
    $envelope = array(
      'ttl' => Time::getTime() + Constants::REQUEST_TTL,
      'r' => json_encode($data),
    );
    return 'CXN-0.2-RSA' . Constants::PROTOCOL_DELIM
    . $appId . Constants::PROTOCOL_DELIM
    . self::getRsa($appPubKey, 'public')->encrypt(json_encode($envelope));
  }

  /**
   * @param $appId
   * @param $appPrivKey
   * @param $blob
   * @return array
   *   Decoded data.
   */
  public static function decodeCxn02Registration($appId, $appPrivKey, $blob) {
    list ($wireProt, $wireAppId, $ciphertext) = explode(Constants::PROTOCOL_DELIM, $blob, 3);
    if ($wireProt != 'CXN-0.2-RSA') {
      throw new \RuntimeException('Incorrect coding. Expected CXN-0.2-RSA');
    }
    if ($wireAppId != $appId) {
      throw new \RuntimeException('Received message intended for incorrect app.');
    }
    $plaintext = UserErrorException::adapt(function () use ($ciphertext, $appPrivKey) {
      return self::getRsa($appPrivKey, 'private')->decrypt($ciphertext);
    });
    if (empty($plaintext)) {
      throw new InvalidMessageException("Invalid request: decryption produced empty message");
    }
    $envelope = json_decode($plaintext, TRUE);
    if (Time::getTime() > $envelope['ttl']) {
      throw new InvalidMessageException("Invalid request: expired");
    }
    return json_decode($envelope['r'], TRUE);
  }

  /**
   * @param string $cxnId
   * @param string $secret
   *   Base64-encoded secret.
   * @param mixed $data
   *   Serializable data.
   * @return string
   *   Ciphertext.
   */
  public static function encodeCxn02Aes($cxnId, $secret, $data) {
    $envelope = array(
      'ttl' => Time::getTime() + Constants::REQUEST_TTL,
      'r' => json_encode($data),
    );

    $keys = self::deriveAesKeys($secret);

    $cipher = new \Crypt_AES(CRYPT_AES_MODE_CBC);
    $cipher->setKeyLength(Constants::AES_BYTES);
    $cipher->setKey($keys['enc']);
    $ciphertext = $cipher->encrypt(json_encode($envelope));

    return 'CXN-0.2-AES-CBC-HMAC'
    . Constants::PROTOCOL_DELIM . $cxnId
    . Constants::PROTOCOL_DELIM . hash_hmac('sha256', $ciphertext, $keys['auth'])
    . Constants::PROTOCOL_DELIM . $ciphertext;
  }

  /**
   * @param CxnStore\CxnStoreInterface $cxnStore
   *   A repository that contains shared secrets.
   * @param string $wireCiphertext
   *   Ciphertext.
   * @return array
   *   Array($cxnId,$data).
   * @throws InvalidMessageException
   */
  public static function decodeCxn02Aes($cxnStore, $message) {
    list ($parsedProt, $parsedCxnId, $parsedHmac, $parsedCiphertext) = explode(Constants::PROTOCOL_DELIM, $message, 4);
    if ($parsedProt != 'CXN-0.2-AES-CBC-HMAC') {
      throw new InvalidMessageException('Incorrect coding. Expected CXN-0.2-AES-CBC-HMAC');
    }
    $cxn = $cxnStore->getByCxnId($parsedCxnId);
    if (empty($cxn)) {
      throw new InvalidMessageException('Received message with unknown connection ID.');
    }

    $keys = self::deriveAesKeys($cxn['secret']);

    $localHmac = hash_hmac('sha256', $parsedCiphertext, $keys['auth']);
    if (!self::hash_compare($parsedHmac, $localHmac)) {
      throw new InvalidMessageException("Hash does not match ciphertext");
    }

    $plaintext = UserErrorException::adapt(function () use ($parsedCiphertext, $cxn, $keys) {
      $cipher = new \Crypt_AES(CRYPT_AES_MODE_CBC);
      $cipher->setKeyLength(Constants::AES_BYTES);
      $cipher->setKey($keys['enc']);
      return $cipher->decrypt($parsedCiphertext);
    });
    $envelope = json_decode($plaintext, TRUE);
    if (Time::getTime() > $envelope['ttl']) {
      throw new InvalidMessageException("Invalid request: expired");
    }
    return array($parsedCxnId, json_decode($envelope['r'], TRUE));
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
}
