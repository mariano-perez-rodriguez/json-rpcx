<?php

/**
 * Failed.php  Proof-of-work calculation failed exception
 *
 * Exception class to model Json RPC-X proof-of-work calculation failed exception.
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
 * Exception class to model Json RPC-X proof-of-work calculation failed exception.
 *
 */
final class Failed extends Predefined {

  /**
   * Json RPC-X error code to use
   *
   * @var int
   */
  const CODE = -32694;

  /**
   * Json RPC-X message to use
   *
   * @var string
   */
  const MESSAGE = 'Pow calculation failed';

}
