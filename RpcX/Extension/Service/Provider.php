<?php

/**
 * Provider.php  Json RPC-X SERVICE extension provider
 *
 * Class implementing a SERVICE extension provider.
 *
 */

namespace Json\RpcX\Extension\Service;

/**
 * Needed for:
 *  - Service
 *
 */
use \Json\RpcX\Extension\Service\Extension;

/**
 * Class implementing a SERVICE extension provider.
 *
 */
class Provider {

  /**
   * Backend to use for construction
   *
   * @var callable
   */
  protected $backend = null;

  /**
   * Set the internal backend parameter
   *
   * @param callable $backend  Value to set backend to
   * @return self
   * @throws \Exception
   */
  protected function setBackend(callable $backend) {
    // NB: this contraption (ie. "call_user_func('is_callable', $nackend)" is
    //     used instead of "is_callable($backend)" because by using it, we're
    //     leaving the class scope and can decide whether the callable is
    //     indeed callable from outside this class (and prevents malicious
    //     input like "self::privateMethod" from being accepted).
    if (!call_user_func('is_callable', $backend)) {
      throw new \Exception(__CLASS__ . ": 'backend' value must be callable");
    }
    $this->backend = $backend;

    return $this;
  }

  /**
   * Constructor merely initialces the backend value
   *
   * @param callable $backend  Value to set backend to
   */
  public function __construct($backend) {
    $this->setBackend($backend);
  }

  /**
   * Handle provider commands
   *
   * @param string $command  Command to handle
   * @param string  $name  Configuration name to set (only on "config" command)
   * @param mixed $value  Configuration value to use (only on "config" command)
   * @return Extension|boolean
   * @throws \Exception
   */
  public function __invoke($command, $name = null, $value = null) {
    switch (strtolower($command)) {
      case 'build':
        return new Extension($this->backend);
      case 'config':
        switch (strtolower($name)) {
          case 'backend':
            $this->setBackend($value);
            return true;
          default:
            throw new \Exception(__CLASS__ . ": unknown parameter '{$name}'");
        }
      case 'reset':
        $this->backend = null;
        return true;
      default:
        throw new \Exception(__CLASS__ . ": unknown command '{$command}'");
    }
  }

}
