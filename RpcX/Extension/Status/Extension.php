<?php

/**
 * Extension.php  Json RPC-X STATUS extension
 *
 * Class to implement the STATUS extension.
 *
 */

namespace Json\RpcX\Extension\Status;

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
 * Class to implement the STATUS extension
 *
 * This class implements the STATUS extension through a StatusBackend
 *
 */
class Extension implements ExtensionInterface {

  /**
   * Deal with boilerplate
   *
   */
  use ExtensionTrait;

  /**
   * Underlying Status backend
   *
   * This callable must accept the following parameters:
   *   - string $action: the action being attempted, this value further
   *         determines the following parameters; possible values are:
   *     - 'status': return the status code for this (successful) request,
   *         paramters are:
   *       - string $method: method being called,
   *       - array $params: parameters being passed,
   *       - mixed $result: result obtained by calling said method with said
   *           parameters,
   *         this call should generate an integer status to be returned, or
   *         false if no status is to be included in the response.
   *     - 'command': try to execute the given rpc extension command,
   *           parameters are:
   *       - string $method: method name to execute (always lowercased and
   *             of the form 'rpc.x.status.<methodName>'),
   *       - array $params: parameters passed to the method.
   *         this call should return the command's result, or throw an
   *         exception on error.
   *
   * @var callable
   */
  protected $backend = null;

  /**
   * Method executed
   *
   * @var string
   */
  protected $method = null;

  /**
   * Parameters passed
   *
   * @var mixed
   */
  protected $params = null;

  /**
   * Status returned
   *
   * @var mixed
   */
  protected $status = null;

  /**
   * Constructor merely sets the underlying Status backend
   *
   * @param callable $backend  Underlying Status backend to use
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
   * Return the extension's metadata collected from the request/response cycle
   *
   * @return mixed
   */
  public function getMetadata() {
    return $this->status;
  }

  /**
   * Process an 'rpc.status.*' command
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
    $this->method = property_exists($request, 'method') ? $request->method : null;
    $this->params = property_exists($request, 'params') ? $request->params : null;
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
    if (property_exists($response, 'error')) {
      $response->status = $response->error->code;
    } else if (false !== ($status = call_user_func($this->backend, 'status', $this->method, $this->params, $response->result))) {
      $response->status = $status;
    }
    return $response;
  }

  /**
   * Execute the postDecodeResponse hook by estracting the status field
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
    if (property_exists($response, 'status')) {
      $this->status = $response->status;
      unset($response->status);
    }
    return $response;
  }

  /**
   * Return the postDecodeRequest hook priority for this instance, fixed at -300
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  public function postDecodeRequestPriority() {
    return -300;
  }

  /**
   * Return the preEncodeResponse hook priority for this instance, fixed at 300
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  public function preEncodeResponsePriority() {
    return 300;
  }

  /**
   * Return the postDecodeResponse hook priority for this instance, fixed at -300
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  public function postDecodeResponsePriority() {
    return -300;
  }

}
