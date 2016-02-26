<?php

/**
 * MethodNotFound.php  Method not found exception
 *
 * Exception class to model Json RPC-X method not found exception.
 *
 */

namespace Json\RpcX\Exception\Predefined;

/**
 * Needed for:
 *  - Predefined
 *
 */
use \Json\RpcX\Exception\Predefined;

/**
 * Exception class to model Json RPC-X method not found exception.
 *
 */
final class MethodNotFound extends Predefined {

  /**
   * Json RPC-X error code to use
   *
   * @var int
   */
  const CODE = -32601;

  /**
   * Json RPC-X message to use
   *
   * @var string
   */
  const MESSAGE = 'Method not found';

}
