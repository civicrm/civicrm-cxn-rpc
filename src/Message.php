<?php
namespace Civi\Cxn\Rpc;

use Civi\Cxn\Rpc\Exception\InvalidMessageException;
use Civi\Cxn\Rpc\Exception\InvalidSigException;
use Civi\Cxn\Rpc\Exception\UserErrorException;

class Message {
  public static function encode(CaIdentity $caIdentity, AgentIdentity $myIdentity, AgentIdentity $remoteIdentity, $validate, $data) {
    $envelope = array(
      'crt' => $myIdentity->getCert(),
      'ttl' => Time::getTime() + Constants::REQUEST_TTL,
      'r' => json_encode($data),
    );
    if ($validate) {
      $remoteIdentity->validate($caIdentity);
    }
    $envelope['sig'] = base64_encode($myIdentity->getRsaKey('privatekey')->sign($envelope['ttl'] . ':' . $envelope['r']));

    return $remoteIdentity->getRsaKey('publickey')->encrypt(json_encode($envelope));
  }

  /**
   * @param CaIdentity $caIdentity
   * @param AgentIdentity $myIdentity
   * @param string $expectedRemoteUsage
   * @param string $ciphertext
   * @return array
   *   Array(0 => $remoteIdentity, 1 => $data)
   */
  public static function decode(CaIdentity $caIdentity, AgentIdentity $myIdentity, $expectedRemoteUsage, $ciphertext) {
    $plaintext = UserErrorException::adapt(function () use ($ciphertext, $myIdentity) {
      return $myIdentity->getRsaKey('privatekey')->decrypt($ciphertext);
    });
    $envelope = json_decode($plaintext, TRUE);
    if (empty($envelope)) {
      throw new InvalidMessageException("Failed to decrypt an envelope");
    }

    if (Time::getTime() > $envelope['ttl']) {
      throw new InvalidMessageException("Invalid request: expired");
    }

    $remoteIdentity = AgentIdentity::loadCert($envelope['crt']);
    if ($expectedRemoteUsage !== $remoteIdentity->getUsage()) {
      throw new InvalidUsageException("Certificate presents incorrect usage. Expected: " . $expectedRemoteUsage);
    }
    $remoteIdentity->validate($caIdentity);

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
}
