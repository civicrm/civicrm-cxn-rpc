<?php
namespace Civi\Cxn\Rpc;

use Civi\Cxn\Rpc\Exception\InvalidMessageException;
use Civi\Cxn\Rpc\Exception\InvalidUsageException;

abstract class BaseClient extends Agent implements ClientInterface {

  /**
   * @var AgentIdentity
   */
  protected $remoteIdentity;

  public function __construct($caIdentity, $myIdentity, $remoteIdentity, $enableValidation = TRUE) {
    parent::__construct($caIdentity, $myIdentity, $enableValidation);

    $this->remoteIdentity = $remoteIdentity;
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
    return $this->createMessage($data, $this->remoteIdentity);
  }

  /**
   * @param string $ciphertext
   *   Serialized request.
   * @return array
   *   Array(0 => AgentIdentity $remoteIdentity, 1=> $reqData).
   */
  public function parseResponse($ciphertext) {
    list ($remoteIdentity, $response) = $this->parseMessage($ciphertext);
    if ($this->remoteIdentity->getAgentId() != $remoteIdentity->getAgentId()) {
      throw new InvalidMessageException("Message contains incorrect agent ID.");
    }
    return array($remoteIdentity, $response);
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
    $opts = array(
      'http' => array(
        'method' => 'POST',
        'header' => 'Content-type: application/x-civi-cxn',
        'content' => $this->createRequest($data),
      ),
    );
    $context = stream_context_create($opts);
    $respCiphertext = file_get_contents($this->getRemoteUrl(), FALSE, $context);
    list ($remoteIdentity, $respData) = $this->parseResponse($respCiphertext);
    return $respData;
  }

}
