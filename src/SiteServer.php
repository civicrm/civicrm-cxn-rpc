<?php
namespace Civi\Cxn\Rpc;

/**
 * Class SiteServer
 *
 * The SiteServer parses incoming requests on behalf of an end-user organization. For example,
 * the AppClient for AddressCleanup.com connects to the SiteServer for SaveTheWhales.org.
 *
 * @package Civi\Cxn\Rpc
 */
class SiteServer extends BaseServer {

  /**
   * @return string
   *   The extendendUsage attribute which should be present on my certificate.
   */
  protected function getMyExpectedCertUsage() {
    return 'id-kp-clientAuth';
  }

  /**
   * @return string
   *   The extendendUsage attribute which should be present on my certificate.
   */
  protected function getExpectedRemoteUsage() {
    return 'id-kp-serverAuth';
  }

}
