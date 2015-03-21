<?php
namespace Civi\Cxn\Rpc;

class CaIdentity extends BaseIdentity {

  const CA_DURATION = '+1 year';

  const AGENT_DURATION = '+1 month';

  public static function load($cert) {
    throw new \Exception("Not implemented");
  }

  public static function create($dn) {
    $ca = new static();
    $ca->keypair = $ca->createKeypair();
    $ca->cert = $ca->createCaCert($dn);
    return $ca;
  }

  public function createCaCert($dn) {
    $privKey = new \Crypt_RSA();
    $privKey->loadKey($this->getKey('privatekey'));

    $pubKey = new \Crypt_RSA();
    $pubKey->loadKey($this->getKey('publickey'));
    $pubKey->setPublicKey();

    $subject = new \File_X509();
    $subject->setDN($dn);
    $subject->setPublicKey($pubKey);

    $issuer = new \File_X509();
    $issuer->setPrivateKey($privKey);
    $issuer->setDN($dn);

    $x509 = new \File_X509();
    $x509->makeCA();
    $x509->setEndDate(self::CA_DURATION);

    $result = $x509->sign($issuer, $subject);
    return $x509->saveX509($result);
  }

  /**
   * @param string $csr
   * @param string $usage
   * @return string
   * @throws \Exception
   */
  public function signCSR($csr, $usage) {
    $privKey = new \Crypt_RSA();
    $privKey->loadKey($this->getKey('privatekey'));

    $subject = new \File_X509();
    $subject->loadCSR($csr);
    $subject->setExtension('id-ce-extKeyUsage', $usage);

    $issuer = new \File_X509();
    $issuer->loadX509($this->getCert());
    $issuer->setPrivateKey($privKey);

    $x509 = new \File_X509();
    $x509->setEndDate(self::AGENT_DURATION);

    $result = $x509->sign($issuer, $subject);
    return $x509->saveX509($result);
  }

}
