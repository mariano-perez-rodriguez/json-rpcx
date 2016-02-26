<?php

/**
 * DuplicateId.php  Duplicate ID exception
 *
 * Exception class to model Json RPC-X duplicate ID exception.
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
 * Exception class to model Json RPC-X duplicate ID exception.
 *
 */
final class DuplicateId extends Predefined {

  /**
   * Json RPC-X error code to use
   *
   * @var int
   */
  const CODE = -32683;

  /**
   * Json RPC-X message to use
   *
   * @var string
   */
  const MESSAGE = 'Duplicate ID';

}
