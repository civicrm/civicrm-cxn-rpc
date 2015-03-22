<?php
namespace Civi\Cxn\Rpc;

/**
 * Class Agent
 *
 * An agent is a component which has an identity and exchanges messages.
 *
 * @package Civi\Cxn\Rpc
 */
abstract class Agent {
  /**
   * @var CaIdentity
   */
  protected $caIdentity;

  /**
   * @var AgentIdentity
   */
  protected $myIdentity;

  /**
   * @var bool
   */
  protected $enableValidation;

  public function __construct(CaIdentity $caIdentity, AgentIdentity $myIdentity, $enableValidation = TRUE) {
    $this->caIdentity = $caIdentity;
    $this->myIdentity = $myIdentity;
    $this->enableValidation = $enableValidation;

    if ($this->getMyExpectedCertUsage() != $this->myIdentity->getUsage()) {
      throw new Exception\InvalidUsageException("Cannot setup server. My certificate must have usage flag: " . $this->getMyExpectedCertUsage());
    }
  }

  /**
   * @param array $data
   * @return string
   */
  public function createMessage($data, $remoteIdentity) {
    return Message::encode($this->caIdentity, $this->myIdentity, $remoteIdentity, $this->getEnableValidation(), $data);
  }

  /**
   * @param string $ciphertext
   *   Serialized request.
   * @return array
   *   Array(0 => AgentIdentity $remoteIdentity, 1=> $reqData).
   */
  public function parseMessage($ciphertext) {
    return Message::decode($this->caIdentity, $this->myIdentity, $this->getExpectedRemoteUsage(), $ciphertext);
  }

  /**
   * @return bool
   */
  public function getEnableValidation() {
    return $this->enableValidation;
  }

  /**
   * @param bool $enableValidation
   */
  public function setEnableValidation($enableValidation) {
    $this->enableValidation = $enableValidation;
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
