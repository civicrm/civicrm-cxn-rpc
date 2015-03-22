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

  /**
   * @var bool
   */
  protected $enableValidation;


  public function __construct($caIdentity, $myIdentity, $remoteIdentity, $enableValidation = TRUE) {
    $this->caIdentity = $caIdentity;
    $this->myIdentity = $myIdentity;
    $this->remoteIdentity = $remoteIdentity;
    $this->enableValidation = $enableValidation;

    if ($this->getMyExpectedCertUsage() != $this->myIdentity->getUsage()) {
      throw new InvalidUsageException("Cannot setup client. My certificate must have usage flag: " . $this->getMyExpectedCertUsage());
    }
    if ($this->getExpectedRemoteUsage() != $this->remoteIdentity->getUsage()) {
      throw new InvalidUsageException("Cannot setup client. Remote certificate must have usage flag: " . $this->getExpectedRemoteUsage());
    }
  }

  /**
   * @param array $data
   *   Array-tree.
   * @return string
   *   Serialized request.
   */
  public function createRequest($data) {
    $payload = json_encode(array(
      $this->myIdentity->getCert(),
      Time::getTime() + Constants::REQUEST_TTL,
      $data,
    ));
    if ($this->getEnableValidation()) {
      $this->remoteIdentity->validate($this->caIdentity);
    }

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
   * @param array $data
   *   Array-tree.
   * @return array
   *   Response.
   */
  public function sendRequest($data) {
    $request = $this->createRequest($data);
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


  /**
   * @return boolean
   */
  public function getEnableValidation() {
    return $this->enableValidation;
  }

  /**
   * @param boolean $enableValidation
   */
  public function setEnableValidation($enableValidation) {
    $this->enableValidation = $enableValidation;
  }
}
