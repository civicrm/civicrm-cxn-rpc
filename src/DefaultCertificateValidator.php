<?php
namespace Civi\Cxn\Rpc;

use Civi\Cxn\Rpc\Exception\ExpiredCertException;
use Civi\Cxn\Rpc\Exception\InvalidCertException;

/**
 * Class DefaultCertificateValidator
 * @package Civi\Cxn\Rpc
 *
 * The default certificate validator will:
 *  - Check that the certificate is signed by canonical CA.
 *  - Check that the certificate has not been revoked by the canonical CA.
 */
class DefaultCertificateValidator implements CertificateValidatorInterface {

  /**
   * @var string
   */
  protected $caCertPem;
  /**
   * @var string
   */
  protected $crlPem;

  /**
   * @var string
   */
  protected $crlDistCertPem;

  /**
   * @param string $caCertPem
   * @param string $crlDistCertPem
   * @param string $crlPem
   */
  public function __construct($caCertPem, $crlDistCertPem = NULL, $crlPem = NULL) {
    $this->caCertPem = $caCertPem;
    $this->crlDistCertPem = $crlDistCertPem;
    $this->crlPem = $crlPem;
  }

  /**
   * Determine whether an X.509 certificate is currently valid.
   *
   * @param string $certPem
   *   PEM-encoded certificate.
   * @throws InvalidCertException
   *   Invalid certificates are reported as exceptions.
   */
  public function validateCert($certPem) {
    self::validate($certPem, $this->caCertPem, $this->crlPem, $this->crlDistCertPem);
  }

  protected static function validate($certPem, $caCertPem, $crlPem = NULL, $crlDistCertPem = NULL) {
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
          self::validate($crlDistCertPem, $caCertPem);
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
