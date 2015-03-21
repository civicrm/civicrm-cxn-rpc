<?php
namespace Civi\Cxn\Rpc;

class RoundtripTest extends \PHPUnit_Framework_TestCase {

  /**
   * @var CaIdentity
   */
  protected static $ca;

  /**
   * @var SiteIdentity
   */
  protected static $siteA;

  /**
   * @var SiteIdentity
   */
  protected static $siteB;

  /**
   * @var AppIdentity
   */
  protected static $appA;

  /**
   * @var AppIdentity
   */
  protected static $appB;

  public static function setUpBeforeClass() {
    parent::setUpBeforeClass();
    self::$ca = CaIdentity::create("/O=test");
    self::$appA = AppIdentity::create(self::$ca, 'appA-1234-1234-1234', 'http://app-a.com/callback');
    self::$appB = AppIdentity::create(self::$ca, 'appB-5678-5678-5678', 'http://app-b.com/callback');
    self::$siteA = SiteIdentity::create(self::$ca, 'siteA-asdf-asdf-asdf', 'http://site-a.org/callback');
    self::$siteB = SiteIdentity::create(self::$ca, 'siteB-fdsa-fdsa-fdsa', 'http://site-b.org/callback');
  }

  /**
   * Test in which the SiteClient(siteA) connects to the AppServer(appB).
   */
  public function testSiteToAppOk() {
    $siteClient = new SiteClient(self::$ca, self::$siteA, self::$appA);
    $reqCiphertext = $siteClient->createRequest('entity-1', 'action-1', array(
      'foo' => 'bar',
    ));

    $this->assertTrue(is_string($reqCiphertext));
    $this->assertEquals('http://app-a.com/callback', $siteClient->getRemoteUrl());

    $appServer = new AppServer(self::$ca, self::$appA);
    list ($parsedIdentity, $parsedEntity, $parsedAction, $parsedParams) = $appServer->parseRequest($reqCiphertext);
    $this->assertEquals($parsedIdentity->getCallbackUrl(), self::$siteA->getCallbackUrl());
    $this->assertEquals($parsedIdentity->getAgentId(), self::$siteA->getAgentId());
    $this->assertEquals('entity-1', $parsedEntity);
    $this->assertEquals('action-1', $parsedAction);
    $this->assertEquals(array('foo' => 'bar'), $parsedParams);

    $respCiphertext = $appServer->createResponse(array('field' => 'value-123'), $parsedIdentity);
    $this->assertTrue(is_string($respCiphertext));

    $response = $siteClient->parseResponse($respCiphertext);
    $this->assertEquals('value-123', $response['field']);
  }

  /**
   * Test in which the AppClient(appB) connects to the SiteServer(siteB).
   */
  public function testAppToSiteOk() {
    $appClient = new AppClient(self::$ca, self::$appB, self::$siteB);
    $reqCiphertext = $appClient->createRequest('entity-2', 'action-2', array(
      'whiz' => 'bang',
    ));

    $this->assertTrue(is_string($reqCiphertext));
    $this->assertEquals('http://site-b.org/callback', $appClient->getRemoteUrl());

    $siteServer = new SiteServer(self::$ca, self::$siteB);
    list ($parsedIdentity, $parsedEntity, $parsedAction, $parsedParams) = $siteServer->parseRequest($reqCiphertext);
    $this->assertEquals($parsedIdentity->getCallbackUrl(), self::$appB->getCallbackUrl());
    $this->assertEquals($parsedIdentity->getAgentId(), self::$appB->getAgentId());
    $this->assertEquals('entity-2', $parsedEntity);
    $this->assertEquals('action-2', $parsedAction);
    $this->assertEquals(array('whiz' => 'bang'), $parsedParams);

    $respCiphertext = $siteServer->createResponse(array('field' => 'value-123'), $parsedIdentity);
    $this->assertTrue(is_string($respCiphertext));

    $response = $appClient->parseResponse($respCiphertext);
    $this->assertEquals('value-123', $response['field']);
  }


  // test: misuse site as app
  // test: misuse app as site
  // test: client and server mix up identities
  // test: submit an ancient request
  // test: malformed callback url
  // test: malformed agentId
  // test: validate with wrong ca

}
