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
    Examples::init();

    $cases = array();
    $cases[] = array(
      'Civi\Cxn\Rpc\AppServer',
      Examples::$ca,
      Examples::$siteA,
      NULL,
      'Civi\Cxn\Rpc\Exception\InvalidUsageException'
    );
    $cases[] = array(
      'Civi\Cxn\Rpc\SiteServer',
      Examples::$ca,
      Examples::$appA,
      NULL,
      'Civi\Cxn\Rpc\Exception\InvalidUsageException'
    );
    $cases[] = array(
      'Civi\Cxn\Rpc\AppClient',
      Examples::$ca,
      Examples::$siteA,
      Examples::$siteA,
      'Civi\Cxn\Rpc\Exception\InvalidUsageException'
    );
    $cases[] = array(
      'Civi\Cxn\Rpc\AppClient',
      Examples::$ca,
      Examples::$appA,
      Examples::$appA,
      'Civi\Cxn\Rpc\Exception\InvalidUsageException'
    );
    $cases[] = array(
      'Civi\Cxn\Rpc\SiteClient',
      Examples::$ca,
      Examples::$siteA,
      Examples::$siteA,
      'Civi\Cxn\Rpc\Exception\InvalidUsageException'
    );
    $cases[] = array(
      'Civi\Cxn\Rpc\SiteClient',
      Examples::$ca,
      Examples::$appA,
      Examples::$appA,
      'Civi\Cxn\Rpc\Exception\InvalidUsageException'
    );
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
    Examples::init();

    $cases = array();

    // #0: OK, siteA => appA
    $cases[] = array(
      new SiteClient(Examples::$ca, Examples::$siteA, Examples::$appA),
      NULL, // expectCreateRequestException
      array('url' => 'http://app-a.com/callback'), // expectTo

      new AppServer(Examples::$ca, Examples::$appA),
      NULL, // expectParseRequestException
      array('url' => 'http://site-a.org/callback', 'id' => 'siteA-asdf-asdf-asdf'), // expectFrom
    );

    // #1: OK, siteB => appA
    $cases[] = array(
      new SiteClient(Examples::$ca, Examples::$siteB, Examples::$appA),
      NULL, // expectCreateRequestException
      array('url' => 'http://app-a.com/callback'), // expectTo

      new AppServer(Examples::$ca, Examples::$appA),
      NULL, // expectParseRequestException
      array('url' => 'http://site-b.org/callback', 'id' => 'siteB-fdsa-fdsa-fdsa'), // expectFromUrl
    );

    // #2: OK, appB => siteB
    $cases[] = array(
      new AppClient(Examples::$ca, Examples::$appB, Examples::$siteB),
      NULL, // expectCreateRequestException
      array('url' => 'http://site-b.org/callback'), // expectTo

      new SiteServer(Examples::$ca, Examples::$siteB),
      NULL, // expectParseRequestException
      array('url' => 'http://app-b.com/callback', 'id' => 'appB-5678-5678-5678'), // expectFrom
    );

    // #3: Err, appB encodes for siteB... but delivers to appA
    $cases[] = array(
      new AppClient(Examples::$ca, Examples::$appB, Examples::$siteB),
      NULL, // expectCreateRequestException
      array('url' => 'http://site-b.org/callback'), // expectTo

      new AppServer(Examples::$ca, Examples::$appA),
      'Civi\Cxn\Rpc\Exception\InvalidUsageException', // expectParseRequestException
      NULL, // expectFrom
    );

    // #4: Err, siteA encodes for appA... but delivers to siteB
    $cases[] = array(
      new SiteClient(Examples::$ca, Examples::$siteA, Examples::$appA),
      NULL, // expectCreateRequestException
      array('url' => 'http://app-a.com/callback'), // expectTo

      new SiteServer(Examples::$ca, Examples::$siteB),
      'Civi\Cxn\Rpc\Exception\InvalidUsageException', // expectParseRequestException
      NULL, // expectFrom
    );

    // #5: Err, siteBadUrl sends a message to appA, which rejects it
    $cases[] = array(
      new SiteClient(Examples::$ca, Examples::$siteBadUrl, Examples::$appA),
      NULL, // expectCreateRequestException
      array('url' => 'http://app-a.com/callback'), // expectTo

      new AppServer(Examples::$ca, Examples::$appA),
      'Civi\Cxn\Rpc\Exception\InvalidDnUrlException', // expectParseRequestException
      NULL, // expectFrom
    );

    // #6: Err, siteBadId sends a message to appA, which rejects it
    $cases[] = array(
      new SiteClient(Examples::$ca, Examples::$siteBadId, Examples::$appA),
      NULL, // expectCreateRequestException
      array('url' => 'http://app-a.com/callback'), // expectTo

      new AppServer(Examples::$ca, Examples::$appA),
      'Civi\Cxn\Rpc\Exception\InvalidDnIdException', // expectParseRequestException
      NULL, // expectFrom
    );

    // Err, siteA => appA, but siteA was tricked into loading Mallory's appA cert!
    $cases[] = array(
      new SiteClient(Examples::$ca, Examples::$siteA, Examples::$malloryAppA),
      'Civi\Cxn\Rpc\Exception\InvalidCertException', // expectCreateRequestException
      NULL, // expectTo

      NULL, // server
      NULL, // expectParseRequestException
      NULL, // expectFrom
    );

    // Err, Mallory makes a fake site and tries to trick appA into doing something.
    $cases[] = array(
      new SiteClient(Examples::$malloryCa, Examples::$mallorySiteA, Examples::$appA, FALSE),
      NULL, // expectCreateRequestException
      array('url' => 'http://app-a.com/callback'), // expectTo

      new AppServer(Examples::$ca, Examples::$appA), // server
      'Civi\Cxn\Rpc\Exception\InvalidCertException', // expectParseRequestException
      NULL, // expectFrom
    );

    // Err, client is using an expired certificate.
    $cases[] = array(
      new SiteClient(Examples::$ca, Examples::$siteBadOld, Examples::$appA, FALSE),
      NULL, // expectCreateRequestException
      array('url' => 'http://app-a.com/callback'), // expectTo

      new AppServer(Examples::$ca, Examples::$appA), // server
      'Civi\Cxn\Rpc\Exception\ExpiredCertException', // expectParseRequestException
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
    $test = $this;

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

    // Server receives request and prepares response
    try {
      $respCiphertext = $server->handle($reqCiphertext, function ($identity, $entity, $action, $params) use ($expectParseRequestException, $expectFrom, $test) {
        // Parse exception would have fired already.
        $test->assertNull($expectParseRequestException);

        $test->assertEquals($expectFrom, array(
          'url' => $identity->getCallbackUrl(),
          'id' => $identity->getAgentId(),
        ));

        $test->assertEquals('entity-1', $entity);
        $test->assertEquals('action-1', $action);
        $test->assertEquals(array('foo' => 'bar'), $params);

        return array('field' => 'value-123');
      });
    }
    catch (\Exception $e) {
      if (empty($expectParseRequestException) || !($e instanceof $expectParseRequestException)) {
        throw $e;
      }
      //$this->assertInstanceOf($expectParseRequestException, $e);
      return;
    }

    // Server sends response
    $this->assertTrue(is_string($respCiphertext));

    // client receives response
    $response = $client->parseResponse($respCiphertext);
    $this->assertEquals('value-123', $response['field']);
  }

}
