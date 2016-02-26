<?php

/**
 * InvalidPow.php  Invalid proof-of-work exception
 *
 * Exception class to model Json RPC-X invalid proof-of-work exception.
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
 * Exception class to model Json RPC-X invalid proof-of-work exception.
 *
 */
final class InvalidPow extends Predefined {

  /**
   * Json RPC-X error code to use
   *
   * @var int
   */
  const CODE = -32692;

  /**
   * Json RPC-X message to use
   *
   * @var string
   */
  const MESSAGE = 'Invalid pow';

}
