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
use Civi\Cxn\Rpc\Http\FakeHttp;
use Civi\Cxn\Rpc\Http\ViaPortHttp;
use Civi\Cxn\Rpc\Message\InsecureMessage;
use Civi\Cxn\Rpc\Message\RegistrationMessage;
use Civi\Cxn\Rpc\Message\StdMessage;
use Psr\Log\NullLogger;

class ViaPortHttpTest extends \PHPUnit_Framework_TestCase {

  protected $lastCall;

  public function testVia() {
    $fakeHttp = new FakeHttp(array($this, 'logRequest'));
    $viaPortHttp = new ViaPortHttp($fakeHttp, 'proxy.example.com:1234');

    $viaPortHttp->send('GET', 'http://foo.example.com', NULL, array('Accept' => 'text/javascript'));
    $this->assertEquals(
      array(
        'GET',
        'http://proxy.example.com:1234',
        NULL,
        array(
          'Accept' => 'text/javascript',
          'Host' => 'foo.example.com',
        ),
      ),
      $this->lastCall
    );

    $viaPortHttp->send('POST', 'http://bar.example.com:567/foo/bar?whiz=bang', NULL);
    $this->assertEquals(
      array(
        'POST',
        'http://proxy.example.com:1234/foo/bar?whiz=bang',
        NULL,
        array(
          'Host' => 'bar.example.com',
        ),
      ),
      $this->lastCall
    );
  }

  public function logRequest($verb, $url, $blob, $headers) {
    $this->lastCall = array($verb, $url, $blob, $headers);
  }

  public function testValidate() {
    // IPv4 + port
    $this->assertTrue(ViaPortHttp::validate('123.123.123.123:456'));

    // IPv6 + port
    $this->assertTrue(ViaPortHttp::validate('2001:0db8:85a3:0000:0000:8a2e:0370:7334:456'));
    $this->assertTrue(ViaPortHttp::validate('2001:db8:85a3::8a2e:370:7334:456'));

    // Second level domain name + port
    $this->assertTrue(ViaPortHttp::validate('example.com:456'));

    // Third level domain name + port
    $this->assertTrue(ViaPortHttp::validate('proxy.example.com:456'));
    $this->assertTrue(ViaPortHttp::validate('proxy-service.example.com:456'));

    // Bad TLD
    $this->assertFalse(ViaPortHttp::validate('example.123:456'));

    // IPv6, missing segment
    $this->assertFalse(ViaPortHttp::validate('2001:0db8:85a3:0000:0000:8a2e:0370:456'));

    // IPV6, bad char ('z')
    $this->assertFalse(ViaPortHttp::validate('2001:0db8:85a3:0000:0000:8a2e:0370:733z:456'));

    // Bad port number ('http')
    $this->assertFalse(ViaPortHttp::validate('proxy.example.com:http'));

    // Missing port
    $this->assertFalse(ViaPortHttp::validate('proxy.example.com'));

    // Bad leading char in hostname ('-')
    $this->assertFalse(ViaPortHttp::validate('-proxy.example.com:456'));

    // Illegal char in hostname
    $this->assertFalse(ViaPortHttp::validate('pr(oxy.example.com:456'));
    $this->assertFalse(ViaPortHttp::validate('proxy.examp:le.com:456'));
  }

}
