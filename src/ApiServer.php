<?php
namespace Civi\Cxn\Rpc;

class ApiServer {

  /**
   * @var array
   */
  protected $appMeta;

  /**
   * @var CxnStoreInterface
   */
  protected $cxnStore;

  /**
   * @var callable
   */
  protected $router;

  /**
   * @param array $appMeta
   * @param CxnStoreInterface $cxnStore
   */
  public function __construct($appMeta, $cxnStore, $router = NULL) {
    $this->appMeta = $appMeta;
    $this->cxnStore = $cxnStore;
    $this->router = $router;
  }

  /**
   * @param $blob
   * @return array
   *   array($headers, $blob, $code)
   */
  public function handle($blob) {
    list ($reqCxnId, $reqData) = Message::decodeCxn02Aes($this->cxnStore, $blob);
    $cxn = $this->cxnStore->getByCxnId($reqCxnId);
    list ($entity, $action, $params) = $reqData;

    $respData = call_user_func($this->router, $cxn, $entity, $action, $params);

    $tuple = array(
      array(), //headers
      Message::encodeCxn02Aes($reqCxnId, $cxn['secret'], $respData),
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
   * @param callable $router
   */
  public function setRouter($router) {
    $this->router = $router;
  }

}
