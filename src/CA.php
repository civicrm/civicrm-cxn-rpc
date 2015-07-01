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

    $result = $x509->sign($issuer, $subject, Constants::CERT_SIGNATURE_ALGORITHM);
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

    $result = $x509->sign($issuer, $subject, Constants::CERT_SIGNATURE_ALGORITHM);
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

    $csr = $x509->signCSR(Constants::CERT_SIGNATURE_ALGORITHM);

    return $x509->saveCSR($csr);
  }

  /**
   * Create a CSR for an authority that can issue CRLs.
   *
   * @param array $keyPair
   * @param string $dn
   * @return string
   *   PEM-encoded CSR.
   */
  public static function createCrlDistCSR($keyPair, $dn) {
    $privKey = new \Crypt_RSA();
    $privKey->loadKey($keyPair['privatekey']);

    $pubKey = new \Crypt_RSA();
    $pubKey->loadKey($keyPair['publickey']);
    $pubKey->setPublicKey();

    $csr = new \File_X509();
    $csr->setPrivateKey($privKey);
    $csr->setPublicKey($pubKey);
    $csr->setDN($dn);
    $csr->loadCSR($csr->saveCSR($csr->signCSR(Constants::CERT_SIGNATURE_ALGORITHM)));
    $csr->setExtension('id-ce-keyUsage', array('cRLSign'));

    $csrData = $csr->signCSR(Constants::CERT_SIGNATURE_ALGORITHM);
    return $csr->saveCSR($csrData);
  }

  /**
   * @param array $caKeyPair
   * @param string $caCert
   *   PEM-encoded cert.
   * @param string $csr
   *   PEM-encoded CSR.
   * @param int $serialNumber
   * @return string
   *   PEM-encoded cert.
   */
  public static function signCSR($caKeyPair, $caCert, $csr, $serialNumber = 1) {
    $privKey = new \Crypt_RSA();
    $privKey->loadKey($caKeyPair['privatekey']);

    $subject = new \File_X509();
    $subject->loadCSR($csr);

    $issuer = new \File_X509();
    $issuer->loadX509($caCert);
    $issuer->setPrivateKey($privKey);

    $x509 = new \File_X509();
    $x509->setSerialNumber($serialNumber, 10);
    $x509->setEndDate(date('c', strtotime(Constants::APP_DURATION, Time::getTime())));

    $result = $x509->sign($issuer, $subject, Constants::CERT_SIGNATURE_ALGORITHM);
    return $x509->saveX509($result);
  }

  /**
   * @param string $certPem
   *   PEM-encoded cert.
   * @param string $caCertPem
   *   PEM-encoded cert.
   * @param string|null $crlPem
   *   PEM-encoded CRL.
   * @param string|null $crlDistCertPem
   *   PEM-encoded cert for the service which generated CRL.
   * @throws ExpiredCertException
   * @throws InvalidCertException
   */
  public static function validate($certPem, $caCertPem, $crlPem = NULL, $crlDistCertPem = NULL) {
    $caCertObj = X509Util::loadCACert($caCertPem);

    $certObj = new \File_X509();
    $certObj->loadCA($caCertPem);

    if ($crlPem !== NULL) {
      $crlObj = new \File_X509();
      if ($crlDistCertPem) {
        $crlDistCertObj = X509Util::loadCrlDistCert($crlDistCertPem, NULL, $caCertPem);
        if ($crlDistCertObj->getSubjectDN(FILE_X509_DN_STRING) !== $caCertObj->getSubjectDN(FILE_X509_DN_STRING)) {
          throw new InvalidCertException("CRL distributor does not act on behalf of this CA");
        }
        try {
          CA::validate($crlDistCertPem, $caCertPem);
        }
        catch (InvalidCertException $ie) {
          throw new InvalidCertException("CRL distributor has an invalid certificate", 0, $ie);
        }
        $crlObj->loadCA($crlDistCertPem);
      }
      $crlObj->loadCA($caCertPem);
      $crlObj->loadCRL($crlPem);
      if (!$crlObj->validateSignature()) {
        throw new InvalidCertException("CRL signature is invalid");
      }
    }

    $parsedCert = $certObj->loadX509($certPem);
    if ($crlPem !== NULL) {
      $revoked = $crlObj->getRevoked($parsedCert['tbsCertificate']['serialNumber']->toString());
      if (!empty($revoked)) {
        throw new InvalidCertException("Identity is invalid. Certificate revoked.");
      }
    }

    if (!$certObj->validateSignature()) {
      throw new InvalidCertException("Identity is invalid. Certificate is not signed by proper CA.");
    }
    if (!$certObj->validateDate(Time::getTime())) {
      throw new ExpiredCertException("Identity is invalid. Certificate expired.");
    }
  }

}
