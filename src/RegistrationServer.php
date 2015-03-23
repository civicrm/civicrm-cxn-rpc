<?php
namespace Civi\Cxn\Rpc;

class RegistrationServer {

  protected $appMeta;
  protected $keyPair;
  protected $cxnStore;

  /**
   * @param array $appMeta
   * @param array $keyPair
   * @param CxnStoreInterface $cxnStore
   */
  public function __construct($appMeta, $keyPair, $cxnStore) {
    $this->appMeta = $appMeta;
    $this->keyPair = $keyPair;
    $this->cxnStore = $cxnStore;
  }

  /**
   * @param $blob
   * @return array
   *   array($headers, $blob, $code)
   */
  public function handle($blob) {
    $reqData = Message::decodeCxn02Registration($this->appMeta['appId'], $this->keyPair['privatekey'], $blob);
    $cxn = $reqData['cxn'];

    $respData = array(
      'is_error' => 1,
      'error_message' => 'Unrecognized entity or action',
    );

    if ($reqData['entity'] == 'Cxn' && preg_match('/^[a-zA-Z]+$/', $reqData['action'])) {
      $func = 'on' . $reqData['entity'] . strtoupper($reqData['action']{0}) . substr($reqData['action'], 1);
      if (is_callable(array($this, $func))) {
        $respData = call_user_func(array($this, $func), $reqData['cxn'], $reqData['params']);
      }
    }
    $tuple = array(
      array(), //headers
      Message::encodeCxn02Aes($cxn['cxnId'], $cxn['secret'], $respData),
      200, // code
    );
    return $tuple;
  }

  public function handleAndRespond($blob) {
    list ($headers, $blob, $code) = $this->handle($blob);
    header("X-PHP-Response-Code: $code", TRUE, $code);
    foreach ($headers as $n => $v) {
      header("$n: $v");
    }
    echo $blob;
    exit();
  }

  /**
   * @return array
   */
  public function getAppMeta() {
    return $this->appMeta;
  }

  /**
   * @return CxnStoreInterface
   */
  public function getCxnStore() {
    return $this->cxnStore;
  }

  /**
   * @return array
   */
  public function getKeyPair() {
    return $this->keyPair;
  }

  /**
   * Callback for Cxn.register.
   *
   * @param array $cxn
   *   The CXN record submitted by the client.
   * @param array $params
   *   Additional parameters from the client.
   */
  public function onCxnRegister($cxn, $params) {
    $this->cxnStore->add($cxn);
    return array(
      'is_error' => 0,
    );
  }

  /**
   * Callback for Cxn.unregister.
   *
   * @param array $cxn
   *   The CXN record submitted by the client.
   * @param array $params
   *   Additional parameters from the client.
   */
  public function onCxnUnregister($cxn, $params) {
    $storedCxn = $this->cxnStore->getByCxnId($cxn['cxnId']);
    if ($storedCxn && $storedCxn['secret'] == $cxn['secret']) {
      $this->cxnStore->remove($cxn['cxnId']);
      return array(
        'is_error' => 0,
      );
    }
    else {
      return array(
        'is_error' => 1,
        'error_message' => 'Incorrect cxnId or secret.',
      );
    }
  }

}