<?php

/**
 * Extension.php  Json RPC-X SERVICE extension
 *
 * Class to implement the SERVICE extension.
 *
 */

namespace Json\RpcX\Extension\Service;

/**
 * Needed for:
 *  - ExtensionInterface
 *
 */
use \Json\RpcX\ExtensionInterface;
/**
 * Needed for:
 *  - ExtensionTrait
 *
 */
use \Json\RpcX\ExtensionTrait;

/**
 * Class to implement the SERVICE extension
 *
 * This class implements the SERVICE extension through a ServiceBackend
 *
 */
class Extension implements ExtensionInterface {

  /**
   * Deal with boilerplate
   *
   */
  use ExtensionTrait;

  /**
   * Underlying Service backend
   *
   * This callable must accept the following parameters:
   *   - string $action: the action being attempted, this value further
   *         determines the following parameters; possible values are:
   *     - 'start': try to generate a valid service for the current
   *           request, paramters are:
   *       - string $method: method being called,
   *       - array $params: parameters being passed,
   *         this call should generate a service member (of whatever type),
   *         or null if no service object is to be included in the request.
   *     - 'init': try to initialize the service with the given service
   *           member, parameters are:
   *       - mixed $service: service member being offered,
   *     - 'deinit': finalize the service in case of success, parameters are:
   *       - mixed $result: result value to be returned,
   *     - 'command': try to execute the given rpc extension command,
   *           parameters are:
   *       - string $method: method name to execute (always lowercased and
   *             of the form 'rpc.x.service.<methodName>'),
   *       - array $params: parameters passed to the method.
   *         this call should return the command's result, or throw an
   *         exception on error.
   *
   * NB: in case of errors, no method will be called.
   *
   * @var callable
   */
  protected $backend = null;

  /**
   * Constructor merely sets the underlying Service backend
   *
   * @param callable $backend  Underlying Service backend to use
   */
  public function __construct(callable $backend) {
    // NB: this contraption (ie. "call_user_func('is_callable', $callable)" is
    //     used instead of "is_callable($callable)" because by using it, we're
    //     leaving the class scope and can decide whether the callable is
    //     indeed callable from outside this class (and prevents malicious
    //     input like "self::privateMethod" from being accepted).
    if (!call_user_func('is_callable', $backend)) {
      throw new \Exception(__CLASS__ . ': invalid backend');
    }
    $this->backend = $backend;
  }

  /**
   * Process an 'rpc.service.*' command
   *
   * @param string $method  Method to execute
   * @param array $params  Parameters given
   * @return mixed
   * @throws Exception
   */
  public function command($method, array $params = []) {
    return call_user_func($this->backend, 'command', $method, $params);
  }

  /**
   * Execute the preEncodeRequest hook by calling the underlying Service backend
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
    $method  = property_exists($request, 'method') ? $request->method : null;
    $params  = property_exists($request, 'params') ? $request->params : null;
    if (null !== ($service = call_user_func($this->backend, 'start', $method, $params))) {
      $request->service = $service;
    }
    return $request;
  }

  /**
   * Execute the postDecodeRequest hook by calling the underlying Service backend
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
    if (property_exists($request, 'service')) {
      call_user_func($this->backend, 'init', $request->service);
      unset($request->service);
    }
    return $request;
  }

  /**
   * Execute the preEncodeResponse hook
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
    if (property_exists($response, 'result')) {
      call_user_func($this->backend, 'deinit', $response->result);
    }
    return $response;
  }

  /**
   * Return the preEncodeRequest hook priority for this instance, fixed at 200
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  public function preEncodeRequestPriority() {
    return 200;
  }

  /**
   * Return the postDecodeRequest hook priority for this instance, fixed at -200
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  public function postDecodeRequestPriority() {
    return -200;
  }

  /**
   * Return the preEncodeResponse hook priority for this instance, fixed at 200
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  public function preEncodeResponsePriority() {
    return 200;
  }

}
