<?php
namespace Civi\Cxn\Rpc;

class AppIdentity extends AgentIdentity {

  const X509_USAGE = 'id-kp-serverAuth';

  public static function create(CaIdentity $ca, $siteId, $callbackUrl) {
    return static::createHelper($ca, $siteId, $callbackUrl, array(AppIdentity::X509_USAGE));
  }

}
