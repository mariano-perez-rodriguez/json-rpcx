<?php

/**
 * ParseError.php  Parse error exception
 *
 * Exception class to model Json RPC-X parse error exception.
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
 * Exception class to model Json RPC-X parse error exception
 *
 */
final class ParseError extends Predefined {

  /**
   * Json RPC-X error code to use
   *
   * @var int
   */
  const CODE = -32700;

  /**
   * Json RPC-X message to use
   *
   * @var string
   */
  const MESSAGE = 'Parse error';

}
