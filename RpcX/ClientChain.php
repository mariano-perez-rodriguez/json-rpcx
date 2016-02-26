<?php

/**
 * ClientChain.php  Json RPC-X Client chain parser class
 *
 * Json RPC-X Client chain parser.
 *
 */

namespace Json\RpcX;

/**
 * Needed for:
 *  - Client
 *
 */
use \Json\RpcX\Client;

/**
 * Json RPC-X Client chain parser
 *
 */
class ClientChain extends Client {

  /**
   * Reading method name
   *
   * @var int
   */
  const NAME = 0;

  /**
   * Reading parameters
   *
   * @var int
   */
  const PARAMS = 1;

  /**
   * Reading call modifiers requiring an argument instance
   *
   * @var int
   */
  const CALL_ARG = 2;

  /**
   * Reading call modifiers
   *
   * @var int
   */
  const CALL = 3;

  /**
   * Current state
   *
   * @var int
   */
  protected $state = self::NAME;

  /**
   * Current method name
   *
   * @var string
   */
  protected $method = '';

  /**
   * Current parameter specifications
   *
   * @var array
   */
  protected $params = [];

  /**
   * Current notification status
   *
   * @var boolean
   */
  protected $notify = false;

  /**
   * Current id, or false if automatically generated
   *
   * @var int|float|string|false
   */
  protected $id = false;

  /**
   * Current processing callback
   *
   * @var callable|null
   */
  protected $callback = null;

  /**
   * Underlying Client to act upon
   *
   * @var Client
   */
  protected $client;

  /**
   * Builder initializes all fields selectively
   *
   * This function will construct a copy of the current object, overriding the
   * fields listed in the associative array given with their values there, or
   * using the defaults, if the array contains null values.
   *
   * If null is given, all values are returned as their default.
   *
   * @param array $opts  Overriding fields array
   * @return self
   */
  protected function build(array $opts = []) {
    $opts += [
        'state'    => $this->state,
        'method'   => $this->method,
        'params'   => $this->params,
        'notify'   => $this->notify,
        'id'       => $this->id,
        'callback' => $this->callback,
    ];
    return new self($this->client, $opts['state'], $opts['method'], $opts['params'], $opts['notify'], $opts['id'], $opts['callback']);
  }

  /**
   * Constructor merely sets the paramenters up
   *
   * @param Client $client  Underlying Client object to use
   * @param int $state  State to set the parser in
   * @param string $method  Method being parsed so far
   * @param array $params  Parameters being parsed so far
   * @param boolea $notify  Notification status so far
   * @param null|int|float|string|false $id  Id value to use (false to automatically generate one) so far
   * @param callable $callback  Callback to apply to the current request, so far
   */
  public function __construct(Client $client, $state = self::NAME, $method = '', array $params = [], $notify = false, $id = false, callable $callback = null) {
    parent::__construct(null);
    $this->client   = $client;
    $this->state    = $state;
    $this->method   = $method;
    $this->params   = $params;
    $this->notify   = $notify;
    $this->id       = $id;
    $this->callback = $callback;
  }

  //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  /**
   * Parse a namespace, call verb, or modifier
   *
   * @param string $name  Namespace, call verb or modifier
   * @return self
   * @throws \Exception
   */
  public function __get($name) {
    switch ($this->state) {
      case self::NAME:
        return $this->build(['method' => "{$this->method}.{$name}"]);
      case self::PARAMS:
        switch (strtolower($name)) {
          case 'with':
            return $this->build(['state' => self::CALL_ARG]);
          case 'exec':
            return $this->enqueue()->exec();
          case 'enqueue':
            return $this->enqueue();
          default:
            throw new \Exception(__CLASS__ . ": unknown call verb or modifier '{$name}'");
        }
      case self::CALL_ARG:
        switch (strtolower($name)) {
          case 'notification':
            return $this->build(['state' => self::CALL, 'notify' => true]);
          case 'confirmation':
            return $this->build(['state' => self::CALL, 'notify' => false]);
          default:
            throw new \Exception(__CLASS__ . ": unknown call verb or modifier argument '{$name}'");
        }
      case self::CALL:
        switch (strtolower($name)) {
          case 'with':
            return $this->build();
          case 'exec':
            return $this->enqueue()->exec();
          case 'enqueue':
            return $this->enqueue();
          default:
            throw new \Exception(__CLASS__ . ": unknown call verb or modifier '{$name}'");
        }
      default:
        throw new \Exception(__CLASS__ . ': unknown state');
    }
  }

