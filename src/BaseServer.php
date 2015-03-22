<?php
namespace Civi\Cxn\Rpc;

use Civi\Cxn\Rpc\Exception\IdentityException;
use Civi\Cxn\Rpc\Exception\InvalidRequestException;
use Civi\Cxn\Rpc\Exception\InvalidSigException;
use Civi\Cxn\Rpc\Exception\InvalidUsageException;
use Civi\Cxn\Rpc\Exception\UserErrorException;

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
   * @throws InvalidRequestException
   */
  public function parseRequest($request) {
    $plaintext = UserErrorException::adapt(function () use (&$request) {
      return $this->myIdentity->getRsaKey('privatekey')->decrypt($request);
    });
    $envelope = json_decode($plaintext, TRUE);
    if (empty($envelope)) {
      throw new InvalidRequestException("Failed to decrypt an envelope");
    }

    if (Time::getTime() > $envelope['ttl']) {
      throw new InvalidRequestException("Invalid request: expired");
    }

    $remoteIdentity = AgentIdentity::load($envelope['crt']);
    if ($this->getExpectedRemoteUsage() !== $remoteIdentity->getUsage()) {
      throw new InvalidUsageException("Certificate presents incorrect usage. Expected: " . $this->getExpectedRemoteUsage());
    }
    $remoteIdentity->validate($this->caIdentity);

    $verify = UserErrorException::adapt(function() use ($remoteIdentity, $envelope) {
      return $remoteIdentity
        ->getRsaKey('publickey')
        ->verify(
          $envelope['ttl'] . ':' . $envelope['r'],
          base64_decode($envelope['sig']
          )
        );
    });
    if (!$verify) {
      throw new InvalidSigException("Envelope signature is invalid.");
    }

    $reqData = json_decode($envelope['r'], TRUE);
    return array($remoteIdentity, $reqData);
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
