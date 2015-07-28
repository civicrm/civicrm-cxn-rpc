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

use Civi\Cxn\Rpc\CxnStore\ArrayCxnStore;
use Civi\Cxn\Rpc\Message\InsecureMessage;
use Civi\Cxn\Rpc\Message\RegistrationMessage;
use Civi\Cxn\Rpc\Message\StdMessage;
use Psr\Log\NullLogger;

class RegistrationServerTest extends \PHPUnit_Framework_TestCase {

  const APP_ID = 'app:org.civicrm.demoapp';

  public function invalidInputExamples() {
    $appKeyPair = KeyPair::create();
    $otherKeyPair = KeyPair::create();
    return array(
      array($appKeyPair, new InsecureMessage(array('sldjkfasdf'))),
      array($appKeyPair, new InsecureMessage(array('cxn' => array('abcd')))),
      array($appKeyPair, new StdMessage(Cxn::createId(), AesHelper::createSecret(), array('whatever'))),
      array($appKeyPair, new RegistrationMessage('app:org.civicrm.other', $appKeyPair['publickey'], array('whatever'))), // wrong appid
      array($appKeyPair, new RegistrationMessage(self::APP_ID, $otherKeyPair['publickey'], array('whatever'))), // wrong key
    );
  }

  /**
   * @param Message $invalidInput
   * @throws Exception\InvalidMessageException
   * @dataProvider invalidInputExamples
   */
  public function testInvalidInput($appKeyPair, $invalidInput) {
    $caKeyPair = KeyPair::create();
    $this->assertNotEmpty($caKeyPair['privatekey']);
    $this->assertNotEmpty($caKeyPair['publickey']);
    $caCert = CA::create($caKeyPair, '/O=test');
    $this->assertNotEmpty($caCert);

    $appMeta = array(
      'title' => 'My App',
      'appId' => self::APP_ID,
      'appCert' => CA::signCSR($caKeyPair, $caCert, CA::createAppCSR($appKeyPair, '/O=Application Provider')),
      'appUrl' => 'http://app-a.com/cxn',
      'perm' => array(
        'api' => array(),
        'grant' => array('view all contacts'),
      ),
    );
    $appCxnStore = new ArrayCxnStore();
    $regServer = new RegistrationServer($appMeta, $appKeyPair, $appCxnStore);
    list ($headers, $blob, $code) = $regServer->handle($invalidInput->encode())->toHttp();
    $this->assertEquals(400, $code);
    $message = InsecureMessage::decode($blob);
    $data = $message->getData();
    $this->assertEquals(1, $data['is_error']);
    $this->assertEquals('Invalid message coding', $data['error_message']);
  }

}
