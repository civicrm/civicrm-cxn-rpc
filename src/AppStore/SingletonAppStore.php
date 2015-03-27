<?php
namespace Civi\Cxn\Rpc\AppStore;

use Civi\Cxn\Rpc\AppMeta;

class SingletonAppStore implements AppStoreInterface {

  private $appId;

  private $appMeta;

  private $publicKey;

  private $privateKey;

  public function __construct($appId, $appMeta, $privateKey, $publicKey) {
    AppMeta::validate($appMeta);
    $this->appId = $appId;
    $this->appMeta = $appMeta;
    $this->privateKey = $privateKey;
    $this->publicKey = $publicKey;
  }

  public function getAppMeta($appId) {
    if ($appId == $this->appId) {
      return $this->appMeta;
    }
    else {
      return NULL;
    }
  }

  public function getPublicKey($appId) {
    if ($appId == $this->appId) {
      return $this->publicKey;
    }
    else {
      return NULL;
    }
  }

  public function getPrivateKey($appId) {
    if ($appId == $this->appId) {
      return $this->privateKey;
    }
    else {
      return NULL;
    }
  }

}
