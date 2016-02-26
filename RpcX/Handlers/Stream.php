<?php

/**
 * Stream.php  Json RPC-X Stream handler
 *
 * Json RPC-X Stream handler.
 *
 */

namespace Json\RpcX\Handlers;

/**
 * Json RPC-X Stream handler
 *
 */
class Stream {

  /**
   * Read stream handle to use
   *
   * @var resource
   */
  protected $readHandle = null;

  /**
   * Write stream handle to use
   *
   * @var resource
   */
  protected $writeHandle = null;

  /**
   * Construct a handler from a (pair of) resource(s)
   *
   * If only one resource is given, it is used for both reading and writing,
   * otherwise.
   *
   * @param resource $readHandle  Read handle to use
   * @param resource $writeHandle  Write handle to use
   * @throws \Exception
   */
  public function __construct($readHandle, $writeHandle = null) {
    if (null === $writeHandle) {
      $msg         = ['', ''];
      $writeHandle = $readHandle;
    } else {
      $msg = ['read ', 'write '];
    }
    if (!is_resource($readHandle)) {
      throw new \Exception(__CLASS__ . ": {$msg[0]}handle passed is not a resource");
    }
    if (false === strpos(stream_get_meta_data($readHandle)['mode'], 'r')) {
      throw new \Exception(__CLASS__ . ": {$msg[0]}handle passed is not readable");
    }
    if (!is_resource($writeHandle)) {
      throw new \Exception(__CLASS__ . ": {$msg[1]}handle passed is not a resource");
    }
    if (false === strpos(stream_get_meta_data($writeHandle)['mode'], 'w')) {
      throw new \Exception(__CLASS__ . ": {$msg[1]}handle passed is not writeable");
    }
    $this->readHandle  = $readHandle;
    $this->writeHandle = $writeHandle;
  }

  /**
   * Execute the given action with the given parameters
   *
   * This method writes to the write stream, returning false on errors, and
   * then read from the read stream and returns the result.
   *
   * @param string $action  Action to take
   * @param mixed ...$params  Parameters for the action given
   * @return mixed
   * @throws \Exception
   */
  public function __invoke($action, ...$args) {
    switch (strtolower($action)) {
      case 'call':
        if (1 !== count($args)) {
          throw new \Exception(__CLASS__ . ": wrong number of arguments passed to 'call' action");
        }
        if (false === fwrite($this->writeHandle, $args[0])) {
          return false;
        }
        return stream_get_contents($this->readHandle);
      default:
        throw new \Exception(__CLASS__ . ": unknown action '{$action}'");
    }
  }

}
