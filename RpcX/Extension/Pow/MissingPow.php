<?php

/**
 * MissingPow.php  Missing proof-of-work exception
 *
 * Exception class to model Json RPC-X missing proof-of-work exception.
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
 * Exception class to model Json RPC-X missing proof-of-work exception.
 *
 */
final class MissingPow extends Predefined {

  /**
   * Json RPC-X error code to use
   *
   * @var int
   */
  const CODE = -32691;

  /**
   * Json RPC-X message to use
   *
   * @var string
   */
  const MESSAGE = 'Missing pow';

}
