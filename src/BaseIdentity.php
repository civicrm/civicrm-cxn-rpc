<?php
namespace Civi\Cxn\Rpc;

use Civi\Cxn\Rpc\Exception\IdentityException;

abstract class BaseIdentity {

  /**
   * @var string
   */
  protected $cert;

  /**
   * @var \File_X509|NULL
   */
  protected $certX509;

  /**
   * @var array
   */
  protected $keypair;

  /**
   * @var array
   */
  protected $rsaKeypair;

  /**
   * @return array
   */
  protected function createKeypair() {
    $rsa = new \Crypt_RSA();
    return $rsa->createKey(Constants::RSA_KEYLEN);
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
   * @return \File_X509|NULL
   */
  public function getCertAsX509() {
    if (empty($this->cert)) {
      return NULL;
    }
    if (!$this->certX509) {
      $this->certX509 = new \File_X509();
      $this->certX509->loadX509($this->cert);
    }
    return $this->certX509;
  }

  /**
   * @return string
   * @throws Exception\InvalidUsageException
   */
  public function getUsage() {
    $usage = $this->getCertAsX509()->getExtension('id-ce-extKeyUsage');
    if (count($usage) != 1) {
      throw new Exception\InvalidUsageException("Certificate must include exactly one authorized usage.");
    }
    return $usage[0];
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

  /**
   * @param string $name
   *   Name of key ('privatekey' or 'publickey').
   * @return \Crypt_RSA
   */
  public function getRsaKey($name) {
    if (!isset($this->rsaKeypair[$name])) {
      if (isset($this->keypair[$name])) {
        $rsa = new \Crypt_RSA();
        $rsa->loadKey($this->getKey($name));
        if ($name == 'publickey') {
          $rsa->setPublicKey();
        }
        $this->rsaKeypair[$name] = $rsa;
      }
      elseif ($this->cert && $name == 'publickey') {
        $this->rsaKeypair[$name] = $this->getCertAsX509()->getPublicKey();
      }
      else {
        throw new IdentityException("Failed to locate key: " . $name);
      }
      $this->rsaKeypair[$name]->setEncryptionMode(Constants::RSA_ENC_MODE);
      $this->rsaKeypair[$name]->setSignatureMode(Constants::RSA_SIG_MODE);
    } $this->rsaKeypair[$name]->setHash(Constants::RSA_HASH);

    return $this->rsaKeypair[$name];
  }

  public function toArray() {
    return array(
      'cert' => $this->cert,
      'keypair' => $this->keypair,
    );
  }

  public function fromArray($arr) {
    $this->cert = $arr['cert'];
    $this->certX509 = NULL;
    $this->keypair = $arr['keypair'];
    $this->rsaKeypair = array();
  }
}
