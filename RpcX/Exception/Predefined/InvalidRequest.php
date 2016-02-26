<?php

/**
 * InvalidRequest.php  Invalid request exception
 *
 * Exception class to model Json RPC-X invalid request exception.
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
 * Exception class to model Json RPC-X invalid request exception.
 *
 */
final class InvalidRequest extends Predefined {

  /**
   * Json RPC-X error code to use
   *
   * @var int
   */
  const CODE = -32600;

  /**
   * Json RPC-X message to use
   *
   * @var string
   */
  const MESSAGE = 'Invalid request';

}
