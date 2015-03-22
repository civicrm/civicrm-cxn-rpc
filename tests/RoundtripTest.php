<?php
namespace Civi\Cxn\Rpc;

use Civi\Cxn\Rpc\Exception\InvalidSigException;

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
      'Civi\Cxn\Rpc\Exception\InvalidUsageException',
    );
    $cases[] = array(
      'Civi\Cxn\Rpc\SiteServer',
      Examples::$ca,
      Examples::$appA,
      NULL,
      'Civi\Cxn\Rpc\Exception\InvalidUsageException',
    );
    $cases[] = array(
      'Civi\Cxn\Rpc\AppClient',
      Examples::$ca,
      Examples::$siteA,
      Examples::$siteA,
      'Civi\Cxn\Rpc\Exception\InvalidUsageException',
    );
    $cases[] = array(
      'Civi\Cxn\Rpc\AppClient',
      Examples::$ca,
      Examples::$appA,
      Examples::$appA,
      'Civi\Cxn\Rpc\Exception\InvalidUsageException',
    );
    $cases[] = array(
      'Civi\Cxn\Rpc\SiteClient',
      Examples::$ca,
      Examples::$siteA,
      Examples::$siteA,
      'Civi\Cxn\Rpc\Exception\InvalidUsageException',
    );
    $cases[] = array(
      'Civi\Cxn\Rpc\SiteClient',
      Examples::$ca,
      Examples::$appA,
      Examples::$appA,
      'Civi\Cxn\Rpc\Exception\InvalidUsageException',
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

    $cases[] = array(
      'OK: siteA => appA', // description
      new SiteClient(Examples::$ca, Examples::$siteA, Examples::$appA),
      NULL, // expectCreateRequestException
      array('url' => 'http://app-a.com/callback'), // expectTo

      new AppServer(Examples::$ca, Examples::$appA),
      NULL, // expectParseRequestException
      array('url' => 'http://site-a.org/callback', 'id' => 'siteA-asdf-asdf-asdf'), // expectFrom
    );

    $cases[] = array(
      'OK: siteB => appA', // description
      new SiteClient(Examples::$ca, Examples::$siteB, Examples::$appA),
      NULL, // expectCreateRequestException
      array('url' => 'http://app-a.com/callback'), // expectTo

      new AppServer(Examples::$ca, Examples::$appA),
      NULL, // expectParseRequestException
      array('url' => 'http://site-b.org/callback', 'id' => 'siteB-fdsa-fdsa-fdsa'), // expectFromUrl
    );

    $cases[] = array(
      'OK: appB => siteB', // description
      new AppClient(Examples::$ca, Examples::$appB, Examples::$siteB),
      NULL, // expectCreateRequestException
      array('url' => 'http://site-b.org/callback'), // expectTo

      new SiteServer(Examples::$ca, Examples::$siteB),
      NULL, // expectParseRequestException
      array('url' => 'http://app-b.com/callback', 'id' => 'appB-5678-5678-5678'), // expectFrom
    );

    $cases[] = array(
      'Error: siteA encodes valid message for appA... but delivers to appB', // description
      new SiteClient(Examples::$ca, Examples::$siteA, Examples::$appA),
      NULL, // expectCreateRequestException
      array('url' => 'http://app-a.com/callback'), // expectTo

      new AppServer(Examples::$ca, Examples::$appB),
      'Civi\Cxn\Rpc\Exception\UserErrorException', // expectParseRequestException
      NULL, // expectFrom
    );

    $cases[] = array(
      'Error: appB encodes for siteB... but delivers to appA', // description
      new AppClient(Examples::$ca, Examples::$appB, Examples::$siteB),
      NULL, // expectCreateRequestException
      array('url' => 'http://site-b.org/callback'), // expectTo

      new AppServer(Examples::$ca, Examples::$appA),
      'Civi\Cxn\Rpc\Exception\UserErrorException', // expectParseRequestException
      NULL, // expectFrom
    );

    $cases[] = array(
      'Error: siteA encodes for appA... but delivers to siteB', // description
      new SiteClient(Examples::$ca, Examples::$siteA, Examples::$appA),
      NULL, // expectCreateRequestException
      array('url' => 'http://app-a.com/callback'), // expectTo

      new SiteServer(Examples::$ca, Examples::$siteB),
      'Civi\Cxn\Rpc\Exception\UserErrorException', // expectParseRequestException
      NULL, // expectFrom
    );

    $cases[] = array(
      'Error: siteBadUrl sends a message to appA, which rejects it', // description
      new SiteClient(Examples::$ca, Examples::$siteBadUrl, Examples::$appA),
      NULL, // expectCreateRequestException
      array('url' => 'http://app-a.com/callback'), // expectTo

      new AppServer(Examples::$ca, Examples::$appA),
      'Civi\Cxn\Rpc\Exception\InvalidDnUrlException', // expectParseRequestException
      NULL, // expectFrom
    );

    $cases[] = array(
      'Error: siteBadId sends a message to appA, which rejects it', // description
      new SiteClient(Examples::$ca, Examples::$siteBadId, Examples::$appA),
      NULL, // expectCreateRequestException
      array('url' => 'http://app-a.com/callback'), // expectTo

      new AppServer(Examples::$ca, Examples::$appA),
      'Civi\Cxn\Rpc\Exception\InvalidDnIdException', // expectParseRequestException
      NULL, // expectFrom
    );

    $cases[] = array(
      'Error: siteA => appA, but siteA was tricked into loading Mallory\'s appA cert!',
      new SiteClient(Examples::$ca, Examples::$siteA, Examples::$malloryAppA),
      'Civi\Cxn\Rpc\Exception\InvalidCertException', // expectCreateRequestException
      NULL, // expectTo

      NULL, // server
      NULL, // expectParseRequestException
      NULL, // expectFrom
    );

    $cases[] = array(
      'Error: Mallory makes a fake site and tries to trick appA into doing something.',
      new SiteClient(Examples::$malloryCa, Examples::$mallorySiteA, Examples::$appA, FALSE),
      NULL, // expectCreateRequestException
      array('url' => 'http://app-a.com/callback'), // expectTo

      new AppServer(Examples::$ca, Examples::$appA), // server
      'Civi\Cxn\Rpc\Exception\InvalidCertException', // expectParseRequestException
      NULL, // expectFrom
    );

    $cases[] = array(
      'Error: client is using an expired certificate.', // description
      new SiteClient(Examples::$ca, Examples::$siteBadOld, Examples::$appA, FALSE),
      NULL, // expectCreateRequestException
      array('url' => 'http://app-a.com/callback'), // expectTo

      new AppServer(Examples::$ca, Examples::$appA), // server
      'Civi\Cxn\Rpc\Exception\ExpiredCertException', // expectParseRequestException
      NULL, // expectFrom
    );

    return $cases;
  }

  /**
   * Test in which a client connects to a server.
   *
   * @dataProvider roundtripExamples
   * @param string $desc
   *   Description of this test case. Non-functional, but good for reviewing test output.
   * @param BaseClient $client
   * @param \Exception|NULL $expectCreateRequestException
   * @param array $expectTo
   * @param BaseServer $server
   * @param \Exception|NULL $expectParseRequestException
   * @param array $expectFrom
   * @throws \Exception
   */
  public function testRoundtrip($desc, $client, $expectCreateRequestException, $expectTo, $server, $expectParseRequestException, $expectFrom) {
    $test = $this;

    // Client prepares request
    try {
      $reqCiphertext = $client->createRequest(array(
        'entity-1',
        'action-1',
        array(
          'foo' => 'bar',
        ),
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
      $respCiphertext = $server->handle($reqCiphertext, function ($identity, $data) use ($expectParseRequestException, $expectFrom, $test) {
        list ($entity, $action, $params) = $data;
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

  /**
   * Send a message from siteA to appA, but munge the data along the way.
   */
  public function testInvalidSig() {
    $client = new SiteClient(Examples::$ca, Examples::$siteA, Examples::$appA);
    $server = new AppServer(Examples::$ca, Examples::$appA);

    // Prepare a proper message
    $origCiphertext = $client->createRequest(array(
      'entity-1',
      'action-1',
      array(
        'foo' => 'bar',
      ),
    ));

    // That messag looks OK...
    $server->parseRequest($origCiphertext);

    // But what happens if MitM munges the data?
    $envelope = json_decode(Examples::$appA->getRsaKey('privatekey')->decrypt($origCiphertext), TRUE);
    $envelope['r'] = json_encode(array('muahahaha'));
    $newCiphertext = Examples::$appA->getRsaKey('publickey')->encrypt(json_encode($envelope));

    // Now try processing
    try {
      $server->parseRequest($newCiphertext);
      $this->fail('Expected InvalidSigException');
    }
    catch (InvalidSigException $e) {
      // OK!
    }

  }

}
