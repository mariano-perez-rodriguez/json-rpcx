<?php

/**
 * MissingAuth.php  Missing auth exception
 *
 * Exception class to model Json RPC-X missing auth exception.
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
 * Exception class to model Json RPC-X missing auth exception.
 *
 */
final class MissingAuth extends Predefined {

  /**
   * Json RPC-X error code to use
   *
   * @var int
   */
  const CODE = -32651;

  /**
   * Json RPC-X message to use
   *
   * @var string
   */
  const MESSAGE = 'Missing auth';

}
