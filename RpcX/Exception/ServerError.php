<?php

/**
 * ServerError.php  Server error exception
 *
 * Exception class to model Json RPC-X server error exceptions.
 *
 */

namespace Json\RpcX\Exception;

/**
 * Needed for:
 *  - Exception
 *
 */
use \Json\RpcX\Exception;

/**
 * Exception class to model Json RPC-X server error exceptions.
 *
 */
class ServerError extends Exception {

  /**
   * Constructor checks for code bounds and calls parent one
   *
   * @param int $code  Code to use
   * @param string $message  Message to use
   * @param mixed $data  Data to use
   * @throws \Exception
   */
  public function __construct($code, $message, $data = null) {
    if ($code < -32099 || -32000 < $code) {
      throw new \Exception(__CLASS__ . ": code '{$code}' does not lie between -32099 and -32000");
    }
    parent::__construct($code, $message, $data);
  }

}
