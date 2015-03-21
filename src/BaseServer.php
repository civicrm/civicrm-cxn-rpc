<?php
namespace Civi\Cxn\Rpc;

use Civi\Cxn\Rpc\Exception\InvalidRequestException;

abstract class BaseServer {

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
   * @return array
   *   Array(0 => AgentIdentity $remoteIdentity, 1=> $entity, 2 => $action, 4 => $params).
   * @throws Exception\IdentityException
   * @throws InvalidRequestException
   */
  public function parseRequest($request) {
    list ($remoteCert, $expires, $entity, $action, $params) = json_decode($request, TRUE);
    if (Time::getTime() > $expires) {
      throw new InvalidRequestException("Invalid request: expired");
    }
    $remoteIdentity = AgentIdentity::load($remoteCert, $this->getExpectedRemoteUsage());
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
