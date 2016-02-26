<?php

/**
 * Extension.php  Json RPC-X HINTING extension
 *
 * Class to implement the HINTING extension.
 *
 */

namespace Json\RpcX\Extension\Hinting;

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
 * Needed for:
 *  - InvalidHinting
 *
 */
use \Json\RpcX\Extension\Hinting\InvalidHinting;
/**
 * Needed for:
 *  - RecursionLimit
 *
 */
use \Json\RpcX\Extension\Hinting\RecursionLimit;

/**
 * Class to implement the HINTING extension
 *
 * This class implements the HINTING extension
 *
 */
class Extension implements ExtensionInterface {

  /**
   * Deal with boilerplate
   *
   */
  use ExtensionTrait;

  /**
   * Jsonclass identifier
   *
   * @var string
   */
  protected $jsonclass = '__jsonclass__';

  /**
   * Maximum recursion depth, or null to not apply one
   *
   * @var int|null
   */
  protected $maxDepth = 1024;

  /**
   * Apply jsonclass hinting
   *
   * This function will add jsonclass fields where appropriate.
   *
   * @param mixed $var  Value to transform
   * @param string $jsonClass  JsonClass tag to use
   * @param int|null $maxDepth  Maximum recursion depth, or null to not apply one
   * @param array &$map  Object map, mapping "source" object hashes to resulting objects
   * @param int $currDepth  Current recursion depth
   * @return mixed
   * @throws RecursionLimit
   */
  protected static function applyJsonclass($var, $jsonClass = '__jsonclass__', $maxDepth = 1024, array &$map = [], $currDepth = 0) {
    if (null !== $maxDepth && $currDepth > $maxDepth) {
      throw new RecursionLimit(['limit' => $maxDepth]);
    }
    $currDepth++;
    switch (true) {
      case is_array($var):
        $return = [];
        foreach ($var as $k => $v) {
          $return[$k] = call_user_func_array(__METHOD__, [$v, $jsonClass, $maxDepth, &$map, $currDepth]);
        }
        return $return;
      case is_object($var):
        if (!array_key_exists(($hash = spl_object_hash($var)), $map)) {
          if (!property_exists($var, $jsonClass) && 'stdClass' !== ($class = get_class($var))) {
            $return             = new \stdClass();
            $return->$jsonClass = [$class];  // NB: constructor arguments are never given
          } else {
            $return = $var;
          }
          foreach ($var as $name => $value) {
            $return->$name = call_user_func_array(__METHOD__, [$value, $jsonClass, $maxDepth, &$map, $currDepth]);
          }
          $map[$hash] = $return;
        }
        return $map[$hash];
      default:
        return $var;
    }
  }

  /**
   * De-apply jsonclass hinting
   *
   * @param mixed $var  Value to transform
   * @param string $jsonClass  JsonClass tag to use
   * @param int|null $maxDepth  Maximum recursion depth, or null to not apply one
   * @param array &$map  Object map, mapping "source" object hashes to resulting objects
   * @param int $currDepth  Current recursion depth
   * @return mixed
   * @throws InvalidHinting
   * @throws RecursionLimit
   */
  protected static function deapplyJsonclass($var, $jsonClass = '__jsonclass__', $maxDepth = 1024, array &$map = [], $currDepth = 0) {
    if (null !== $maxDepth && $currDepth > $maxDepth) {
      throw new RecursionLimit(['limit' => $maxDepth]);
    }
    $currDepth++;
    switch (true) {
      case is_array($var):
        $return = [];
        foreach ($var as $k => $v) {
          $return[$k] = call_user_func_array(__METHOD__, [$v, $jsonClass, $maxDepth, &$map, $currDepth]);
        }
        return $return;
      case is_object($var):
        if (!array_key_exists(($hash = spl_object_hash($var)), $map)) {
          foreach ($var as &$value) {
            $value = call_user_func_array(__METHOD__, [$value, $jsonClass, $maxDepth, &$map, $currDepth]);
          }
          if (!property_exists($var, $jsonClass)) {
            return $var;
          }
          if (!is_array($var->$jsonClass) || [] === $var->$jsonClass) {
            throw new InvalidHinting(['proposed' => $var->$jsonClass]);
          }
          $arguments = $var->$jsonClass;
          $class     = array_shift($arguments);
          if (!is_string($class) || !class_exists($class)) {
            throw new InvalidHinting(['proposed' => $var->$jsonClass]);
          }
          $return = new $class(...$arguments);
          foreach ($var as $n => $v) {
            if ($jsonClass !== $n) {
              $return->$n = $v;
            }
          }
          $map[$hash] = $return;
        }
        return $map[$hash];
      default:
        return $var;
    }
  }

