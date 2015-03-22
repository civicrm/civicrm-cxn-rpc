<?php
namespace Civi\Cxn\Rpc;

use Civi\Cxn\Rpc\Exception\IdentityException;
use Civi\Cxn\Rpc\Exception\InvalidCertException;
use Civi\Cxn\Rpc\Exception\InvalidDnIdException;
use Civi\Cxn\Rpc\Exception\InvalidDnUrlException;
use Civi\Cxn\Rpc\Exception\InvalidUsageException;
use Civi\Cxn\Rpc\Exception\ExpiredCertException;

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
   * @return AppIdentity|SiteIdentity
   * @throws IdentityException
   */
  public static function loadCert($cert) {
    $x509 = new \File_X509();
    $x509->loadX509($cert);
    $usage = $x509->getExtension('id-ce-extKeyUsage');

    if (count($usage) != 1) {
      throw new InvalidUsageException("Certificate must include exactly one authorized usage.");
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
    $identity->cert = $cert;
    $identity->certX509 = $x509;

    return $identity;
  }

  /**
   * Load an identity from a set of files.
   *
   * @param string $prefix
   *   A base name shared by the files. For example, "/tmp/hello"
   *   would correspond to files "/tmp/hello.crt", "/tmp/hello.key",
   *   and "/tmp/hello.pub".
   * @return AgentIdentity
   */
  public static function loadFiles($prefix) {
    $cert = file_get_contents("$prefix.crt");
    $identity = static::loadCert($cert);
    $identity->keypair = array();
    foreach (array('publickey' => "$prefix.pub", 'privatekey' => "$prefix.key") as $name => $file) {
      if (file_exists($file)) {
        $identity->keypair[$name] = file_get_contents($file);
      }
    }

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
   * @throws IdentityException
   * @return static
   */
  public function validate(CaIdentity $ca) {
    if (!self::validateCallbackUrl($this->callbackUrl)) {
      throw new InvalidDnUrlException("Identity is invalid. Expected DN: CN={url},O={id}. Malformed URL.");
    }
    if (!self::validateAgentId($this->agentId)) {
      throw new InvalidDnIdException("Identity is invalid. Expected DN: CN={url},O={id}. Malformed ID.");
    }

    $x509 = new \File_X509();
    $x509->loadCA($ca->getCert());
    $x509->loadX509($this->getCert());
    if (!$x509->validateSignature()) {
      throw new InvalidCertException("Identity is invalid. Certificate is not signed by proper CA.");
    }
    if (!$x509->validateDate(Time::getTime())) {
      throw new ExpiredCertException("Identity is invalid. Certificate expired.");
    }
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
    return !empty($id) && preg_match('/^[a-zA-Z0-9\-_]+$/', $id) && strlen($id) >= Constants::AGENT_ID_MIN;
  }

  public function toArray() {
    $arr = parent::toArray();
    $arr['agentId'] = $this->agentId;
    $arr['callbackUrl'] = $this->callbackUrl;
    return $arr;
  }

  public function fromArray($arr) {
    parent::fromArray($arr);
    $this->agentId = $arr['agentId'];
    $this->callbackUrl = $arr['callbackUrl'];
  }

}
