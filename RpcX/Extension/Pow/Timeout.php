<?php

/**
 * Timeout.php  Proof-of-work calculation timeout exception
 *
 * Exception class to model Json RPC-X proof-of-work calculation timeout exception.
 *
 */

namespace Json\RpcX\Extension\Pow;

/**
 * Needed for:
 *  - Predefined
 *
 */
use \Json\RpcX\Exception\Predefined;

/**
 * Exception class to model Json RPC-X proof-of-work calculation timeout exception.
 *
 */
final class Timeout extends Predefined {

  /**
   * Json RPC-X error code to use
   *
   * @var int
   */
  const CODE = -32693;

  /**
   * Json RPC-X message to use
   *
   * @var string
   */
  const MESSAGE = 'Pow calculation timeout';

}
