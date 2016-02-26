<?php

/**
 * InvalidParams.php  Invalid params exception
 *
 * Exception class to model Json RPC-X invalid params exception.
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
 * Exception class to model Json RPC-X invalid params exception.
 *
 */
final class InvalidParams extends Predefined {

  /**
   * Json RPC-X error code to use
   *
   * @var int
   */
  const CODE = -32602;

  /**
   * Json RPC-X message to use
   *
   * @var string
   */
  const MESSAGE = 'Invalid params';

}
