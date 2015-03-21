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
  }

  public function constructorExceptions() {
    $ca = CaIdentity::create("/O=test");
    $siteA = SiteIdentity::create($ca, 'siteA-asdf-asdf-asdf', 'http://site-a.org/callback');
    $siteB = SiteIdentity::create($ca, 'siteB-fdsa-fdsa-fdsa', 'http://site-b.org/callback');
    $appA = AppIdentity::create($ca, 'appA-1234-1234-1234', 'http://app-a.com/callback');
    $appB = AppIdentity::create($ca, 'appB-5678-5678-5678', 'http://app-b.com/callback');

    $cases = array();
    $cases[] = array('Civi\Cxn\Rpc\AppServer', $ca, $siteA, NULL, 'Civi\Cxn\Rpc\Exception\InvalidUsageException');
    $cases[] = array('Civi\Cxn\Rpc\SiteServer', $ca, $appA, NULL, 'Civi\Cxn\Rpc\Exception\InvalidUsageException');
    $cases[] = array('Civi\Cxn\Rpc\AppClient', $ca, $siteA, $siteA, 'Civi\Cxn\Rpc\Exception\InvalidUsageException');
    $cases[] = array('Civi\Cxn\Rpc\AppClient', $ca, $appA, $appA, 'Civi\Cxn\Rpc\Exception\InvalidUsageException');
    $cases[] = array('Civi\Cxn\Rpc\SiteClient', $ca, $siteA, $siteA, 'Civi\Cxn\Rpc\Exception\InvalidUsageException');
    $cases[] = array('Civi\Cxn\Rpc\SiteClient', $ca, $appA, $appA, 'Civi\Cxn\Rpc\Exception\InvalidUsageException');
    return $cases;
  }

  /**
   * @dataProvider constructorExceptions
   * @param string $class
   * @param CaIdentity $ca
   * @param AgentIdentity $myIdentity
   * @param AgentIdentity $remoteIdentity
   * @param string $expectException
   */
  public function testConstructorExceptions($class, $ca, $myIdentity, $remoteIdentity, $expectException) {
    try {
      new $class($ca, $myIdentity, $remoteIdentity);
      $this->fail("Expected exception: " . $expectException);
    }
    catch (\Exception $e) {
      $this->assertInstanceOf($expectException, $e);
    }
  }

  public function roundtripExamples() {
    $ca = CaIdentity::create("/O=test");
    $siteA = SiteIdentity::create($ca, 'siteA-asdf-asdf-asdf', 'http://site-a.org/callback');
    $siteB = SiteIdentity::create($ca, 'siteB-fdsa-fdsa-fdsa', 'http://site-b.org/callback');
    $siteBadUrl = SiteIdentity::create($ca, 'siteC-fdsa-fdsa-fdsa', 'htt://site-c.org/callback');
    $siteBadId = SiteIdentity::create($ca, 'me', 'http://site-d.org/callback');
    $appA = AppIdentity::create($ca, 'appA-1234-1234-1234', 'http://app-a.com/callback');
    $appB = AppIdentity::create($ca, 'appB-5678-5678-5678', 'http://app-b.com/callback');

    $cases = array();

    // #0: OK, siteA => appA
    $cases[] = array(
      new SiteClient($ca, $siteA, $appA),
      NULL, // expectCreateRequestException
      array('url' => 'http://app-a.com/callback'), // expectTo

      new AppServer($ca, $appA),
      NULL, // expectParseRequestException
      array('url' => 'http://site-a.org/callback', 'id' => 'siteA-asdf-asdf-asdf'), // expectFrom
    );

    // #1: OK, siteB => appA
    $cases[] = array(
      new SiteClient($ca, $siteB, $appA),
      NULL, // expectCreateRequestException
      array('url' => 'http://app-a.com/callback'), // expectTo

      new AppServer($ca, $appA),
      NULL, // expectParseRequestException
      array('url' => 'http://site-b.org/callback', 'id' => 'siteB-fdsa-fdsa-fdsa'), // expectFromUrl
    );

    // #2: OK, appB => siteB
    $cases[] = array(
      new AppClient($ca, $appB, $siteB),
      NULL, // expectCreateRequestException
      array('url' => 'http://site-b.org/callback'), // expectTo

      new SiteServer($ca, $siteB),
      NULL, // expectParseRequestException
      array('url' => 'http://app-b.com/callback', 'id' => 'appB-5678-5678-5678'), // expectFrom
    );

    // #3: Err, appB encodes for siteB... but delivers to appA
    $cases[] = array(
      new AppClient($ca, $appB, $siteB),
      NULL, // expectCreateRequestException
      array('url' => 'http://site-b.org/callback'), // expectTo

      new AppServer($ca, $appA),
      'Civi\Cxn\Rpc\Exception\InvalidUsageException', // expectParseRequestException
      NULL, // expectFrom
    );

    // #4: Err, siteA encodes for appA... but delivers to siteB
    $cases[] = array(
      new SiteClient($ca, $siteA, $appA),
      NULL, // expectCreateRequestException
      array('url' => 'http://app-a.com/callback'), // expectTo

      new SiteServer($ca, $siteB),
      'Civi\Cxn\Rpc\Exception\InvalidUsageException', // expectParseRequestException
      NULL, // expectFrom
    );

    // #5: Err, siteBadUrl sends a message to appA, which rejects it
    $cases[] = array(
      new SiteClient($ca, $siteBadUrl, $appA),
      NULL, // expectCreateRequestException
      array('url' => 'http://app-a.com/callback'), // expectTo

      new AppServer($ca, $appA),
      'Civi\Cxn\Rpc\Exception\InvalidDnException', // expectParseRequestException
      NULL, // expectFrom
    );

    // #6: Err, siteBadId sends a message to appA, which rejects it
    $cases[] = array(
      new SiteClient($ca, $siteBadId, $appA),
      NULL, // expectCreateRequestException
      array('url' => 'http://app-a.com/callback'), // expectTo

      new AppServer($ca, $appA),
      'Civi\Cxn\Rpc\Exception\InvalidDnException', // expectParseRequestException
      NULL, // expectFrom
    );

    // FIXME: #7: Err, siteA encodes valid message for appA... but delivers to appB
    //$cases[] = array(
    //  new SiteClient($ca, $siteA, $appA),
    //  NULL, // expectCreateRequestException
    //  array('url' => 'http://app-a.com/callback'), // expectTo
    //
    //  new AppServer($ca, $appB),
    //  'Civi\Cxn\Rpc\Exception\UndecryptableException', // expectParseRequestException
    //  NULL, // expectFrom
    //);

    // FIXME: test: submit an ancient request
    // FIXME: test: validate request with cert signed by wrong ca

    return $cases;
  }

  /**
   * Test in which a client connects to a server.
   *
   * @dataProvider roundtripExamples
   * @param BaseClient $client
   * @param \Exception|NULL $expectCreateRequestException
   * @param array $expectTo
   * @param BaseServer $server
   * @param \Exception|NULL $expectParseRequestException
   * @param array $expectFrom
   * @throws \Exception
   */
  public function testRoundtrip($client, $expectCreateRequestException, $expectTo, $server, $expectParseRequestException, $expectFrom) {
    // Client prepares request
    try {
      $reqCiphertext = $client->createRequest('entity-1', 'action-1', array(
        'foo' => 'bar',
      ));
    }
    catch (\Exception $e) {
      if (empty($expectCreateRequestException)) {
        throw $e;
      }
      $this->assertInstanceOf($expectCreateRequestException, $e);
      return;
    }
    $this->assertEquals(NULL, $expectCreateRequestException);
    $this->assertTrue(is_string($reqCiphertext));
    $this->assertEquals($expectTo, array('url' => $client->getRemoteUrl()));

    // Server receives request
    try {
      list ($parsedIdentity, $parsedEntity, $parsedAction, $parsedParams) = $server->parseRequest($reqCiphertext);
    }
    catch (\Exception $e) {
      if (empty($expectParseRequestException)) {
        throw $e;
      }
      $this->assertInstanceOf($expectParseRequestException, $e);
      return;
    }
    $this->assertEquals(NULL, $expectParseRequestException);
    $this->assertEquals($expectFrom, array(
      'url' => $parsedIdentity->getCallbackUrl(),
      'id' => $parsedIdentity->getAgentId(),
    ));
    $this->assertEquals('entity-1', $parsedEntity);
    $this->assertEquals('action-1', $parsedAction);
    $this->assertEquals(array('foo' => 'bar'), $parsedParams);

    // Server sends response
    $respCiphertext = $server->createResponse(array('field' => 'value-123'), $parsedIdentity);
    $this->assertTrue(is_string($respCiphertext));

    // client receives response
    $response = $client->parseResponse($respCiphertext);
    $this->assertEquals('value-123', $response['field']);
  }

}
