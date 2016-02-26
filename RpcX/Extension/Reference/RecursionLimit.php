<?php

/**
 * RecursionLimit.php  Recursion limit exception
 *
 * Exception class to model Json RPC-X reference recursion limit exception.
 *
 */

namespace Json\RpcX\Extension\Reference;

/**
 * Needed for:
 *  - Predefined
 *
 */
use \Json\RpcX\Exception\Predefined;

/**
 * Exception class to model Json RPC-X reference recursion limit exception.
 *
 */
final class RecursionLimit extends Predefined {

  /**
   * Json RPC-X error code to use
   *
   * @var int
   */
  const CODE = -32682;

  /**
   * Json RPC-X message to use
   *
   * @var string
   */
  const MESSAGE = 'Reference recursion limit';

}
