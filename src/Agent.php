<?php
namespace Civi\Cxn\Rpc;

class Agent {
  /**
   * @var string
   */
  protected $caCert;

  /**
   * @var CxnStore\CxnStoreInterface
   */
  protected $cxnStore;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $log;

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
