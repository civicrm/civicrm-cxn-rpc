<?php
namespace Civi\Cxn\Rpc;

use Civi\Cxn\Rpc\Message\StdMessage;
use Psr\Log\NullLogger;

class ApiServer extends Agent {

  /**
   * @var callable
   */
  protected $router;

  /**
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
   * @return Message
   */
  public function handle($blob) {
    $this->log->debug("Processing request");
    $reqMessage = Message\StdMessage::decode($this->cxnStore, $blob);
    $cxn = $this->cxnStore->getByCxnId($reqMessage->getCxnId());
    $this->log->debug('Looked up cxn', array('cxn' => $cxn));
    Cxn::validate($cxn);
    list ($entity, $action, $params) = $reqMessage->getData();

    $this->log->debug('Decoded API', array('reqData' => $reqMessage->getData()));
    $respData = call_user_func($this->router, $cxn, $entity, $action, $params);
    $this->log->debug('Formed response', array('respData' => $respData));

    return new StdMessage($reqMessage->getCxnId(), $cxn['secret'], $respData);
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
   * @param callable $router
   */
  public function setRouter($router) {
    $this->router = $router;
  }

}
