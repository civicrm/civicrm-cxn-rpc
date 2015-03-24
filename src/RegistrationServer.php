<?php
namespace Civi\Cxn\Rpc;

use Civi\Cxn\Rpc\Exception\CxnException;
use Psr\Log\NullLogger;

class RegistrationServer {

  protected $appMeta;
  protected $keyPair;
  protected $cxnStore;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $log;

  /**
   * @param array $appMeta
   * @param array $keyPair
   * @param CxnStore\CxnStoreInterface $cxnStore
   */
  public function __construct($appMeta, $keyPair, $cxnStore) {
    AppMeta::validate($appMeta);
    if (empty($keyPair)) {
      throw new CxnException("Missing keyPair");
    }
    if (empty($keyPair)) {
      throw new CxnException("Missing cxnStore");
    }

    $this->appMeta = $appMeta;
    $this->keyPair = $keyPair;
    $this->cxnStore = $cxnStore;
    $this->log = new NullLogger();
  }

  /**
   * Parse the ciphertext, process it, and return the response.
   *
   * FIXME Catch exceptions and return in a nice format.
   *
   * @param string $blob
   *   POST'ed ciphertext.
   * @return array
   *   array($headers, $blob, $code)
   */
  public function handle($blob) {
    $reqData = Message::decodeCxn02Registration($this->appMeta['appId'], $this->keyPair['privatekey'], $blob);
    $this->log->debug('Received registration request', array(
      'reqData' => $reqData,
    ));
    $cxn = $reqData['cxn'];
    Cxn::validate($cxn);

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

  /**
   * Parse the ciphertext, process it, send the response, and exit.
   *
   * @param string $blob
   *   POST'ed ciphertext.
   */
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
   * @return CxnStore\CxnStoreInterface
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
   * @return \Psr\Log\LoggerInterface
   */
  public function getLog() {
    return $this->log;
  }

  /**
   * @param \Psr\Log\LoggerInterface $log
   */
  public function setLog($log) {
    $this->log = $log;
  }

  /**
   * Callback for Cxn.register.
   *
   * @param array $cxn
   *   The CXN record submitted by the client.
   * @param array $params
   *   Additional parameters from the client.
   * @return array
   */
  public function onCxnRegister($cxn, $params) {
    $storedCxn = $this->cxnStore->getByCxnId($cxn['cxnId']);

    if (!$storedCxn || $storedCxn['secret'] == $cxn['secret']) {
      $this->log->info('Register cxnId="{cxnId}": OK', array(
        'cxnId' => $cxn['cxnId'],
      ));
      $this->cxnStore->add($cxn);
      return array(
        'is_error' => 0,
      );
    }
    else {
      $this->log->info('Register cxnId="{cxnId}": Secret does not match.', array(
        'cxnId' => $cxn['cxnId'],
      ));
      return array(
        'is_error' => 1,
        'error_message' => 'Secret does not match previous registration.',
      );
    }
  }

  /**
   * Callback for Cxn.unregister.
   *
   * @param array $cxn
   *   The CXN record submitted by the client.
   * @param array $params
   *   Additional parameters from the client.
   * @return array
   */
  public function onCxnUnregister($cxn, $params) {
    $storedCxn = $this->cxnStore->getByCxnId($cxn['cxnId']);
    if (!$storedCxn) {
      $this->log->info('Unregister cxnId="{cxnId}": Non-existent', array(
        'cxnId' => $cxn['cxnId'],
      ));
      return array(
        'is_error' => 0,
      );
    }
    elseif ($storedCxn['secret'] == $cxn['secret']) {
      $this->log->info('Unregister cxnId="{cxnId}: OK"', array(
        'cxnId' => $cxn['cxnId'],
      ));
      $this->cxnStore->remove($cxn['cxnId']);
      return array(
        'is_error' => 0,
      );
    }
    else {
      $this->log->info('Unregister cxnId="{cxnId}": Secret does not match.', array(
        'cxnId' => $cxn['cxnId'],
      ));

      return array(
        'is_error' => 1,
        'error_message' => 'Incorrect cxnId or secret.',
      );
    }
  }

}
