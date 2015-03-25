<?php
namespace Civi\Cxn\Rpc\Message;

use Civi\Cxn\Rpc\Exception\InvalidMessageException;
use Civi\Cxn\Rpc\AppStore\AppStoreInterface;
use Civi\Cxn\Rpc\Message;
use Civi\Cxn\Rpc\UserError;
use Civi\Cxn\Rpc\Constants;
use Civi\Cxn\Rpc\Time;

class RegistrationMessage extends Message {

  const NAME = 'CXN-0.2-RSA';

  protected $appId;
  protected $appPubKey;

  public function __construct($appId, $appPubKey, $data) {
    parent::__construct($data);
    $this->appId = $appId;
    $this->appPubKey = $appPubKey;
  }

  /**
   * @return string
   *   Ciphertext.
   */
  public function encode() {
    $envelope = array(
      'ttl' => Time::getTime() + Constants::REQUEST_TTL,
      'r' => json_encode($this->data),
    );
    return self::NAME . Constants::PROTOCOL_DELIM
    . $this->appId . Constants::PROTOCOL_DELIM
    . self::getRsa($this->appPubKey, 'public')->encrypt(json_encode($envelope));
  }

  /**
   * @param AppStoreInterface $appStore
   * @param string $blob
   * @return array
   *   Decoded data.
   */
  public static function decode($appStore, $blob) {
    $parts = explode(Constants::PROTOCOL_DELIM, $blob, 3);
    if (count($parts) != 3) {
      throw new InvalidMessageException('Invalid message: insufficient parameters');
    }
    list ($wireProt, $wireAppId, $ciphertext) = $parts;
    if ($wireProt != self::NAME) {
      throw new InvalidMessageException('Invalid message: wrong protocol name');
    }
    $appPrivKey = $appStore->getPrivateKey($wireAppId);
    if (!$appPrivKey) {
      throw new InvalidMessageException('Received message intended for unknown app.');
    }
    $plaintext = UserError::adapt('Civi\Cxn\Rpc\Exception\InvalidMessageException', function () use ($ciphertext, $appPrivKey) {
      return self::getRsa($appPrivKey, 'private')->decrypt($ciphertext);
    });
    if (empty($plaintext)) {
      throw new InvalidMessageException("Invalid message: decryption produced empty message");
    }
    $envelope = json_decode($plaintext, TRUE);
    if (Time::getTime() > $envelope['ttl']) {
      throw new InvalidMessageException("Invalid message: expired");
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