  /**
   * Parse a namespace, call verb, or modifier
   *
   * @param string $name  Namespace, call verb or modifier
   * @param array $args  Verb or modifier's argument(s)
   * @return self
   * @throws \Exception
   */
  public function __call($name, array $args) {
    switch ($this->state) {
      case self::NAME:
        return $this->build(['state' => self::PARAMS, 'method' => trim("{$this->method}.{$name}", '.'), 'params' => $args]);
      case self::PARAMS:
        if (1 !== count($args)) {
          throw new \Exception(__CLASS__ . ": multiple arguments for parameter '{$name}'");
        }
        return $this->build(['state' => self::PARAMS, 'params' => [$name => $args[0]] + $this->params]);
      case self::CALL_ARG:
        switch (strtolower($name)) {
          case 'notify':
            if (count($args) < 1) {
              throw new \Exception(__CLASS__ . ": too few parameters for 'notify' modifier");
            } else if (1 < count($args)) {
              throw new \Exception(__CLASS__ . ": too many parameters for 'notify' modifier");
            }
            return $this->build(['state' => self::CALL, 'notify' => boolval($args[0])]);
          case 'confirm':
            if (count($args) < 1) {
              throw new \Exception(__CLASS__ . ": too few parameters for 'notify' modifier");
            } else if (1 < count($args)) {
              throw new \Exception(__CLASS__ . ": too many parameters for 'notify' modifier");
            }
            return $this->build(['state' => self::CALL, 'notify' => !boolval($args[0])]);
          case 'id':
            if (count($args) < 1) {
              throw new \Exception(__CLASS__ . ": too few parameters for 'id' modifier");
            } else if (1 < count($args)) {
              throw new \Exception(__CLASS__ . ": too many parameters for 'id' modifier");
            } else if (null !== $args[0] && false !== $args[0] && !is_int($args[0]) && !is_float($args[0]) && !is_string($args[0])) {
              throw new \Exception(__CLASS__ . ": the 'id' modifier expects its argument to be null, integer, float, string, or false");
            }
            return $this->build(['state' => self::CALL, 'id' => $args[0]]);
          case 'callback':
            if (count($args) < 1) {
              throw new \Exception(__CLASS__ . ": too few parameters for 'callback' modifier");
            } else if (1 < count($args)) {
              throw new \Exception(__CLASS__ . ": too many parameters for 'callback' modifier");
              // NB: this contraption (ie. "call_user_func('is_callable', $args[0])" is
              //     used instead of "is_callable($args[0])" because by using it, we're
              //     leaving the class scope and can decide whether the callable is
              //     indeed callable from outside this class (and prevents malicious
              //     input like "self::privateMethod" from being accepted).
            } else if (!call_user_func('is_callable', $args[0])) {
              throw new \Exception(__CLASS__ . ": the 'callback' modifier expects it parameter to be callable");
            }
            return $this->build(['state' => self::CALL, 'callback' => $args[0]]);
          default:
            throw new \Exception(__CLASS__ . ": unknown call verb or modifier '{$name}'");
        }
      case self::CALL:
        throw new \Exception(__CLASS__ . ': unsupported call verb or modifier for current state');
      default:
        throw new \Exception(__CLASS__ . ': unknown state');
    }
  }

  /**
   * Act as a proxy for static functions
   *
   * This override is in place in order to avoid calling the Client's one.
   *
   * @param string $name  Static method being called
   * @param array $args  Arguments passed
   * @return mixed
   */
  public static function __callStatic($name, array $args) {
    // just keep NetBeans happy
    false && $args;

    throw new \Exception(__CLASS__ . ": unknown manipulator '{$name}'");
  }

  /**
   * Invoke the current selected method
   *
   * @param array $args  Arguments to apply
   * @throws \Exception
   */
  public function __invoke(...$args) {
    switch ($this->state) {
      case self::NAME:
        return $this->build(['state' => self::PARAMS, 'params' => $args]);
      case self::PARAMS:
        return $this->build(['params' => $args + $this->params]);
      case self::CALL_ARG:
        throw new \Exception(__CLASS__ . ": non-callable state");
      case self::CALL:
        throw new \Exception(__CLASS__ . ": non-callable state");
      default:
        throw new \Exception(__CLASS__ . ': unknown state');
    }
  }

  /**
   * Enqueue the current call specification for execution and return the underlying Client
   *
   * @return Client
   */
  protected function enqueue() {
    if (!$this->method) {
      throw new \Exception(__CLASS__ . ': blank method name');
    }

    $this->client->queue[] = [
        'method'   => $this->method,
        'params'   => $this->params ? : false,
        'notify'   => $this->notify,
        'id'       => $this->id,
        'callback' => $this->callback ? : false,
    ];

    return $this->client;
  }

}
