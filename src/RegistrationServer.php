<?php
namespace Civi\Cxn\Rpc;

use Civi\Cxn\Rpc\Exception\CxnException;
use Civi\Cxn\Rpc\Exception\InvalidMessageException;
use Civi\Cxn\Rpc\Message\InsecureMessage;
use Civi\Cxn\Rpc\Message\RegistrationMessage;
use Civi\Cxn\Rpc\Message\StdMessage;
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
   * @return Message
   */
  public function handle($blob) {
    try {
      $messages = new Messages($this->appMeta['appId'], $this->keyPair['privatekey'], $this->cxnStore);
      $reqData = $messages->decode(array(RegistrationMessage::NAME), $blob);
    }
    catch (InvalidMessageException $e) {
      $this->log->debug('Received invalid message', array(
        'exception' => $e,
      ));
      $resp = new InsecureMessage(array('is_error' => 1, 'error_message' => 'Invalid message coding'));
      return $resp->setCode(400);
    }

    $this->log->debug('Received registration request', array(
      'reqData' => $reqData,
    ));
    $cxn = $reqData['cxn'];
    $validation = Cxn::getValidationMessages($cxn);
    if (!empty($validation)) {
      // $cxn is not valid, so we can't encode it use it for encoding.
      $resp = new InsecureMessage(array(
        'is_error' => 1,
        'error_message' => 'Invalid cxn details: ' . implode(', ', array_keys($validation)),
      ));
      return $resp->setCode(400);
    }

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
    return new StdMessage($cxn['cxnId'], $cxn['secret'], $respData);
  }

  /**
   * Parse the ciphertext, process it, send the response, and exit.
   *
   * @param string $blob
   *   POST'ed ciphertext.
   */
  public function handleAndRespond($blob) {
    list ($headers, $blob, $code) = $this->handle($blob)->toHttp();
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
