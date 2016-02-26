<?php

/**
 * Extension.php  Json RPC-X AUTH extension
 *
 * Class to implement the AUTH extension.
 *
 */

namespace Json\RpcX\Extension\Auth;

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
 * Needed for:
 *  - MissingAuth
 *
 */
use \Json\RpcX\Extension\Auth\MissingAuth;
/**
 * Needed for:
 *  - InvalidAuth
 *
 */
use \Json\RpcX\Extension\Auth\InvalidAuth;

/**
 * Class to implement the AUTH extension
 *
 * This class implements the AUTH extension through an AUTH Backend
 *
 */
class Extension implements ExtensionInterface {

  /**
   * Deal with boilerplate
   *
   */
  use ExtensionTrait;

  /**
   * Underlying Auth backend
   *
   * This callable must accept the following parameters:
   *   - string $action: the action being attempted, this value further
   *         determines the following parameters; possible values are:
   *     - 'generate': try to generate a valid auth token for the current
   *           request, paramters are:
   *       - string $method: method being called,
   *       - array $params: parameters being passed,
   *         this call should generate an auth token (of whatever type), or
   *         false, if no auth information is to be included in the request,
   *     - 'verify': try to verify the given auth token against the intended
   *         method and parameters, parameters are:
   *       - mixed $auth: auth token being offered as authentication,
   *       - string $method: method being called,
   *       - array $params: parameters being passed,
   *         this call should return true when the authentication attempt
   *         succeeds, any other value other than false will be passed as
   *         "data" to the InvalidAuth exception,
   *     - 'command': try to execute the given rpc extension command,
   *           parameters are:
   *       - string $method: method name to execute (always lowercased and
   *             of the form 'rpc.x.auth.<methodName>'),
   *       - array $params: parameters passed to the method,
   *         this call should return the command's result, or throw an
   *         exception on error.
   *
   * @var callable
   */
  protected $backend = null;

  /**
   * Constructor merely sets the underlying Auth backend
   *
   * @param callable $backend  Underlying Auth backend to use
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
   * Process an 'rpc.auth.*' command
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
   * Execute the postDecodeRequest hook by calling the underlying Auth backend
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
    $auth         = property_exists($request, 'auth') ? $request->auth : null;
    $method       = property_exists($request, 'method') ? $request->method : null;
    $params       = property_exists($request, 'params') ? $request->params : null;
    if (true === ($verification = call_user_func($this->backend, 'verify', $auth, $method, $params))) {
      unset($request->auth);
      return $request;
    }

    $message = false === $verification ? null : $verification;
    throw (null === $auth) ? new MissingAuth($message) : new InvalidAuth($message);
  }

  /**
   * Execute the preEncodeRequest hook by calling the underlying Auth backend
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
    $method = property_exists($request, 'method') ? $request->method : null;
    $params = property_exists($request, 'params') ? $request->params : null;
    if (false !== ($token  = call_user_func($this->backend, 'generate', $method, $params))) {
      $request->auth = $token;
    }
    return $request;
  }

  /**
   * Return the postDecodeRequest hook priority for this instance, fixed at -100
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  public function postDecodeRequestPriority() {
    return -100;
  }

  /**
   * Return the preEncodeRequest hook priority for this instance, fixed at -100
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  public function preEncodeRequestPriority() {
    return -100;
  }

}
