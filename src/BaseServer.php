<?php
namespace Civi\Cxn\Rpc;

use Civi\Cxn\Rpc\Exception\IdentityException;
use Civi\Cxn\Rpc\Exception\InvalidRequestException;
use Civi\Cxn\Rpc\Exception\InvalidUsageException;

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
   *   Array(0 => AgentIdentity $remoteIdentity, 1=> $entity, 2 => $action, 4 => $params).
   * @throws IdentityException
   * @throws InvalidRequestException
   */
  public function parseRequest($request) {
    list ($remoteCert, $expires, $entity, $action, $params) = json_decode($request, TRUE);
    if (Time::getTime() > $expires) {
      throw new InvalidRequestException("Invalid request: expired");
    }
    $remoteIdentity = AgentIdentity::load($remoteCert);
    if ($this->getExpectedRemoteUsage() !== $remoteIdentity->getUsage()) {
      throw new InvalidUsageException("Certificate presents incorrect usage. Expected: " . $this->getExpectedRemoteUsage());
    }

    $remoteIdentity->validate($this->caIdentity);
    return array($remoteIdentity, $entity, $action, $params);
  }

  /**
   * @param array $data
   * @return string
   */
  public function createResponse($data, $remoteIdentity) {
    $payload = json_encode($data);
    // FIXME encrypt $payload with $myPrivate and $remotePublic
    return $payload;
  }

  /**
   * Parse a request and pass it to a function for execution.
   *
   * @param string $request
   *   Serialized request.
   * @param callable $callable
   *   Function(AgentIdentity $remoteIdentity, string $entity, string $action, array $params).
   * @return string
   *   Serialized response.
   */
  public function handle($request, $callable) {
    // FIXME: format exceptions
    list ($parsedIdentity, $parsedEntity, $parsedAction, $parsedParams) = $this->parseRequest($request);
    $response = call_user_func($callable, $parsedIdentity, $parsedEntity, $parsedAction, $parsedParams);
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
