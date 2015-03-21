<?php
namespace Civi\Cxn\Rpc;

interface ClientInterface {

  /**
   * @param string $entity
   *   Entity name.
   * @param string $action
   *   Action name.
   * @param array $params
   *   Array-tree parameters.
   * @return array
   *   Parsed response data.
   */
  public function sendRequest($entity, $action, $params);

}
