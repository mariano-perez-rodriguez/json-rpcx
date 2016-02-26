<?php

/**
 * Extension.php  Json RPC-X Null-object extension
 *
 * Class to implement the Null-object extension.
 *
 */

namespace Json\RpcX\Extension\Null;

/**
 * Needed for:
 *  - ExtensionInterface
 *
 */
use \Json\RpcX\ExtensionInterface;
/**
 * Needed for:
 *  - ExtensionTrait
 *
 */
use \Json\RpcX\ExtensionTrait;

/**
 * Class to implement the Null-object extension
 *
 * This class implements the Null-object extension
 *
 */
class Extension implements ExtensionInterface {

  /**
   * Deal with boilerplate
   *
   */
  use ExtensionTrait;
}
