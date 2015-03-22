<?php
namespace Civi\Cxn\Rpc;

use Civi\Cxn\Rpc\Exception\IdentityException;
use Civi\Cxn\Rpc\Exception\InvalidMessageException;

abstract class BaseServer implements ServerInterface {

  /**
   * @var CaIdentity
   */
  protected $caIdentity;

  /**
   * @var AgentIdentity
   */
  protected $myIdentity;

  public function __construct(CaIdentity $caIdentity, AgentIdentity $myIdentity) {
    $this->caIdentity = $caIdentity;
    $this->myIdentity = $myIdentity;
    if ($this->getMyExpectedCertUsage() != $this->myIdentity->getUsage()) {
      throw new Exception\InvalidUsageException("Cannot setup server. My certificate must have usage flag: " . $this->getMyExpectedCertUsage());
    }
  }

  /**
   * @param string $request
   *   Serialized request.
   * @return array
   *   Array(0 => AgentIdentity $remoteIdentity, 1=> $reqData).
   * @throws IdentityException
   * @throws InvalidMessageException
   */
  public function parseRequest($request) {
    return Message::decode($this->caIdentity, $this->myIdentity, $this->getExpectedRemoteUsage(), $request);
  }

  /**
   * @param array $data
   * @return string
   */
  public function createResponse($data, $remoteIdentity) {
    return Message::encode($this->caIdentity, $this->myIdentity, $remoteIdentity, TRUE, $data);
  }

  /**
   * Parse a request and pass it to a function for execution.
   *
   * @param string $request
   *   Serialized request.
   * @param callable $callable
   *   Function(AgentIdentity $remoteIdentity, array $data).
   * @return string
   *   Serialized response.
   * @throws \Exception
   */
  public function handle($request, $callable) {
    // FIXME: format exceptions
    list ($parsedIdentity, $payload) = $this->parseRequest($request);
    $response = call_user_func($callable, $parsedIdentity, $payload);
    return $this->createResponse($response, $parsedIdentity);
  }

  /**
   * @return string
   *   The extendendUsage attribute which should be present on my certificate.
   */
  abstract protected function getMyExpectedCertUsage();

  /**
   * @return string
   *   The extendendUsage attribute which should be present on my certificate.
   */
  abstract protected function getExpectedRemoteUsage();

}
