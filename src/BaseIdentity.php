<?php
namespace Civi\Cxn\Rpc;

abstract class BaseIdentity {

  const KEYLEN = 2048;

  /**
   * @var string
   */
  protected $cert;

  /**
   * @var array
   */
  protected $keypair;

  /**
   * @return array
   */
  protected function createKeypair() {
    $rsa = new \Crypt_RSA();
    return $rsa->createKey(self::KEYLEN);
  }

  public function createCSR($siteId, $callbackUrl) {
    $privKey = new \Crypt_RSA();
    $privKey->loadKey($this->getKey('privatekey'));

    $pubKey = new \Crypt_RSA();
    $pubKey->loadKey($this->getKey('publickey'));
    $pubKey->setPublicKey();

    $x509 = new \File_X509();
    $x509->setPrivateKey($privKey);
    $x509->setDNProp('commonName', $callbackUrl);
    $x509->setDNProp('id-at-organizationName', $siteId);

    $csr = $x509->signCSR();
    return $x509->saveCSR($csr);
  }

  /**
   * @return string
   */
  public function getCert() {
    return $this->cert;
  }

  /**
   * @param string $name
   *   Name of key ('privatekey' or 'publickey').
   * @return string
   *   Serialized key.
   */
  public function getKey($name) {
    return $this->keypair[$name];
  }

}
