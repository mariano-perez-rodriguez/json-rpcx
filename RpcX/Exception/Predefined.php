<?php

/**
 * Predefined.php  Predefined exceptions
 *
 * Abstract base class every predefined Json RPC-X exception derives from.
 *
 */

namespace Json\RpcX\Exception;

/**
 * Needed for:
 *  - Exception
 *
 */
use \Json\RpcX\Exception;

/**
 * Abstract base class every predefined Json RPC-X exception derives from.
 *
 */
abstract class Predefined extends Exception {

  /**
   * Constructor call the parent one using static constants
   *
   * @param mixed $data  Data value to use
   */
  public function __construct($data = null) {
    parent::__construct(static::CODE, static::MESSAGE, $data);
  }

}
