<?php
namespace Civi\Cxn\Rpc;

use Civi\Cxn\Rpc\Exception\InvalidMessageException;
use Civi\Cxn\Rpc\Message\AppMetasMessage;

class AppMetasMessageTest extends \PHPUnit_Framework_TestCase {

  public function testSignedValid() {
    list($caKeyPair, $caCert) = $this->createCA();
    $distPointKeyPair = KeyPair::create();
    $distPointCert = CA::signCSR($caKeyPair, $caCert,
      CA::createDirSvcCSR($distPointKeyPair, 'C=US, O=CiviCRM, OU=Civi App Manager, CN=' . Constants::OFFICIAL_APPMETAS_CN));

    $msg = new AppMetasMessage($distPointCert, $distPointKeyPair, array(
      'app-1' => array(
        'appId' => 'app-1',
      ),
    ));

    $appMetas = AppMetasMessage::decode($caCert, $msg->encode())->getData();
    $this->assertEquals('app-1', $appMetas['app-1']['appId']);
  }

  /**
   * The application wants to verify the authenticity of the app list,
   * but gets a signature from an unrecognized party.
   */
  public function testSignedInvalid() {
    list($caKeyPair, $caCert) = $this->createCA();
    $distPointKeyPair = KeyPair::create();
    $distPointCert = CA::signCSR($caKeyPair, $caCert, CA::createDirSvcCSR($distPointKeyPair, 'O=Someone, CN=else'));

    $msg = new AppMetasMessage($distPointCert, $distPointKeyPair, array(
      'app-1' => array(
        'appId' => 'app-1',
      ),
    ));

    try {
      AppMetasMessage::decode($caCert, $msg->encode())->getData();
      $this->fail('Expected an exception');
    }
    catch (InvalidMessageException $e) {
      $this->assertEquals('Invalid message: signed by unauthorized party', $e->getMessage());
    }
  }

  /**
   * The application does not want to verify the authenticity of the app list,
   * so it's valid - even if signed by someone else.
   */
  public function testUnsignedValid() {
    list($caKeyPair, $caCert) = $this->createCA();
    $distPointKeyPair = KeyPair::create();
    $distPointCert = CA::signCSR($caKeyPair, $caCert, CA::createDirSvcCSR($distPointKeyPair, 'O=Someone, CN=else'));

    $msg = new AppMetasMessage($distPointCert, $distPointKeyPair, array(
      'app-2' => array(
        'appId' => 'app-2',
      ),
    ));

    $appMetas = AppMetasMessage::decode(NULL, $msg->encode())->getData();
    $this->assertEquals('app-2', $appMetas['app-2']['appId']);
  }

  /**
   * @return array
   */
  protected function createCA() {
    $caKeyPair = KeyPair::create();
    $this->assertNotEmpty($caKeyPair['privatekey']);
    $this->assertNotEmpty($caKeyPair['publickey']);
    $caCert = CA::create($caKeyPair, '/O=test');
    $this->assertNotEmpty($caCert);
    return array($caKeyPair, $caCert);
  }

}
