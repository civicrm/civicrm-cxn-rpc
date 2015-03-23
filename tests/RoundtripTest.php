<?php
namespace Civi\Cxn\Rpc;

class RoundtripTest extends \PHPUnit_Framework_TestCase {

  public function testRoundtrip() {
    $test = $this;

    $caKeyPair = KeyPair::create();
    $this->assertNotEmpty($caKeyPair['privatekey']);
    $this->assertNotEmpty($caKeyPair['publickey']);
    $caCert = CA::create($caKeyPair, '/O=test');
    $this->assertNotEmpty($caCert);

    // The application provider sets up a RegistrationServer.
    // The site connects to the registration server.

    $appKeyPair = KeyPair::create();
    $appMeta = array(
      'appId' => 'abcd1234abcd1234',
      'appCert' => CA::signCSR($caKeyPair, $caCert, CA::createCSR($appKeyPair, '/O=Application Provider')),
      'appUrl' => 'http://app-a.com/cxn',
      'perm' => array(
        'sys' => array('view all contacts'),
      ),
    );
    $appCxnStore = new ArrayCxnStore();
    $regServer = new RegistrationServer($appMeta, $appKeyPair, $appCxnStore);

    $siteCxnStore = new ArrayCxnStore();
    $regClient = new RegistrationClient($caCert, $siteCxnStore, 'http://example.org/civicrm/cxn/api');
    $regClient->setHttp(new Http\FakeHttp(function ($verb, $url, $blob) use ($regServer, $test) {
      $test->assertEquals('http://app-a.com/cxn', $url);
      return $regServer->handle($blob);
    }));
    list($cxnId, $status) = $regClient->register($appMeta, $siteCxnStore);
    $this->assertTrue($status);

    $siteCxn = $siteCxnStore->getByCxnId($cxnId);
    $this->assertEquals($siteCxn['appUrl'], 'http://app-a.com/cxn');
    $appCxn = $appCxnStore->getByCxnId($cxnId);
    $this->assertEquals($appCxn['siteUrl'], 'http://example.org/civicrm/cxn/api');

    // The application provider issues an API call to the site.

    $apiServer = new APIServer($appMeta, $siteCxnStore);
    $apiServer->setRouter(function ($cxn, $entity, $action, $params) {
      if ($action == 'echo') {
        return $params;
      }
      else {
        return array('message' => 'unrecognized action');
      }
    });

    $apiClient = new APIClient($appMeta, $appCxnStore, $cxnId);
    $apiClient->setHttp(new Http\FakeHttp(function ($verb, $url, $blob) use ($apiServer, $test) {
      $test->assertEquals('http://example.org/civicrm/cxn/api', $url);
      return $apiServer->handle($blob);
    }));
    $this->assertEquals(array('whimsy'), $apiClient->call('Foo', 'echo', array('whimsy')));
    $this->assertEquals(array('message' => 'unrecognized action'), $apiClient->call('Foo', 'bar', array()));

    // The site unregisters.
    $regClient->unregister($appMeta);
    //$this->assertNull($siteCxnStore->getByCxnId($cxnId));
    //$this->assertNull($appCxnStore->getByCxnId($cxnId));
  }

}
