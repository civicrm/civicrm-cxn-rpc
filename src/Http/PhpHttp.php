<?php
namespace Civi\Cxn\Rpc\Http;

class PhpHttp implements HttpInterface {

  /**
   * @param string $verb
   * @param string $url
   * @param string $blob
   * @return array
   *   array($headers, $blob, $code)
   */
  public function send($verb, $url, $blob) {
    $opts = array(
      'http' => array(
        'method' => $verb,
        'content' => $blob,
      ),
    );
    $context = stream_context_create($opts);
    $blob = file_get_contents($url, FALSE, $context);
    $code = NULL;
    $headers = array();
    foreach ($http_response_header as $line) {
      if (preg_match('/^HTTP\/[0-9\.]+[^0-9]+([0-9]+)/', $line, $matches)) {
        $code = $matches[1];
      }
      elseif (preg_match(';^([a-zA-Z0-9\-]+):[ \t](.*);', $line, $matches)) {
        $headers[$matches[1]] = $matches[2];
      }
    }
    return array($headers, $blob, $code);
  }
}
