<?php

/**
 * ApplicationError.php  Application error exception
 *
 * Exception class to model Json RPC-X application error exceptions.
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
 * Exception class to model Json RPC-X application error exceptions.
 *
 */
class ApplicationError extends Exception {

  /**
   * Constructor checks for code bounds and calls parent one
   *
   * @param int $code  Code to use
   * @param string $message  Message to use
   * @param mixed $data  Data to use
   * @throws \Exception
   */
  public function __construct($code, $message, $data = null) {
    if ($code <= -32000) {
      throw new \Exception(__CLASS__ . ": code '{$code}' is not greater than -32000");
    }
    parent::__construct($code, $message, $data);
  }

}
