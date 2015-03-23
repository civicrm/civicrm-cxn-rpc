<?php
namespace Civi\Cxn\Rpc;

interface HttpInterface {

  /**
   * @param string $verb
   * @param string $url
   * @param string $blob
   * @return array
   *   array($headers, $blob, $code)
   */
  public function send($verb, $url, $blob);
}
