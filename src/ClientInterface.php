<?php
namespace Civi\Cxn\Rpc;

interface ClientInterface {

  /**
   * @param array $data
   *   Array-tree.
   * @return array
   *   Parsed response data.
   */
  public function sendRequest($data);

}
