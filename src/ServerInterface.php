<?php
namespace Civi\Cxn\Rpc;

/**
 * Interface ServerInterface
 * @package Civi\Cxn\Rpc
 *
 * @code
 * $server = new ConcreteServer(...);
 * $server->handle($_POST['encryptedRequest'], function($identity, $entity, $action, $params){
 *   return "Thank you, " . $identity->getAgentId() . " I believe that $entity is a good entity.";
 * });
 * @endcode
 */
interface ServerInterface {

  /**
   * Parse a request and pass it to a function for execution.
   *
   * @param string $request
   *   Serialized, encrypted request.
   * @param callable $callable
   *   Function(AgentIdentity $remoteIdentity, string $entity, string $action, array $params).
   * @return string
   *   Serialized, encrypted response.
   */
  public function handle($request, $callable);

}
