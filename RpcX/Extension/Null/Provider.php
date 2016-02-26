<?php

/**
 * Provider.php  Json RPC-X NULL extension provider
 *
 * Class implementing a NULL extension provider.
 *
 */

namespace Json\RpcX\Extension\Null;

/**
 * Needed for:
 *  - Null
 *
 */
use \Json\RpcX\Extension\Null\Extension;

/**
 * Class implementing a NULL extension provider.
 *
 */
class Provider {

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
    // just keep NetBeans happy
    false && $value;

    switch (strtolower($command)) {
      case 'build':
        return new Extension();
      case 'config':
        switch (strtolower($name)) {
          default:
            throw new \Exception(__CLASS__ . ": unknown parameter '{$name}'");
        }
      case 'reset':
        return true;
      default:
        throw new \Exception(__CLASS__ . ": unknown command '{$command}'");
    }
  }

}
