<?php
namespace Civi\Cxn\Rpc;

abstract class BaseServer extends Agent implements ServerInterface {

  /**
   * Parse a request and pass it to a function for execution.
   *
   * @param string $request
   *   Serialized request.
   * @param callable $callable
   *   Function(AgentIdentity $remoteIdentity, array $data).
   * @return string
   *   Serialized response.
   * @throws \Exception
   */
  public function handle($request, $callable) {
    // FIXME: format exceptions
    list ($parsedIdentity, $payload) = $this->parseMessage($request);
    $response = call_user_func($callable, $parsedIdentity, $payload);
    return $this->createMessage($response, $parsedIdentity);
  }

}
