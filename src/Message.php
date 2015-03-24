<?php
namespace Civi\Cxn\Rpc;

use Civi\Cxn\Rpc\Exception\InvalidMessageException;
use Civi\Cxn\Rpc\Exception\InvalidSigException;
use Civi\Cxn\Rpc\Exception\UserErrorException;

class Message {

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

    $cipher = new \Crypt_AES(CRYPT_AES_MODE_ECB);
    $cipher->setKey(base64_decode($secret));

    return 'CXN-0.2-AES' . Constants::PROTOCOL_DELIM
    . $cxnId . Constants::PROTOCOL_DELIM
    . $cipher->encrypt(json_encode($envelope));
  }

  /**
   * @param CxnStore\CxnStoreInterface $cxnStore
   * @param string $ciphertext
   *   Ciphertext.
   * @return array
   *   Array($cxnId,$data).
   */
  public static function decodeCxn02Aes($cxnStore, $ciphertext) {
    list ($wireProt, $wireCxnId, $ciphertext) = explode(Constants::PROTOCOL_DELIM, $ciphertext, 3);
    if ($wireProt != 'CXN-0.2-AES') {
      throw new \RuntimeException('Incorrect coding. Expected CXN-0.2-AES');
    }
    $cxn = $cxnStore->getByCxnId($wireCxnId);
    if (empty($cxn)) {
      throw new \RuntimeException('Received message with unknown connection ID.');
    }
    $plaintext = UserErrorException::adapt(function () use ($ciphertext, $cxn) {
      $cipher = new \Crypt_AES(CRYPT_AES_MODE_ECB);
      $cipher->setKey(base64_decode($cxn['secret']));
      return $cipher->decrypt($ciphertext);
    });
    $envelope = json_decode($plaintext, TRUE);
    if (Time::getTime() > $envelope['ttl']) {
      throw new InvalidMessageException("Invalid request: expired");
    }
    return array($wireCxnId, json_decode($envelope['r'], TRUE));
  }

}
