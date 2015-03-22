<?php
namespace Civi\Cxn\Rpc;

class CaIdentity extends BaseIdentity {

  const CA_DURATION = '+10 year';

  const AGENT_DURATION = '+1 year';

  /**
   * Load an identity from a set of files.
   *
   * @param string $prefix
   *   A base name shared by the files. For example, "/tmp/hello"
   *   would correspond to files "/tmp/hello.crt", "/tmp/hello.key",
   *   and "/tmp/hello.pub".
   * @return AgentIdentity
   */
  public static function loadFiles($prefix) {
    $identity = new CaIdentity();
    $identity->cert = file_get_contents("$prefix.crt");
    $identity->keypair = array();
    foreach (array('publickey' => "$prefix.pub", 'privatekey' => "$prefix.key") as $name => $file) {
      if (file_exists($file)) {
        $identity->keypair[$name] = file_get_contents($file);
      }
    }

    return $identity;
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
    $x509->setEndDate(date('c', strtotime(self::AGENT_DURATION, Time::getTime())));

    $result = $x509->sign($issuer, $subject);
    return $x509->saveX509($result);
  }

}
