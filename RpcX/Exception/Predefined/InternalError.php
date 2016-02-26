<?php

/**
 * InternalError.php  Internal error exception
 *
 * Exception class to model Json RPC-X internal error exception.
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
 * Exception class to model Json RPC-X internal error exception.
 *
 */
final class InternalError extends Predefined {

  /**
   * Json RPC-X error code to use
   *
   * @var int
   */
  const CODE = -32603;

  /**
   * Json RPC-X message to use
   *
   * @var string
   */
  const MESSAGE = 'Internal error';

}
