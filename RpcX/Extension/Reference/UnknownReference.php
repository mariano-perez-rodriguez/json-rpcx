<?php

/**
 * unknownReference.php  Unknown reference exception
 *
 * Exception class to model Json RPC-X unknown reference exception.
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
 * Exception class to model Json RPC-X unknown reference exception.
 *
 */
final class UnknownReference extends Predefined {

  /**
   * Json RPC-X error code to use
   *
   * @var int
   */
  const CODE = -32681;

  /**
   * Json RPC-X message to use
   *
   * @var string
   */
  const MESSAGE = 'Unknown reference';

}
