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
    // FIXME: return exceptions and errors?

    $errors = array();
    set_error_handler(function ($errno, $errstr, $errfile, $errline, $errcontext) use (&$errors) {
      if (!(error_reporting() & $errno)) {
        return;
      }
      $errors[] = array($errno, $errstr, $errfile, $errline);
    });

    $e = NULL;
    try {
      list ($parsedIdentity, $payload) = $this->parseMessage($request);
      $response = call_user_func($callable, $parsedIdentity, $payload);
      $result = $this->createMessage($response, $parsedIdentity);
    }
    catch (\Exception $e2) {
      $e = $e2;
    }

    restore_error_handler();

    if ($e) {
      throw $e;
    }

    return $result;
  }

}
