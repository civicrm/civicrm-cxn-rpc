<?php
namespace Civi\Cxn\Rpc;

/**
 * Class SiteClient
 *
 * The SiteClient formats outgoing requests on behalf of an end-user organization. For example,
 * the SiteClient for SaveTheWhales.org connects to the AppServer for AddressCleanup.com.
 *
 * @package Civi\Cxn\Rpc
 */
class SiteClient extends BaseClient {
  /**
   * @return string
   *   The extendendUsage attribute which should be present on my certificate.
   */
  protected function getMyExpectedCertUsage() {
    return SiteIdentity::X509_USAGE;
  }

  /**
   * @return string
   *   The extendendUsage attribute which should be present on my certificate.
   */
  protected function getExpectedRemoteUsage() {
    return AppIdentity::X509_USAGE;
  }

}
