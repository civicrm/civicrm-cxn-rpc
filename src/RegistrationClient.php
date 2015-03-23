<?php
namespace Civi\Cxn\Rpc;

class RegistrationClient {
  /**
   * @var string
   */
  protected $caCert;

  /**
   * @var CxnStore
   */
  protected $cxnStore;

  /**
   * @var string
   */
  protected $siteUrl;

  /**
   * @var HttpInterface
   */
  protected $http;

  /**
   * @param string $caCert
   * @param CxnStore $cxnStore
   */
  function __construct($caCert, $cxnStore, $siteUrl) {
    $this->caCert = $caCert;
    $this->cxnStore = $cxnStore;
    $this->siteUrl = $siteUrl;
  }

  /**
   * @param array $appMeta
   * @return array
   *   Array($cxnId, $isOk).
   */
  public function register($appMeta) {
    return $this->call($appMeta, 'Cxn', 'register', array());
  }

  /**
   * @param array $appMeta
   * @return array
   *   Array($cxnId, $isOk).
   */
  public function unregister($appMeta) {
    return $this->call($appMeta, 'Cxn', 'unregister', array());
  }

  /**
   * @param $appMeta
   * @return array
   * @throws Exception\ExpiredCertException
   * @throws Exception\InvalidCertException
   * @throws Exception\InvalidMessageException
   */
  protected function call($appMeta, $entity, $action, $params) {
    CA::validate($this->caCert, $appMeta['appCert']);

    $cxn = $this->cxnStore->getByAppId($appMeta['appId']);
    if (!$cxn) {
      $cxn = array(
        'cxnId' => base64_encode(crypt_random_string(Constants::CXN_ID_CHARS)),
        'secret' => base64_encode(crypt_random_string(Constants::AES_CHARS)),
        'appId' => $appMeta['appId'],
      );
    }
    $cxn['appUrl'] = $appMeta['appUrl'];
    $cxn['siteUrl'] = $this->siteUrl;
    $cxn['perm'] = $appMeta['perm'];
    $this->cxnStore->add($cxn);

    $appCert = new \File_X509();
    $appCert->loadX509($appMeta['appCert']);

    $reqCiphertext = Message::encodeCxn02Registration($cxn['appId'], $appCert->getPublicKey(), array(
      'cxn' => $cxn,
      'entity' => $entity,
      'action' => $action,
      'params' => $params,
    ));
    list($respHeaders, $respCiphertext, $respCode) = $this->http->send('POST', $cxn['appUrl'], $reqCiphertext);
    list ($respCxnId, $respData) = Message::decodeCxn02Aes($this->cxnStore, $respCiphertext);
    if ($respCxnId != $cxn['cxnId']) {
      // Tsk, tsk, Mallory!
      throw new \RuntimeException('Received response from incorrect connection.');
    }
    return array($cxn['cxnId'], $respCode == 200 && $respData['is_error'] == 0);
  }

  /**
   * @return HttpInterface
   */
  public function getHttp() {
    return $this->http;
  }

  /**
   * @param HttpInterface $http
   */
  public function setHttp($http) {
    $this->http = $http;
  }

}
