<?php

/**
 * ExtensionTrait.php  Json RPC-X extension boilerplate
 *
 * Trait to implement the ExtensionInterface boilerplate.
 *
 */

namespace Json\RpcX;

/**
 * Needed for:
 *  - Exception::methodNotFound()
 *
 */
use \Json\RpcX\Exception;

/**
 * Trait to implement the ExtensionInterface boilerplate
 *
 * This trait implements every method in the ExtensionInterface interface
 * so that using classes may only implement the needed ones.
 *
 */
trait ExtensionTrait {

  /**
   * Return the extension's name extracting it as the last component of the fully qualified class name
   *
   * @return string
   */
  public function name() {
    $m = [];
    if (preg_match('~^.*\\\\(?P<name>[^\\\\]+)\\\\[^\\\\]*$~', __CLASS__, $m)) {
      return strtolower($m['name']);
    }
    throw new \Exception(__CLASS__ . ': could not determine extension name');
  }

  /**
   * Return the extension's metadata collected from the request/response cycle
   *
   * @return mixed
   */
  public function getMetadata() {
    return null;
  }

  /**
   * Process an 'rpc.x.<extension>.*' command
   *
   * @param string $method  Method to execute
   * @param array $params  Parameters given
   * @return mixed
   * @throws Exception
   */
  public function command($method, array $params = []) {
    // just keep NetBeans happy
    false && $method && $params;

    throw Exception::methodNotFound(['method' => "rpc.x.{$this->name()}.{$method}"]);
  }

  /**
   * Execute the postDecodeRequest hook by doing nothing
   *
   * This method is fed the decoded request object and it must return the
   * "new" request object to consider.
   *
   * Note that, after all postDecodeRequest hooks are executed, the resulting
   * request object must validate against the standard Json RPC format;
   * whith this in mind, this method should remove "consumed" fields from
   * the request.
   *
   * @param \stdClass $request  Request object to act upon
   * @return \stdClass
   */
  public function postDecodeRequest(\stdClass $request) {
    return $request;
  }

  /**
   * Execute the preEncodeRequest hook
   *
   * This method is fed the unencoded request object and it must return
   * the "new" request object to consider.
   *
   * Note that, after all the preEncodeRequest hooks are executed, the resulting
   * request object must validate against the server's expectations;
   * with this in mind, this method should add "additional" fields to the
   * request.
   *
   * @param \stdClass $request  Request object to act upon
   * @return \stdClass
   */
  public function preEncodeRequest(\stdClass $request) {
    return $request;
  }

  /**
   * Execute the postDecodeResponse hook
   *
   * This method is fed the decoded response object and it must return the
   * "new" response object to consider.
   *
   * Note that, after all postDecodeResponse hooks are executed, the resulting
   * response object must validate against the standard Json RPC format;
   * whith this in mind, this method should remove "consumed" fields from
   * the response.
   *
   * @param \stdClass $response  Response object to act upon
   * @return \stdClass
   */
  public function postDecodeResponse(\stdClass $response) {
    return $response;
  }

  /**
   * Execute the preEncodeResponse hook by doing nothing
   *
   * This method is fed the unencoded response object and it must return
   * the "new" response object to consider.
   *
   * Note that, after all the preEncodeResponse hooks are executed, the resulting
   * response object must validate against the client's expectations;
   * with this in mind, this method should add "additional" fields to the
   * response.
   *
   * @param \stdClass $response  Response object to act upon
   * @return \stdClass
   */
  public function preEncodeResponse(\stdClass $response) {
    return $response;
  }

  /**
   * Return the postDecodeRequest hook priority for this instance, fixed at 0
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  public function postDecodeRequestPriority() {
    return 0;
  }

  /**
   * Return the preEncodeRequest hook priority for this instance, fixed at 0
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  public function preEncodeRequestPriority() {
    return 0;
  }

  /**
   * Return the preEncodeResponse hook priority for this instance, fixed at 0
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  public function preEncodeResponsePriority() {
    return 0;
  }

  /**
   * Return the postDecodeResponse hook priority for this instance, fixed at 0
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  public function postDecodeResponsePriority() {
    return 0;
  }

}
