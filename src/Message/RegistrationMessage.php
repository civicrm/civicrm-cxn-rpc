<?php
namespace Civi\Cxn\Rpc\Message;

use Civi\Cxn\Rpc\Exception\InvalidMessageException;
use Civi\Cxn\Rpc\Exception\UserErrorException;
use Civi\Cxn\Rpc\Constants;
use Civi\Cxn\Rpc\Time;

class RegistrationMessage {

  const NAME = 'CXN-0.2-RSA';

  /**
   * @param string $appId
   * @param string $appPubKey
   * @param array $data
   * @return string
   *   Ciphertext.
   */
  public static function encode($appId, $appPubKey, $data) {
    $envelope = array(
      'ttl' => Time::getTime() + Constants::REQUEST_TTL,
      'r' => json_encode($data),
    );
    return self::NAME . Constants::PROTOCOL_DELIM
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
  public static function decode($appId, $appPrivKey, $blob) {
    list ($wireProt, $wireAppId, $ciphertext) = explode(Constants::PROTOCOL_DELIM, $blob, 3);
    if ($wireProt != self::NAME) {
      throw new InvalidMessageException('Incorrect coding. Expected:' . self::NAME);
    }
    if ($wireAppId != $appId) {
      throw new InvalidMessageException('Received message intended for incorrect app.');
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

}
