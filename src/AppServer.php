<?php
namespace Civi\Cxn\Rpc;

/**
 * Class AppServer
 *
 * The AppServer parses incoming requests on behalf of an application. For example,
 * the AppClient for SaveTheWhales.org connects to the AppServer to AddressCleanup.com
 *
 * @package Civi\Cxn\Rpc
 */
class AppServer extends BaseServer {

  /**
   * @return string
   *   The extendendUsage attribute which should be present on my certificate.
   */
  protected function getMyExpectedCertUsage() {
    return AppIdentity::X509_USAGE;
  }

  /**
   * @return string
   *   The extendendUsage attribute which should be present on my certificate.
   */
  protected function getExpectedRemoteUsage() {
    return SiteIdentity::X509_USAGE;
  }

}
