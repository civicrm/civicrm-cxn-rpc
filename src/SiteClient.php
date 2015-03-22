<?php
namespace Civi\Cxn\Rpc;

/**
 * Class SiteClient
 *
 * The SiteClient formats outgoing requests on behalf of an application. For example,
 * the SiteClient for AddressCleanup.com connects to the SiteServer for SaveTheWhales.org.
 *
 * @package Civi\Cxn\Rpc
 */
class SiteClient extends BaseClient {
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
