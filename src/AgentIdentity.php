<?php
namespace Civi\Cxn\Rpc;

use Civi\Cxn\Rpc\Exception\IdentityException;
use Civi\Cxn\Rpc\Exception\InvalidDnException;
use Civi\Cxn\Rpc\Exception\InvalidUsageException;

class AgentIdentity extends BaseIdentity {

  protected $agentId;

  protected $callbackUrl;

  /**
   * @param CaIdentity $ca
   * @param string $agentId
   * @param string $callbackUrl
   * @param string $usage
   * @return static
   */
  protected static function createHelper(CaIdentity $ca, $agentId, $callbackUrl, $usage) {
    $identity = new static();
    $identity->agentId = $agentId;
    $identity->callbackUrl = $callbackUrl;
    $identity->keypair = $identity->createKeypair();
    $csr = $identity->createCSR($agentId, $callbackUrl);
    $identity->cert = $ca->signCSR($csr, $usage);
    return $identity;
  }

  /**
   * @param string $cert
   *   Serialized certificate.
   * @param string $expectUsage
   *   The extended X.509 usage which is expected.
   * @return AppIdentity|SiteIdentity
   * @throws IdentityException
   */
  public static function load($cert, $expectUsage) {
    $x509 = new \File_X509();
    $x509->loadX509($cert);
    $usage = $x509->getExtension('id-ce-extKeyUsage');

    if (count($usage) != 1) {
      throw new Exception\InvalidUsageException("Certificate must include exactly one authorized usage.");
    }

    if ($expectUsage !== $usage[0]) {
      throw new InvalidUsageException("Certificate presents incorrect usage. Expected: $expectUsage");
    }

    switch ($usage[0]) {
      case AppIdentity::X509_USAGE:
        $identity = new AppIdentity();
        break;

      case SiteIdentity::X509_USAGE:
        $identity = new SiteIdentity();
        break;

      default:
        throw new IdentityException("Certificate specifies unknown usage.");
    }
    list($identity->callbackUrl) = $x509->getDNProp('commonName');
    list($identity->agentId) = $x509->getDNProp('id-at-organizationName');

    return $identity;
  }

  /**
   * @return string
   */
  public function getCallbackUrl() {
    return $this->callbackUrl;
  }

  /**
   * @return string
   */
  public function getAgentId() {
    return $this->agentId;
  }

  /**
   * @param CaIdentity $ca
   * @throws InvalidDnException
   * @return static
   */
  public function validate(CaIdentity $ca) {
    if (!self::validateCallbackUrl($this->callbackUrl)) {
      throw new InvalidDnException("Identity is invalid. Expected DN: CN={url},O={id}. Malformed URL.");
    }
    if (!self::validateAgentId($this->agentId)) {
      throw new InvalidDnException("Identity is invalid. Expected DN: CN={url},O={id}. Malformed ID.");
    }
    // FIXME validate against $caIdentity and expiration date
    return $this;
  }

  /**
   * Ensure that a callback URL is well-formed
   *
   * @param string $url
   * @return bool
   *   TRUE if valid.
   */
  protected static function validateCallbackUrl($url) {
    if (empty($url)) {
      return FALSE;
    }
    if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
      return FALSE;
    }
    if (!preg_match('/^(http|https):/', $url)) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * @param string $id
   * @return bool
   */
  protected static function validateAgentId($id) {
    return !empty($id) && preg_match('/^[a-zA-Z0-9\-_]+$/', $id) && strlen($id) > Constants::AGENT_ID_MIN;
  }

}
