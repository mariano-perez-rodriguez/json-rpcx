<?php

/**
 * Provider.php  Json RPC-X TIMING extension provider
 *
 * Class implementing a TIMING extension provider.
 *
 */

namespace Json\RpcX\Extension\Timing;

/**
 * Needed for:
 *  - Timing
 *
 */
use \Json\RpcX\Extension\Timing\Extension;

/**
 * Class implementing a TIMING extension provider.
 *
 */
class Provider {

  /**
   * Wait value to use for construction
   *
   * @var int|null
   */
  protected $wait = null;

  /**
   * Set the internal wait parameter
   *
   * @param int|null $wait  Value to set wait to
   * @return self
   * @throws \Exception
   */
  protected function setWait($wait = null) {
    if (null !== $wait && !is_integer($wait)) {
      throw new \Exception(__CLASS__ . ": 'wait' value must be null or integer");
    }
    $this->wait = $wait;

    return $this;
  }

  /**
   * Constructor merely initialces the wait value
   *
   * @param int|null $wait  Value to set wait to
   */
  public function __construct($wait = null) {
    $this->setWait($wait);
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
        return new Extension($this->wait);
      case 'config':
        switch (strtolower($name)) {
          case 'wait':
            $this->setWait($value);
            return true;
          default:
            throw new \Exception(__CLASS__ . ": unknown parameter '{$name}'");
        }
      case 'reset':
        $this->wait = null;
        return true;
      default:
        throw new \Exception(__CLASS__ . ": unknown command '{$command}'");
    }
  }

}
