<?php
namespace Civi\Cxn\Rpc;

abstract class Message {

  protected $code = 200;
  protected $headers = array();
  protected $data;

  public function __construct($data) {
    $this->data = $data;
  }

  /**
   * @return string
   *   Encoded message.
   */
  abstract public function encode();

  /**
   * @return int
   */
  public function getCode() {
    return $this->code;
  }

  /**
   * @param int $code
   * @return static
   */
  public function setCode($code) {
    $this->code = $code;
    return $this;
  }

  /**
   * @return mixed
   */
  public function getData() {
    return $this->data;
  }

  /**
   * @param mixed $data
   * @return static
   */
  public function setData($data) {
    $this->data = $data;
    return $this;
  }

  /**
   * @return array
   */
  public function getHeaders() {
    return $this->headers;
  }

  /**
   * @param array $headers
   * @return static
   */
  public function setHeaders($headers) {
    $this->headers = $headers;
    return $this;
  }

  /**
   * @return array
   *   array($headers, $blob, $code)
   */
  public function toHttp() {
    return array($this->headers, $this->encode(), $this->code);
  }

  public function send() {
    list ($headers, $blob, $code) = $this->toHttp();
    header("X-PHP-Response-Code: $code", TRUE, $code);
    foreach ($headers as $n => $v) {
      header("$n: $v");
    }
    echo $blob;
  }

}
