<?php

/**
 * Exception.php  Json RPC-X Exception base class
 *
 * Base class every other Json RPC-X exception derives from.
 *
 */

namespace Json\RpcX;

/**
 * Base class every other Json RPC-X exception derives from.
 *
 */
class Exception extends \Exception {

  /**
   * Additional data to use
   *
   * @var mixed
   */
  protected $data = null;

  /**
   * Constructor calls the parnt one and initialices data field
   *
   * @param int $code  Error code to use
   * @param string $message  Message to use
   * @param mixed $data  Data value to use
   */
  public function __construct($code, $message, $data = null) {
    parent::__construct($message, $code);
    $this->data = $data;
  }

  /**
   * The __toString() magic method simply returns null
   *
   * @return null
   */
  public function __toString() {
    return null;
  }

  /**
   * Return additional data
   *
   * @return mixed
   */
  final public function getData() {
    return $this->data;
  }

  /**
   * Convert the exception to an error object
   *
   * @return \stdClass
   */
  final public function asObject() {
    $error = new \stdClass();

    $error->code    = $this->getCode();
    $error->message = $this->getMessage();

    // only add "data" field if not null
    if (null !== ($data = $this->getData())) {
      $error->data = $data;
    }

    return $error;
  }

  /**
   * Construct a new Exception from a Json RPC-X error object
   *
   * @param \stdClass $error  Error object to use for construction
   * @return self
   */
  final public static function fromObject(\stdClass $error) {
    $code    = property_exists($error, 'code') ? $error->code : 0;
    $message = property_exists($error, 'message') ? $error->message : '';
    $data    = property_exists($error, 'data') ? $error->data : null;
    return new self($code, $message, $data);
  }

  //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
  //  Service methods
  //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  /**
   * Build a new ParseError from the given arguments
   *
   * @param mixed $data  Data to use
   * @return \Json\RpcX\Exception\Predefined\ParseError
   */
  public static function parseError($data = null) {
    return new Exception\Predefined\ParseError($data);
  }

  /**
   * Build a new InvalidRequest from the given arguments
   *
   * @param mixed $data  Data to use
   * @return \Json\RpcX\Exception\Predefined\InvalidRequest
   */
  public static function invalidRequest($data = null) {
    return new Exception\Predefined\InvalidRequest($data);
  }

  /**
   * Build a new MethodNotFound from the given arguments
   *
   * @param mixed $data  Data to use
   * @return \Json\RpcX\Exception\Predefined\MethodNotFound
   */
  public static function methodNotFound($data = null) {
    return new Exception\Predefined\MethodNotFound($data);
  }

  /**
   * Build a new InvalidParams from the given arguments
   *
   * @param mixed $data  Data to use
   * @return \Json\RpcX\Exception\Predefined\InvalidParams
   */
  public static function invalidParams($data = null) {
    return new Exception\Predefined\InvalidParams($data);
  }

  /**
   * Build a new InternalError from the given arguments
   *
   * @param mixed $data  Data to use
   * @return \Json\RpcX\Exception\Predefined\InternalError
   */
  public static function internalError($data = null) {
    return new Exception\Predefined\InternalError($data);
  }

  /**
   * Build a new ServerError from the given arguments
   *
   * @param int $code  Code to use
   * @param string $message  Message to use
   * @param mixed $data  Data to use
   * @return \Json\RpcX\Exception\ServerError
   */
  public static function serverError($code, $message, $data = null) {
    return new Exception\ServerError($code, $message, $data);
  }

  /**
   * Build a new ApplicationError from the given arguments
   *
   * @param int $code  Code to use
   * @param string $message  Message to use
   * @param mixed $data  Data to use
   * @return \Json\RpcX\Exception\ApplicationError
   */
  public static function applicationError($code, $message, $data = null) {
    return new Exception\ApplicationError($code, $message, $data);
  }

}