  /**
   * Constructor merely sets up the jsonclass identifier to use
   *
   * @param string $jsonclass  Jsonclass identifier to use (defaults to "__jsonclass__")
   * @param int|null $maxDepth  Maximum recursion depth, or null to not apply one
   */
  public function __construct($jsonclass = '__jsonclass__', $maxDepth = 1024) {
    $this->jsonclass = $jsonclass;
    $this->maxDepth  = $maxDepth;
  }

  /**
   * Execute the preEncodeRequest hook by applying hinting
   *
   * This method is fed the unencoded request object and it must return
   * the "new" request object to consider.
   *
   * Note that, after all the preEncodeRequest hooks are executed, the resulting
   * request object must validate against the server's expectations;
   * with this in mind, this method should add "additional" fields to the
   * request.
   *
   * @param \stdClass $request  Request object to act upon
   * @return \stdClass
   */
  public function preEncodeRequest(\stdClass $request) {
    return self::applyJsonclass($request, $this->jsonclass, $this->maxDepth);
  }

  /**
   * Execute the postDecodeRequest hook by converting types
   *
   * This method is fed the decoded request object and it must return the
   * "new" request object to consider.
   *
   * Note that, after all postDecodeRequest hooks are executed, the resulting
   * request object must validate against the standard Json RPC format;
   * whith this in mind, this method should remove "consumed" fields from
   * the request.
   *
   * @param \stdClass $request  Request object to act upon
   * @return \stdClass
   */
  public function postDecodeRequest(\stdClass $request) {
    return self::deapplyJsonclass($request, $this->jsonclass, $this->maxDepth);
  }

  /**
   * Execute the postDecodeResponse hook by converting types
   *
   * This method is fed the unencoded response object and it must return
   * the "new" response object to consider.
   *
   * Note that, after all the preEncodeResponse hooks are executed, the resulting
   * response object must validate against the client's expectations;
   * with this in mind, this method should add "additional" fields to the
   * response.
   *
   * @param \stdClass $response  Response object to act upon
   * @return \stdClass
   */
  public function postDecodeResponse(\stdClass $response) {
    return self::deapplyJsonclass($response, $this->jsonclass, $this->maxDepth);
  }

  /**
   * Execute the preEncodeResponse hook by adding the needed fields
   *
   * This method is fed the unencoded response object and it must return
   * the "new" response object to consider.
   *
   * Note that, after all the preEncodeResponse hooks are executed, the resulting
   * response object must validate against the client's expectations;
   * with this in mind, this method should add "additional" fields to the
   * response.
   *
   * @param \stdClass $response  Response object to act upon
   * @return \stdClass
   */
  public function preEncodeResponse(\stdClass $response) {
    return self::applyJsonclass($response, $this->jsonclass, $this->maxDepth);
  }

  /**
   * Return the postDecodeRequest hook priority for this instance, fixed at -500
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  public function postDecodeRequestPriority() {
    return -500;
  }

  /**
   * Return the preEncodeRequest hook priority for this instance, fixed at 500
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  public function preEncodeRequestPriority() {
    return 500;
  }

  /**
   * Return the preEncodeResponse hook priority for this instance, fixed at 500
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  public function preEncodeResponsePriority() {
    return 500;
  }

  /**
   * Return the postDecodeResponse hook priority for this instance, fixed at -500
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  public function postDecodeResponsePriority() {
    return -500;
  }

}
