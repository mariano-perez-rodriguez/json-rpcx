<?php

/**
 * Timeout.php  Timeout exception
 *
 * Exception class to model Json RPC-X timeout exception.
 *
 */

namespace Json\RpcX\Extension\Timing;

/**
 * Needed for:
 *  - Predefined
 *
 */
use \Json\RpcX\Exception\Predefined;

/**
 * Exception class to model Json RPC-X timeout exception.
 *
 */
final class Timeout extends Predefined {

  /**
   * Json RPC-X error code to use
   *
   * @var int
   */
  const CODE = -32661;

  /**
   * Json RPC-X message to use
   *
   * @var string
   */
  const MESSAGE = 'Timeout';

}
