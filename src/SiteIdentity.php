<?php
namespace Civi\Cxn\Rpc;

class SiteIdentity extends AgentIdentity {

  const X509_USAGE = 'id-kp-clientAuth';

  public static function create(CaIdentity $ca, $siteId, $callbackUrl) {
    return static::createHelper($ca, $siteId, $callbackUrl, array(SiteIdentity::X509_USAGE));
  }

}
