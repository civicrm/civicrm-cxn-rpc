<?php
namespace Civi\Cxn\Rpc\CxnStore;

interface CxnStoreInterface {

  public function getAll();

  public function getByCxnId($cxnId);

  public function getByAppId($appId);

  public function add($cxn);

  public function remove($cxnId);

}