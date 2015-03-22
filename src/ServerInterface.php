<?php
namespace Civi\Cxn\Rpc;

/**
 * Interface ServerInterface
 * @package Civi\Cxn\Rpc
 *
 * @code
 * $server = new ConcreteServer(...);
 * $server->handle($_POST['encryptedRequest'], function($identity, $data){
 *   return "Thank you, " . $identity->getAgentId() . " I believe that " . $data['entity'] ." is a good entity.";
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
   *   Function(AgentIdentity $remoteIdentity, array $data).
   * @return string
   *   Serialized, encrypted response.
   */
  public function handle($request, $callable);

}
