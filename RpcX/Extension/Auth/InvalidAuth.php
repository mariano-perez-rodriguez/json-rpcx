<?php

/**
 * InvalidAuth.php  Invalid auth exception
 *
 * Exception class to model Json RPC-X invalid auth exception.
 *
 */

namespace Json\RpcX\Extension\Auth;

/**
 * Needed for:
 *  - Predefined
 *
 */
use \Json\RpcX\Exception\Predefined;

/**
 * Exception class to model Json RPC-X invalid auth exception.
 *
 */
final class InvalidAuth extends Predefined {

  /**
   * Json RPC-X error code to use
   *
   * @var int
   */
  const CODE = -32652;

  /**
   * Json RPC-X message to use
   *
   * @var string
   */
  const MESSAGE = 'Invalid auth';

}
