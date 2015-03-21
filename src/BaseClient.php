<?php
namespace Civi\Cxn\Rpc;

abstract class BaseClient {


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
  }

  /**
   * @param string $entity
   * @param string $action
   * @param array $params
   * @return string
   *   Serialized request.
   */
  public function createRequest($entity, $action, $params) {
    $payload = json_encode(array($this->myIdentity->getCert(), Time::getTime() + Constants::REQUEST_TTL, $entity, $action, $params));
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
   * @param string $action
   * @param array $params
   * @return array
   *   Response.
   */
  public function sendRequest($entity, $action, $params) {
    $request = $this->createRequest($entity, $action, $params);
    throw new \RuntimeException("TODO: Connect to " . $this->getRemoteUrl());
    // return $this->parseResponse($response);
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
