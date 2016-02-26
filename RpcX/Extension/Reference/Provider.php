<?php

/**
 * Provider.php  Json RPC-X REFERENCE extension provider
 *
 * Class implementing a REFERENCE extension provider.
 *
 */

namespace Json\RpcX\Extension\Reference;

/**
 * Needed for:
 *  - Reference
 *
 */
use \Json\RpcX\Extension\Reference\Extension;

/**
 * Class implementing a REFERENCE extension provider.
 *
 */
class Provider {

  /**
   * Reference identifier to use for construction
   *
   * @var string
   */
  protected $reference = '__ref__';

  /**
   * Id identifier to use for construction
   *
   * @var string
   */
  protected $id = '__id__';

  /**
   * MaxDepth to use for construction
   *
   * @var int
   */
  protected $maxDepth = 1024;

  /**
   * Set the internal reference parameter
   *
   * @param string $reference  Value to set reference to
   * @return self
   * @throws \Exception
   */
  protected function setReference($reference = '__ref__') {
    if (!is_string($reference)) {
      throw new \Exception(__CLASS__ . ": 'reference' value must be string");
    }
    $this->reference = $reference;

    return $this;
  }

  /**
   * Set the internal id parameter
   *
   * @param string $id  Value to set id to
   * @return self
   * @throws \Exception
   */
  protected function setId($id = '__id__') {
    if (!is_string($id)) {
      throw new \Exception(__CLASS__ . ": 'id' value must be string");
    }
    $this->id = $id;

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
   * Constructor merely initialices the reference, id, and maxDepth values
   *
   * @param string $reference  Value to set reference to
   * @param string $id  Value to set id to
   * @param int $maxDepth  Value to set maxDepth to
   */
  public function __construct($reference = '__ref__', $id = '__id__', $maxDepth = 1024) {
    $this->setReference($reference);
    $this->setId($id);
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
        return new Extension($this->reference, $this->id, $this->maxDepth);
      case 'config':
        switch (strtolower($name)) {
          case 'reference':
            $this->setRreference($value);
            return true;
          case 'id':
            $this->setId($value);
            return true;
          case 'maxdepth':
            $this->setMaxDepth($value);
            return true;
          default:
            throw new \Exception(__CLASS__ . ": unknown parameter '{$name}'");
        }
      case 'reset':
        $this->reference = '__ref__';
        $this->id        = '__id__';
        $this->maxDepth  = 1024;
        return true;
      default:
        throw new \Exception(__CLASS__ . ": unknown command '{$command}'");
    }
  }

}
