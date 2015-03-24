<?php
namespace Civi\Cxn\Rpc;

use Psr\Log\NullLogger;

class ApiServer {

  /**
   * @var CxnStore\CxnStoreInterface
   */
  protected $cxnStore;

  /**
   * @var callable
   */
  protected $router;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $log;

  /**
   * @param array $appMeta
   * @param CxnStore\CxnStoreInterface $cxnStore
   */
  public function __construct($cxnStore, $router = NULL) {
    $this->cxnStore = $cxnStore;
    $this->router = $router;
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
   *   Array($headers, $blob, $code).
   */
  public function handle($blob) {
    $this->log->debug("Processing request");
    list ($reqCxnId, $reqData) = Message::decodeCxn02Aes($this->cxnStore, $blob);
    $cxn = $this->cxnStore->getByCxnId($reqCxnId);
    $this->log->debug('Looked up cxn', array('cxn'=>$cxn));
    Cxn::validate($cxn);
    list ($entity, $action, $params) = $reqData;

    $this->log->debug('Decoded API', array('reqData'=>$reqData));
    $respData = call_user_func($this->router, $cxn, $entity, $action, $params);
    $this->log->debug('Formed response', array('respData'=>$respData));

    $tuple = array(
      array(), //headers
      Message::encodeCxn02Aes($reqCxnId, $cxn['secret'], $respData),
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
   * @param callable $router
   */
  public function setRouter($router) {
    $this->router = $router;
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

}
