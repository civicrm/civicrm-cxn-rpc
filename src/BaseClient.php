<?php
namespace Civi\Cxn\Rpc;

use Civi\Cxn\Rpc\Exception\InvalidUsageException;

abstract class BaseClient implements ClientInterface {

  /**
   * @var BaseIdentity
   */
  protected $caIdentity;

  /**
   * @var AgentIdentity
   */
  protected $myIdentity;

  /**
   * @var AgentIdentity
   */
  protected $remoteIdentity;

  public function __construct($caIdentity, $myIdentity, $remoteIdentity) {
    $this->caIdentity = $caIdentity;
    $this->myIdentity = $myIdentity;
    $this->remoteIdentity = $remoteIdentity;

    if ($this->getMyExpectedCertUsage() != $this->myIdentity->getUsage()) {
      throw new InvalidUsageException("Cannot setup client. My certificate must have usage flag: " . $this->getMyExpectedCertUsage());
    }
    if ($this->getExpectedRemoteUsage() != $this->remoteIdentity->getUsage()) {
      throw new InvalidUsageException("Cannot setup client. Remote certificate must have usage flag: " . $this->getExpectedRemoteUsage());
    }
  }

  /**
   * @param string $entity
   *   Entity name.
   * @param string $action
   *   Action name.
   * @param array $params
   *   Array-tree parameters.
   * @return string
   *   Serialized request.
   */
  public function createRequest($entity, $action, $params) {
    $payload = json_encode(array(
        $this->myIdentity->getCert(),
        Time::getTime() + Constants::REQUEST_TTL,
        $entity,
        $action,
        $params,
      ));
    // FIXME encrypt $payload with $myPrivate and $remotePublic
    return $payload;
  }

  public function parseResponse($response) {
    // FIXME decrypt $response with $myPrivate and $remotePublic
    return json_decode($response, TRUE);
  }

  /**
   * @return string
   */
  public function getRemoteUrl() {
    return $this->remoteIdentity->getCallbackUrl();
  }

  /**
   * @param string $entity
   *   Entity name.
   * @param string $action
   *   Action name.
   * @param array $params
   *   Array-tree parameters.
   * @return array
   *   Response.
   */
  public function sendRequest($entity, $action, $params) {
    $request = $this->createRequest($entity, $action, $params);
    if (TRUE) {
      throw new \RuntimeException("TODO: Connect to " . $this->getRemoteUrl());
    }
    $response = '';
    return $this->parseResponse($response);
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
