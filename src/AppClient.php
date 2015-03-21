<?php
namespace Civi\Cxn\Rpc;

/**
 * Class AppClient
 *
 * The AppClient formats outgoing requests on behalf of an application. For example,
 * the AppClient for AddressCleanup.com connects to the SiteServer for SaveTheWhales.org.
 *
 * @package Civi\Cxn\Rpc
 */
class AppClient extends BaseClient {
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
