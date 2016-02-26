<?php

/**
 * InvalidHinting.php  Invalid hinting exception
 *
 * Exception class to model Json RPC-X invalid hinting exception.
 *
 */

namespace Json\RpcX\Extension\Hinting;

/**
 * Needed for:
 *  - Predefined
 *
 */
use \Json\RpcX\Exception\Predefined;

/**
 * Exception class to model Json RPC-X invalid hinting exception.
 *
 */
final class InvalidHinting extends Predefined {

  /**
   * Json RPC-X error code to use
   *
   * @var int
   */
  const CODE = -32671;

  /**
   * Json RPC-X message to use
   *
   * @var string
   */
  const MESSAGE = 'Invalid hinting';

}
