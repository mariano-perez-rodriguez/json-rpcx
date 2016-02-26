<?php

/**
 * Extension.php  Json RPC-X SHORT extension
 *
 * Class to implement the SHORT extension.
 *
 */

namespace Json\RpcX\Extension\Short;

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
 * Class to implement the SHORT extension
 *
 * This class implements the SHORT extension
 *
 */
class Extension implements ExtensionInterface {

  /**
   * Deal with boilerplate
   *
   */
  use ExtensionTrait;

  /**
   * Whether to shorten the request
   *
   * @var boolean
   */
  protected $shortenRequest = true;

  /**
   * Whether to shorten the response
   *
   * @var boolean
   */
  protected $shortenResponse = false;

  /**
   * Whether to respond with short fields when request uses short fields
   *
   * @var boolean
   */
  protected $honourShortRequest = false;

  /**
   * Translations to apply
   *
   * @var array
   */
  protected $translations = [
      // translations to apply on a request (server-side)
      'serverRequest'  => [
          'a'          => 'auth',
          'i'          => 'id',
          'm'          => 'method',
          'p'          => 'params',
          's'          => 'service',
          // this translations will be applied on the values of the resulting fields
          '__values'   => [],
          // this translations will be applied recursively if the field used as
          // key exists in the object in question
          '__internal' => [
              'timing' => [
                  's' => 'sent',
                  'b' => 'before',
                  'r' => 'received',
              ],
              'pow'    => [
                  'a' => 'algo',
                  'n' => 'nonce',
                  'p' => 'proof',
              ],
          ],
          //
          // OPTIONAL
          //
          'j'          => 'jsonrpc',
          't'          => 'timing',
          'w'          => 'pow',
      ],
      // translations to apply on a request (client-side)
      'clientRequest'  => [
          'auth'       => 'a',
          'id'         => 'i',
          'method'     => 'm',
          'params'     => 'p',
          'service'    => 's',
          // this translations will be applied on the values of the resulting fields
          '__values'   => [],
          // this translations will be applied recursively if the field used as
          // key exists in the object in question
          '__internal' => [
              't' => [
                  'sent'     => 's',
                  'before'   => 'b',
                  'received' => 'r',
              ],
              'w' => [
                  'algo'  => 'a',
                  'nonce' => 'n',
                  'proof' => 'p',
              ],
          ],
          //
          // OPTIONAL
          //
          'jsonrpc'    => 'j',
          'timing'     => 't',
          'pow'        => 'w',
      ],
      // translations to apply on a response (server-side)
      'serverResponse' => [
          'error'      => 'e',
          'id'         => 'i',
          'result'     => 'r',
          'status'     => 's',
          // this translations will be applied on the values of the resulting fields
          '__values'   => [],
          // this translations will be applied recursively if the field used as
          // key exists in the object in question
          '__internal' => [
              // translations to apply on an "error" field
              'e' => [
                  'code'    => 'c',
                  'data'    => 'd',
                  'message' => 'm',
              ],
              't' => [
                  'sent'     => 's',
                  'before'   => 'b',
                  'received' => 'r',
              ],
          ],
          //
          // OPTIONAL
          //
          'jsonrpc'    => 'j',
          'timing'     => 't',
      ],
      // translations to apply on a response (client-side)
      'clientResponse' => [
          'e'          => 'error',
          'i'          => 'id',
          'r'          => 'result',
          's'          => 'status',
          // this translations will be applied on the values of the resulting fields
          '__values'   => [],
          // this translations will be applied recursively if the field used as
          // key exists in the object in question
          '__internal' => [
              // translations to apply on an "error" field
              'error'  => [
                  'c' => 'code',
                  'd' => 'data',
                  'm' => 'message',
              ],
              'timing' => [
                  's' => 'sent',
                  'b' => 'before',
                  'r' => 'received',
              ],
          ],
          //
          // OPTIONAL
          //
          'j'          => 'jsonrpc',
          't'          => 'timing',
      ],
  ];

  /**
   * Recursively apply the translations given to the object in question
   *
   * @param \stdClass $object  Object to apply translations to
   * @param array $translations  Translations to apply
   * @param array &$map  Object map, mapping "source" object hashes to resulting objects
   * @return \stdClass
   */
  protected static function applyTranslations(\stdClass $object, array $translations, array &$map = []) {
    if (!array_key_exists(($hash = spl_object_hash($object)), $map)) {
      $return = new \stdClass();
      // iterate through each property in turn
      foreach ($object as $field => $value) {
        if ('__internal' === $field || '__values' === $field) {
          throw new \Exception(__CLASS__ . ": unsupported field '{$field}'");
        } else if (array_key_exists($field, $translations)) {
          $return->{$translations[$field]} = $value;
        } else {
          $return->$field = $value;
        }
      }
      // deal with __values
      if (array_key_exists('__values', $translations)) {
        foreach ($translations['__values'] as $field => $replacements) {
          if (property_exists($return, $field)) {
            switch (true) {
              // object
              case is_object($return->{$field}):
              // array
              case is_array($return->{$field}):
                foreach ($return->{$field} as &$value) {
                  if (array_key_exists($value, $replacements)) {
                    $value = $replacements[$value];
                  }
                }
              // atomic
              default:
                if (array_key_exists($return->{$field}, $replacements)) {
                  $return->{$field} = $replacements[$return->{$field}];
                }
            }
          }
        }
      }
      // deal with __internal
      if (array_key_exists('__internal', $translations)) {
        foreach ($translations['__internal'] as $field => $internal) {
          if (property_exists($return, $field)) {
            $return->{$field} = static::applyTranslations($return->{$field}, $internal);
          }
        }
      }
      // store the resulting object
      $map[$hash] = $return;
    }
    // return the mapped object
    return $map[$hash];
  }

