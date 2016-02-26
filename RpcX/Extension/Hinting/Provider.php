<?php

/**
 * Provider.php  Json RPC-X HINTING extension provider
 *
 * Class implementing a HINTING extension provider.
 *
 */

namespace Json\RpcX\Extension\Hinting;

/**
 * Needed for:
 *  - Hinting
 *
 */
use \Json\RpcX\Extension\Hinting\Extension;

/**
 * Class implementing a HINTING extension provider.
 *
 */
class Provider {

  /**
   * Jsonclass to use for construction
   *
   * @var string
   */
  protected $jsonclass = '__jsonclass__';

  /**
   * MaxDepth to use for construction
   *
   * @var int|null
   */
  protected $maxDepth = 1024;

  /**
   * Set the internal jsonClass parameter
   *
   * @param string $jsonClass  Value to set jsonClass to
   * @return self
   * @throws \Exception
   */
  protected function setJsonClass($jsonClass = '__jsonclass__') {
    if (!is_string($jsonClass)) {
      throw new \Exception(__CLASS__ . ": 'jsonClass' value must be string");
    }
    $this->jsonclass = $jsonClass;

    return $this;
  }

  /**
   * Set the internal maxDepth parameter
   *
   * @param int $maxDepth  Value to set maxDepth to
   * @return self
   * @throws \Exception
   */
  protected function setMaxDepth($maxDepth = 1024) {
    if (!is_integer($maxDepth)) {
      throw new \Exception(__CLASS__ . ": 'maxDepth' value must be integer");
    }
    $this->maxDepth = $maxDepth;

    return $this;
  }

  /**
   * Constructor merely initialices the jsonClass, and maxDepth values
   *
   * @param string $jsonClass  Value to set jsonClass to
   * @param int $maxDepth  Value to set maxDepth to
   */
  public function __construct($jsonClass = '__jsonclass__', $maxDepth = 1024) {
    $this->setJsonClass($jsonClass);
    $this->setMaxDepth($maxDepth);
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
        return new Extension($this->jsonclass, $this->maxDepth);
      case 'config':
        switch (strtolower($name)) {
          case 'jsonclass':
            $this->setJsonClass($value);
            return true;
          case 'maxdepth':
            $this->setMaxDepth($value);
            return true;
          default:
            throw new \Exception(__CLASS__ . ": unknown parameter '{$name}'");
        }
      case 'reset':
        $this->jsonclass = '__jsonclass__';
        $this->maxDepth  = 1024;
        return true;
      default:
        throw new \Exception(__CLASS__ . ": unknown command '{$command}'");
    }
  }

}
