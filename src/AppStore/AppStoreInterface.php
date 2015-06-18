<?php
namespace Civi\Cxn\Rpc\AppStore;

interface AppStoreInterface {

  public function getAppIds();

  public function getAppMeta($appId);

  public function getPublicKey($appId);

  public function getPrivateKey($appId);

}
