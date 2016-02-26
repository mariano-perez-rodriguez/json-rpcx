<?php

/**
 * Extension.php  Json RPC-X REFERENCE extension
 *
 * Class to implement the REFERENCE extension.
 *
 */

namespace Json\RpcX\Extension\Reference;

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
 *  - UnknownReference
 *
 */
use \Json\RpcX\Extension\Reference\UnknownReference;
/**
 * Needed for:
 *  - RecursionLimit
 *
 */
use \Json\RpcX\Extension\Reference\RecursionLimit;
/**
 * Needed for:
 *  - DuplicateId
 *
 */
use \Json\RpcX\Extension\Reference\DuplicateId;

/**
 * Class to implement the REFERENCE extension
 *
 * This class implements the REFERENCE extension
 *
 */
class Extension implements ExtensionInterface {

  /**
   * Deal with boilerplate
   *
   */
  use ExtensionTrait;

  /**
   * Reference identifier
   *
   * @var string
   */
  protected $reference = '__ref__';

  /**
   * Identification identifier (?)
   *
   * @var string
   */
  protected $id = '__id__';

  /**
   * Maximum recursion depth, or null to not apply one
   *
   * @var int|null
   */
  protected $maxDepth = 1024;

  /**
   * Remove references by replacing them with reference placeholders
   *
   * @param mixed $var  Value to rid of references
   * @param string $reference  Reference tag to use
   * @param string $id  Id tag to use
   * @param int|null $maxDepth  Maximum recursion depth, or null to not apply one
   * @param array &$map  Object map to use (maps unique hashes to object instances)
   * @param int &$i  Internal counter to only modify actually referred-to objects
   * @param int $currDepth  Current recursion depth
   * @return mixed
   */
  protected static function applyRef($var, $reference = '__ref__', $id = '__id__', $maxDepth = 1024, array &$map = [], &$i = 0, $currDepth = 0) {
    if (null !== $maxDepth && $currDepth > $maxDepth) {
      throw new RecursionLimit(['limit' => $maxDepth]);
    }
    $currDepth++;
    switch (true) {
      case is_array($var):
        $return = [];
        foreach ($var as $k => $v) {
          $return[$k] = call_user_func_array(__METHOD__, [$v, $reference, $id, $maxDepth, &$map, &$i, $currDepth]);
        }
        return $return;
      case is_object($var):
        if (array_key_exists(($hash = spl_object_hash($var)), $map)) {
          if (!property_exists($map[$hash], $id)) {
            $map[$hash]->$id = $i++;
          }
          return (object) [$reference => $map[$hash]->$id];
        } else {
          $map[$hash] = $var;
          foreach ($var as &$v) {
            $v = call_user_func_array(__METHOD__, [$v, $reference, $id, $maxDepth, &$map, &$i, $currDepth]);
          }
          return $var;
        }
      default:
        return $var;
    }
  }

  /**
   * Look for all the id indicators found on the given value and fill the given map with them
   *
   * Incidentally, this method will also rid the value given of
   * identification descriptors.
   *
   * @param mixed $var  Value to scan for ids
   * @param array &$map  Object map to load (this will map ids to object instances)
   * @param string  Id tag to use
   * @param int $currDepth  Current recursion depth
   * @param int|null $maxDepth  Maximum recursion depth, or null to not apply one
   */
  protected static function loadIds($var, array &$map, $id = '__id__', $currDepth = 0, $maxDepth = 1024) {
    if (null !== $maxDepth && $currDepth > $maxDepth) {
      throw new RecursionLimit(['limit' => $maxDepth]);
    }
    $currDepth++;
    switch (true) {
      case is_array($var):
        foreach ($var as $v) {
          call_user_func_array(__METHOD__, [$v, &$map, $id, $currDepth, $maxDepth]);
        }
        break;
      case is_object($var):
        if (property_exists($var, $id)) {
          if (array_key_exists($var->$id, $map) && $map[$var->$id] !== $var) {
            throw new DuplicateId(['id' => $var->$id]);
          }
          $map[$var->$id] = $var;
          unset($var->$id);
        }
        foreach ($var as $v) {
          call_user_func_array(__METHOD__, [$v, &$map, $id, $currDepth, $maxDepth]);
        }
        break;
      default:
        break;
    }
  }

  /**
   * Restore references from reference indicators for the given value
   *
   * @param mixed $var  Value that needs its references restored
   * @param string $reference  Reference tag to use
   * @param string $id  Id tag to use
   * @param int|null $maxDepth  Maximum recursion depth, or null to not apply one
   * @param array $map  Object instance map (this one maps ids to object instances)
   * @param int $currDepth  Current recursion depth
   * @return mixed
   * @throws Exception
   */
  protected static function deapplyRef($var, $reference = '__ref__', $id = '__id__', $maxDepth = 1024, array $map = null, $currDepth = 0) {
    if (null !== $maxDepth && $currDepth > $maxDepth) {
      throw new RecursionLimit(['limit' => $maxDepth]);
    }
    $currDepth++;
    if (null === $map) {
      $map = [];
      self::loadIds($var, $map, $id);
    }
    switch (true) {
      case is_array($var):
        $return = [];
        foreach ($var as $k => $v) {
          $return[$k] = call_user_func(__METHOD__, $v, $reference, $id, $maxDepth, $map, $currDepth);
        }
        return $return;
      case is_object($var):
        if ([$reference] === array_keys((array) $var)) {
          if (array_key_exists($var->$reference, $map)) {
            return $map[$var->$reference];
          } else {
            throw new UnknownReference($var->$reference);
          }
        } else {
          foreach ($var as &$v) {
            $v = call_user_func(__METHOD__, $v, $reference, $id, $maxDepth, $map, $currDepth);
          }
          return $var;
        }
      default:
        return $var;
    }
  }

  /**
   * Constructor merely sets up the reference and id identifiers to use
   *
   * @param string $reference  Reference identifier to use (defaults to "__ref__")
   * @param string $id  Id identifier to use (defaults to "__id__")
   * @param int|null $maxDepth  Maximum recursion depth, or null to not apply one
   */
  public function __construct($reference = '__ref__', $id = '__id__', $maxDepth = 1024) {
    $this->reference = $reference;
    $this->id        = $id;
    $this->maxDepth  = $maxDepth;
  }

  /**
   * Execute the preEncodeRequest hook by elimminating duplicates
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
    return self::applyRef($request, $this->reference, $this->id, $this->maxDepth);
  }

  /**
   * Execute the postDecodeRequest hook by setting references
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
    return self::deapplyRef($request, $this->reference, $this->id, $this->maxDepth);
  }

  /**
   * Execute the postDecodeResponse hook by setting references
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
    return self::deapplyRef($response, $this->reference, $this->id, $this->maxDepth);
  }

  /**
   * Execute the preEncodeResponse hook by elimminating duplicates
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
    return self::applyRef($response, $this->reference, $this->id, $this->maxDepth);
  }

  /**
   * Return the postDecodeRequest hook priority for this instance, fixed at -400
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  public function postDecodeRequestPriority() {
    return -400;
  }

  /**
   * Return the preEncodeRequest hook priority for this instance, fixed at 400
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  public function preEncodeRequestPriority() {
    return 400;
  }

  /**
   * Return the preEncodeResponse hook priority for this instance, fixed at 400
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  public function preEncodeResponsePriority() {
    return 400;
  }

  /**
   * Return the postDecodeResponse hook priority for this instance, fixed at -400
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  public function postDecodeResponsePriority() {
    return -400;
  }

}
