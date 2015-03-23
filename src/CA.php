<?php
namespace Civi\Cxn\Rpc;

use Civi\Cxn\Rpc\Exception\ExpiredCertException;
use Civi\Cxn\Rpc\Exception\InvalidCertException;

class CA {

  /**
   * @param array $keyPair
   *   Array with elements:
   *   - privatekey: string.
   *   - publickey: string.
   * @param string $dn
   *   Distinguished name (e.g. "/O=TestOrg").
   * @return string
   *   Certificate data.
   */
  public static function create($keyPair, $dn) {
    $privKey = new \Crypt_RSA();
    $privKey->loadKey($keyPair['privatekey']);

    $pubKey = new \Crypt_RSA();
    $pubKey->loadKey($keyPair['publickey']);
    $pubKey->setPublicKey();

    $subject = new \File_X509();
    $subject->setDN($dn);
    $subject->setPublicKey($pubKey);

    $issuer = new \File_X509();
    $issuer->setPrivateKey($privKey);
    $issuer->setDN($dn);

    $x509 = new \File_X509();
    $x509->makeCA();
    $x509->setEndDate(date('c', strtotime(Constants::CA_DURATION, Time::getTime())));

    $result = $x509->sign($issuer, $subject);
    return $x509->saveX509($result);
  }

  /**
   * @param string $file
   *   File path.
   * @return array
   *   Array with elements:
   *   - privatekey: string.
   *   - publickey: string.
   */
  public static function load($file) {
    return file_get_contents($file);
  }

  /**
   * @param string $file
   *   File path.
   * @param string $cert
   *   Certificate data.
   */
  public static function save($file, $cert) {
    file_put_contents($file, $cert);
  }

  public static function createSelfSignedCert($keyPair, $dn) {
    $privKey = new \Crypt_RSA();
    $privKey->loadKey($keyPair['privatekey']);

    $pubKey = new \Crypt_RSA();
    $pubKey->loadKey($keyPair['publickey']);
    $pubKey->setPublicKey();

    $subject = new \File_X509();
    $subject->setDN($dn);
    $subject->setPublicKey($pubKey);

    $issuer = new \File_X509();
    $issuer->setPrivateKey($privKey);
    $issuer->setDN($dn);

    $x509 = new \File_X509();
    $x509->setEndDate(date('c', strtotime(Constants::APP_DURATION, Time::getTime())));

    $result = $x509->sign($issuer, $subject);
    return $x509->saveX509($result);
  }


  /**
   * @param array $keyPair
   *   Array with elements:
   *   - privatekey: string.
   *   - publickey: string.
   * @param string $dn
   *   Distinguished name.
   * @return string
   *   CSR data.
   */
  public static function createCSR($keyPair, $dn) {
    $privKey = new \Crypt_RSA();
    $privKey->loadKey($keyPair['privatekey']);

    $pubKey = new \Crypt_RSA();
    $pubKey->loadKey($keyPair['publickey']);
    $pubKey->setPublicKey();

    $x509 = new \File_X509();
    $x509->setPrivateKey($privKey);
    $x509->setDN($dn);

    $csr = $x509->signCSR();
    return $x509->saveCSR($csr);
  }

  public static function signCSR($caKeyPair, $caCert, $csr) {
    $privKey = new \Crypt_RSA();
    $privKey->loadKey($caKeyPair['privatekey']);

    $subject = new \File_X509();
    $subject->loadCSR($csr);

    $issuer = new \File_X509();
    $issuer->loadX509($caCert);
    $issuer->setPrivateKey($privKey);

    $x509 = new \File_X509();
    $x509->setEndDate(date('c', strtotime(Constants::APP_DURATION, Time::getTime())));

    $result = $x509->sign($issuer, $subject);
    return $x509->saveX509($result);
  }

  public static function validate($caCert, $cert) {
    $x509 = new \File_X509();
    $x509->loadCA($caCert);
    $x509->loadX509($cert);
    if (!$x509->validateSignature()) {
      throw new InvalidCertException("Identity is invalid. Certificate is not signed by proper CA.");
    }
    if (!$x509->validateDate(Time::getTime())) {
      throw new ExpiredCertException("Identity is invalid. Certificate expired.");
    }
  }

}
