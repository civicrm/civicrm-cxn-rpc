<?php

/*
 * This file is part of the civicrm-cxn-rpc package.
 *
 * Copyright (c) CiviCRM LLC <info@civicrm.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this package.
 */

namespace Civi\Cxn\Rpc;

use Civi\Cxn\Rpc\Exception\InvalidCertException;

class CATest extends \PHPUnit_Framework_TestCase {

  public function testCRL_SignedByCA() {
    // create CA
    $caKeyPairPems = KeyPair::create();
    $caCertPem = CA::create($caKeyPairPems, '/O=test');
    $this->assertNotEmpty($caCertPem);

    // create CRL
    $caCertObj = X509Util::loadCert($caCertPem, $caKeyPairPems);
    $crlObj = new \File_X509();
    $crlObj->setSerialNumber(1, 10);
    $crlObj->setEndDate('+2 days');
    $crlPem = $crlObj->saveCRL($crlObj->signCRL($caCertObj, $crlObj));
    $this->assertNotEmpty($crlPem);

    // create cert
    $appKeyPairPems = KeyPair::create();
    $appCertPem = CA::signCSR($caKeyPairPems, $caCertPem, CA::createAppCSR($appKeyPairPems, '/O=Application Provider'));

    // validate cert - OK. (Note: would throw exception if invalid)
    $certValidator = new DefaultCertificateValidator($caCertPem, NULL, $crlPem);
    $certValidator->validateCert($appCertPem);
  }

  public function testCRL_SignedByDist() {
    // create CA
    $caKeyPairPems = KeyPair::create();
    $caCertPem = CA::create($caKeyPairPems, '/O=test');
    $this->assertNotEmpty($caCertPem);

    // create CRL dist authority
    $crlDistKeyPairPems = KeyPair::create();
    $crlDistCertPem = CA::signCSR($caKeyPairPems, $caCertPem, CA::createCrlDistCSR($crlDistKeyPairPems, '/O=test'));
    $this->assertNotEmpty($crlDistCertPem);

    // create CRL
    $crlDistCertObj = X509Util::loadCert($crlDistCertPem, $crlDistKeyPairPems, $caCertPem);
    $this->assertNotEmpty($crlDistCertObj);

    $crlObj = new \File_X509();
    $crlObj->setSerialNumber(1, 10);
    $crlObj->setEndDate('+2 days');
    $crlPem = $crlObj->saveCRL($crlObj->signCRL($crlDistCertObj, $crlObj));
    $this->assertNotEmpty($crlPem);
    $crlObj->loadCRL($crlPem);

    // create cert
    $appKeyPair = KeyPair::create();
    $appCertPem = CA::signCSR($caKeyPairPems, $caCertPem, CA::createAppCSR($appKeyPair, '/O=Application Provider'), 4321);

    // validate cert - OK
    $certValidator = new DefaultCertificateValidator($caCertPem, $crlDistCertPem, $crlPem);
    $certValidator->validateCert($appCertPem); // throws exception if invalid

    // revoke cert
    $crlObj->setRevokedCertificateExtension(4321, 'id-ce-cRLReasons', 'privilegeWithdrawn');
    $crlObj->setEndDate('+3 months');
    $crlPem = $crlObj->saveCRL($crlObj->signCRL($crlDistCertObj, $crlObj));
    $this->assertNotEmpty($crlPem);

    // check for exception
    try {
      $certValidator = new DefaultCertificateValidator($caCertPem, $crlDistCertPem, $crlPem);
      $certValidator->validateCert($appCertPem);
      $this->fail('Expected InvalidCertException, but no exception was reported.');
    }
    catch (InvalidCertException $e) {
      $this->assertRegExp('/Certificate revoked/', $e->getMessage());
    }
  }

