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

class RoundtripTest extends \PHPUnit_Framework_TestCase {

  public function testRoundtrip() {
    $test = $this;

    $caKeyPair = KeyPair::create();
    $this->assertNotEmpty($caKeyPair['privatekey']);
    $this->assertNotEmpty($caKeyPair['publickey']);
    $caCert = CA::create($caKeyPair, '/O=test');
    $this->assertNotEmpty($caCert);
    $certValidator = new DefaultCertificateValidator($caCert, NULL, NULL);

    // The application provider sets up a RegistrationServer.
    // The site connects to the registration server.

    $appKeyPair = KeyPair::create();
    $appMeta = array(
      'title' => 'My App',
      'appId' => 'app:abcd1234abcd1234',
      'appCert' => CA::signCSR($caKeyPair, $caCert, CA::createAppCSR($appKeyPair, '/O=Application Provider, CN=app:abcd1234abcd1234')),
      'appUrl' => 'http://app-a.com/cxn',
      'perm' => array(
        'api' => array(),
        'grant' => array('view all contacts'),
      ),
    );
    $appCxnStore = new ArrayCxnStore();
    $regServer = new RegistrationServer($appMeta, $appKeyPair, $appCxnStore);
    $regServer->setCertValidator($certValidator);

    $siteCxnStore = new ArrayCxnStore();
    $regClient = new RegistrationClient($siteCxnStore, 'http://example.org/civicrm/cxn/api');
    $regClient->setCertValidator($certValidator);
    $regClient->setHttp(new Http\FakeHttp(function ($verb, $url, $blob) use ($regServer, $test) {
      $test->assertEquals('http://app-a.com/cxn', $url);
      return $regServer->handle($blob)->toHttp();
    }));
    list($cxnId, $regResponse) = $regClient->register($appMeta, $siteCxnStore);
    $this->assertEquals(0, $regResponse['is_error']);

    $siteCxn = $siteCxnStore->getByCxnId($cxnId);
    $this->assertEquals($siteCxn['appUrl'], 'http://app-a.com/cxn');
    $appCxn = $appCxnStore->getByCxnId($cxnId);
    $this->assertEquals($appCxn['siteUrl'], 'http://example.org/civicrm/cxn/api');

    // The application provider issues an API call to the site.

    $apiServer = new ApiServer($siteCxnStore);
    $apiServer->setCertValidator(new DefaultCertificateValidator($caCert, NULL, NULL));
    $apiServer->setRouter(function ($cxn, $entity, $action, $params) {
      if ($action == 'echo') {
        return $params;
      }
      else {
        return array('message' => 'unrecognized action');
      }
    });

    $apiClient = new ApiClient($appMeta, $appCxnStore, $cxnId);
    $apiClient->setHttp(new Http\FakeHttp(function ($verb, $url, $blob) use ($apiServer, $test) {
      $test->assertEquals('http://example.org/civicrm/cxn/api', $url);
      return $apiServer->handle($blob)->toHttp();
    }));
    $this->assertEquals(array('whimsy'), $apiClient->call('Foo', 'echo', array('whimsy')));
    $this->assertEquals(array('message' => 'unrecognized action'), $apiClient->call('Foo', 'bar', array()));

    // The site unregisters.
    list($unregCxnId, $unregResponse) = $regClient->unregister($appMeta);
    $this->assertEquals(0, $unregResponse['is_error']);
    $this->assertNull($siteCxnStore->getByCxnId($cxnId));
    $this->assertNull($appCxnStore->getByCxnId($cxnId));
  }

}