  /**
   * Determine whether a given object consist solely of shortened fields
   *
   * @param \stdClass $object  Object to check for short fields
   * @return boolean
   */
  protected static function isShortened(\stdClass $object) {
    return [1] === array_unique(array_map('strlen', array_keys(get_object_vars($object))), SORT_NUMERIC);
  }

  /**
   * Constructor merely sets the honouring strategy and translations to apply
   *
   * @param boolean $shortenRequest  Whether to shorten the request
   * @param boolean $honourShortRequest  Honouring strategy to use
   * @param array $translations  Translations to apply
   */
  public function __construct($shortenRequest = true, $honourShortRequest = true, array $translations = null) {
    $this->shortenRequest     = $shortenRequest;
    $this->honourShortRequest = $honourShortRequest;

    // defaults are applied statically
    $this->translations = $translations ? : $this->translations;
  }

  /**
   * Execute the preEncodeRequest hook by translating fields as needed
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
    if ($this->shortenRequest && array_key_exists('clientRequest', $this->translations)) {
      // remove 'jsonrpc' field if present
      if (property_exists($request, 'jsonrpc')) {
        unset($request->jsonrpc);
      }
      $request = static::applyTranslations($request, $this->translations['clientRequest']);
    }
    return $request;
  }

  /**
   * Execute the postDecodeRequest hook by translating fields as needed
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
    // check for shortened fields, otherwise, applyTranslations will fail on unshortened fields
    if (array_key_exists('serverRequest', $this->translations) && static::isShortened($request)) {
      $newRequest = static::applyTranslations($request, $this->translations['serverRequest']);
      // add 'jsonrpc' field if not present
      if (!property_exists($newRequest, 'jsonrpc')) {
        $newRequest->jsonrpc = '2.0';
      }
      // NB: ONLY USE NON-STRICT COMPARISON FOR OBJECTS HERE!!!
      $this->shortenResponse = $this->honourShortRequest && ($request != $newRequest);

      return $newRequest;
    } else {
      return $request;
    }
  }

  /**
   * Execute the preEncodeResponse hook
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
    if ($this->shortenResponse && array_key_exists('serverResponse', $this->translations)) {
      // remove 'jsonrpc' field if present
      if (property_exists($response, 'jsonrpc')) {
        unset($response->jsonrpc);
      }
      $response = static::applyTranslations($response, $this->translations['serverResponse']);
    }
    return $response;
  }

  /**
   * Execute the postDecodeResponse hook
   *
   * This method is fed the decoded response object and it must return the
   * "new" response object to consider.
   *
   * Note that, after all postDecodeResponse hooks are executed, the resulting
   * response object must validate against the standard Json RPC format;
   * whith this in mind, this method should remove "consumed" fields from
   * the response.
   *
   * @param \stdClass $response  Response object to act upon
   * @return \stdClass
   */
  public function postDecodeResponse(\stdClass $response) {
    // check for shortened fields, otherwise, applyTranslations will fail on unshortened fields
    if (array_key_exists('clientResponse', $this->translations) && static::isShortened($response)) {
      $newResponse = static::applyTranslations($response, $this->translations['serverRequest']);
      // add 'jsonrpc' field if not present
      if (!property_exists($newResponse, 'jsonrpc')) {
        $newResponse->jsonrpc = '2.0';
      }
      return $newResponse;
    } else {
      return $response;
    }
  }

  /**
   * Return the postDecodeRequest hook priority for this instance, fixed at ~PHP_INT_MAX
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  public function postDecodeRequestPriority() {
    return ~PHP_INT_MAX;
  }

  /**
   * Return the preEncodeRequest hook priority for this instance, fixed at PHP_INT_MAX
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  public function preEncodeRequestPriority() {
    return PHP_INT_MAX;
  }

  /**
   * Return the preEncodeResponse hook priority for this instance, fixed at PHP_INT_MAX
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  public function preEncodeResponsePriority() {
    return PHP_INT_MAX;
  }

  /**
   * Return the postDecodeResponse hook priority for this instance, fixed at ~PHP_INT_MAX
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  public function postDecodeResponsePriority() {
    return ~PHP_INT_MAX;
  }

}