  /**
   * In this case, we have an app whose cert appears valid, but Mallory
   * has tried to swap out the CRL (so that she can replay revoked certs).
   */
  public function testCRL_SignedByUnknownDist() {
    // create CA
    $caKeyPairPems = KeyPair::create();
    $caCertPem = CA::create($caKeyPairPems, '/O=test');
    $this->assertNotEmpty($caCertPem);

    // create malloryCA
    $malloryCaKeyPairPems = KeyPair::create();
    $malloryCaCertPem = CA::create($malloryCaKeyPairPems, '/O=test');
    $this->assertNotEmpty($caCertPem);

    // create CRL dist authority - signed by malloryCA
    $crlDistKeyPairPems = KeyPair::create();
    $crlDistCertPem = CA::signCSR($malloryCaKeyPairPems, $malloryCaCertPem, CA::createCrlDistCSR($crlDistKeyPairPems, '/O=test'));
    $this->assertNotEmpty($crlDistCertPem);

    // create CRL - ultimately authorized on malloryCA
    $crlDistCertObj = X509Util::loadCert($crlDistCertPem, $crlDistKeyPairPems, $caCertPem);
    $this->assertNotEmpty($crlDistCertObj);

    $crlObj = new \File_X509();
    $crlObj->setSerialNumber(1, 10);
    $crlObj->setEndDate('+2 days');
    $crlPem = $crlObj->saveCRL($crlObj->signCRL($crlDistCertObj, $crlObj));
    $this->assertNotEmpty($crlPem);
    $crlObj->loadCRL($crlPem);

    // create cert
    $appKeyPair = KeyPair::create();
    $appCertPem = CA::signCSR($caKeyPairPems, $caCertPem, CA::createAppCSR($appKeyPair, '/O=Application Provider'), 4321);

    // check for exception
    try {
      $certValidator = new DefaultCertificateValidator($caCertPem, $crlDistCertPem, $crlPem);
      $certValidator->validateCert($appCertPem);
      $this->fail('Expected InvalidCertException, but no exception was reported.');
    }
    catch (InvalidCertException $e) {
      $this->assertRegExp('/CRL distributor has an invalid certificate/', $e->getMessage());
    }
  }

  /**
   * In this case, we have an app whose $appCertPem appears valid, and we have CRL
   * whose $crlDistCertPem is signed, but the $crlDistCertPem has usage rules
   * which do not allow signing CRLs.
   */
  public function testCRL_SignedByNonDist() {
    // create CA
    $caKeyPairPems = KeyPair::create();
    $caCertPem = CA::create($caKeyPairPems, '/O=test');
    $this->assertNotEmpty($caCertPem);

    // create would-be CRL dist authority -- but not really authorized for signing CRLs.
    // note createCSR() instead of createCrlDistCSR().
    $crlDistKeyPairPems = KeyPair::create();
    $crlDistCertPem = CA::signCSR($caKeyPairPems, $caCertPem, CA::createAppCSR($crlDistKeyPairPems, '/O=test'));
    $this->assertNotEmpty($crlDistCertPem);
    $certValidator = new DefaultCertificateValidator($caCertPem, NULL, NULL);
    $certValidator->validateCert($crlDistCertPem);

    // create CRL
    $crlDistCertObj = X509Util::loadCert($crlDistCertPem, $crlDistKeyPairPems, $caCertPem);
    $this->assertNotEmpty($crlDistCertObj);

    $crlObj = new \File_X509();
    $crlObj->setSerialNumber(1, 10);
    $crlObj->setEndDate('+2 days');
    $crlPem = $crlObj->saveCRL($crlObj->signCRL($crlDistCertObj, $crlObj));
    $this->assertNotEmpty($crlPem);
    $crlObj->loadCRL($crlPem);

    // create cert
    $appKeyPair = KeyPair::create();
    $appCertPem = CA::signCSR($caKeyPairPems, $caCertPem, CA::createAppCSR($appKeyPair, '/O=Application Provider'), 4321);

    // validate cert - fails due to improper CRL
    try {
      $certValidator = new DefaultCertificateValidator($caCertPem, $crlDistCertPem, $crlPem);
      $certValidator->validateCert($appCertPem);
      $this->fail('Expected InvalidCertException, but no exception was reported.');
    }
    catch (InvalidCertException $e) {
      $this->assertRegExp('/CRL-signing certificate is not a CRL-signing certificate/', $e->getMessage());
    }
  }

}
