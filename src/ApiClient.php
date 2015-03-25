<?php
namespace Civi\Cxn\Rpc;

use Civi\Cxn\Rpc\Message\StdMessage;
use Psr\Log\NullLogger;

class ApiClient {
  /**
   * @var array
   */
  protected $appMeta;

  /**
   * @var CxnStore\CxnStoreInterface
   */
  protected $cxnStore;

  /**
   * @var string
   */
  protected $cxnId;

  /**
   * @var Http\HttpInterface
   */
  protected $http;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $log;

  /**
   * @param array $appMeta
   * @param CxnStore\CxnStoreInterface $cxnStore
   */
  public function __construct($appMeta, $cxnStore, $cxnId) {
    $this->appMeta = $appMeta;
    $this->cxnStore = $cxnStore;
    $this->cxnId = $cxnId;
    $this->http = new Http\PhpHttp();
    $this->log = new NullLogger();
  }

  public function call($entity, $action, $params) {
    $this->log->debug("Send API call: {entity}.{action} over {cxnId}", array(
      'entity' => $entity,
      'action' => $action,
      'cxnId' => $this->cxnId,
    ));
    $cxn = $this->cxnStore->getByCxnId($this->cxnId);
    $req = new StdMessage($cxn['cxnId'], $cxn['secret'],
      array($entity, $action, $params));
    list($respHeaders, $respCiphertext, $respCode) = $this->http->send('POST', $cxn['siteUrl'], $req->encode(), array(
      'Content-type' => Constants::MIME_TYPE,
    ));
    $respMessage = Message\StdMessage::decode($this->cxnStore, $respCiphertext);
    if ($respMessage->getCxnId() != $cxn['cxnId']) {
      // Tsk, tsk, Mallory!
      throw new \RuntimeException('Received response from incorrect connection.');
    }
    return $respMessage->getData();
  }

  /**
   * @return Http\HttpInterface
   */
  public function getHttp() {
    return $this->http;
  }

  /**
   * @param Http\HttpInterface $http
   */
  public function setHttp($http) {
    $this->http = $http;
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
