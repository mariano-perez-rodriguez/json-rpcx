<?php

/**
 * Callback.php  Json RPC-X Callback handler
 *
 * Json RPC-X Callback handler.
 *
 */

namespace Json\RpcX\Handlers;

/**
 * Json RPC-X Callback handler
 *
 */
class Callback {

  /**
   * Callable to use
   *
   * @var callable
   */
  protected $callback = null;

  /**
   * Construct a handler from a callable
   *
   * @param callable $callback  Callable to use
   * @throws \Exception
   */
  public function __construct(callable $callback) {
    // NB: this contraption (ie. "call_user_func('is_callable', $args[0])" is
    //     used instead of "is_callable($args[0])" because by using it, we're
    //     leaving the class scope and can decide whether the callable is
    //     indeed callable from outside this class (and prevents malicious
    //     input like "self::privateMethod" from being accepted).
    if (!call_user_func('is_callable', $callback)) {
      throw new \Exception(__CLASS__ . ': given argument is not callable');
    }
    $this->callback = $callback;
  }

  /**
   * Execute the given action with the given parameters
   *
   * This method returns the results of applying the callback to the passed
   * argument.
   *
   * @param string $action  Action to take
   * @param mixed ...$params  Parameters for the action given
   * @return mixed
   * @throws \Exception
   */
  public function __invoke($action, ...$args) {
    switch (strtolower($action)) {
      case 'call':
        if (1 !== count($args)) {
          throw new \Exception(__CLASS__ . ": wrong number of arguments passed to 'call' action");
        }
        return call_user_func($this->callback, $args[0]);
      default:
        throw new \Exception(__CLASS__ . ": unknown action '{$action}'");
    }
  }

}
