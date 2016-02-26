<?php

/**
 * Extension.php  Json RPC-X TIMING extension
 *
 * Class to implement the TIMING extension.
 *
 */

namespace Json\RpcX\Extension\Timing;

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
 *  - Tools::microtime()
 *
 */
use \Json\RpcX\Tools;
/**
 * Needed for:
 *  - Timeout
 *
 */
use \Json\RpcX\Extension\Timing\Timeout;
/**
 * Needed for:
 *  - Exception::methodNotFound(),
 *  - Exception::invalidParams()
 *
 */
use \Json\RpcX\Exception;

/**
 * Class to implement the TIMING extension
 *
 * This class implements the TIMING extension
 *
 */
class Extension implements ExtensionInterface {

  /**
   * Deal with boilerplate
   *
   */
  use ExtensionTrait;

  /**
   * Timestamp delta to wait
   *
   * @var integer
   */
  protected $wait = null;

  /**
   * Timestamp for sending
   *
   * @var integer
   */
  protected $sent = null;

  /**
   * Timestamp for expiring
   *
   * @var integer
   */
  protected $before = null;

  /**
   * Timestamp for reception
   *
   * @var integer
   */
  protected $received = null;

  /**
   * Constructor merely sets the optional waiting time delta
   *
   * @param int|null $wait  Maximum waiting time before expiring, or null if no expiration is to be set
   * @throws \Exception
   */
  public function __construct($wait = null) {
    if (null !== $wait) {
      if (($iwait = (int) $wait) <= 0) {
        throw new \Exception(__CLASS__ . ": wait value '{$wait}' is not a positive integer");
      }
      $this->wait = $iwait;
    } else {
      $this->wait = null;
    }
  }

  /**
   * Return the extension's metadata collected from the request/response cycle
   *
   * @return mixed
   */
  public function getMetadata() {
    return [
        'sent'     => $this->sent,
        'before'   => $this->before,
        'received' => $this->received,
    ];
  }

  /**
   * Process an 'rpc.timing.*' command
   *
   * Recognized commands:
   *  - 'now': return the current microseconds since the epoch.
   *
   * @param string $method  Method to execute
   * @param array $params  Parameters given
   * @return mixed
   * @throws Exception
   */
  public function command($method, array $params = []) {
    switch (strtolower($method)) {
      case 'now':
        if ([] !== $params) {
          throw Exception::invalidParams();
        }
        return Tools::microtime();
      default:
        throw Exception::methodNotFound(['method' => "rpc.x.timing.{$method}"]);
    }
  }

  /**
   * Execute the preEncodeRequest hook by adding sent and before fields
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
    $request->timing       = new \stdClass();
    $request->timing->sent = Tools::microtime();
    if (null !== $this->wait) {
      $request->timing->before = $request->timing->sent + $this->wait;
    }
    return $request;
  }

  /**
   * Execute the postDecodeRequest hook by ...
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
    if (property_exists($request, 'timing')) {
      if (property_exists($request->timing, 'sent')) {
        $this->sent = $request->timing->sent;
      }
      if (property_exists($request->timing, 'before')) {
        $this->before = $request->timing->before;

        $now = Tools::microtime();
        if ($this->before < $now) {
          throw new Timeout(['before' => $this->before, 'now' => $now]);
        }
      }

      unset($request->timing);
      $this->received = Tools::microtime();
    }
    return $request;
  }

  /**
   * Execute the postDecodeResponse hook by extracting the extension's fields
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
    if (property_exists($response, 'timing')) {
      if (property_exists($response->timing, 'sent')) {
        $this->sent = $response->timing->sent;
      }
      if (property_exists($response->timing, 'before')) {
        $this->sent = $response->timing->before;
      }
      if (property_exists($response->timing, 'received')) {
        $this->sent = $response->timing->received;
      }
      unset($response->timing);
    }
    return $response;
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
    $timing = new \stdClass();

    if (null !== $this->sent) {
      $timing->sent = $this->sent;
    }
    if (null !== $this->before) {
      $timing->before = $this->before;
    }
    if (null !== $this->received) {
      $timing->received = $this->received;
    }

    if (0 !== count((array) $timing)) {
      $response->timing = $timing;
    }

    return $response;
  }

  /**
   * Return the postDecodeRequest hook priority for this instance, fixed at 0
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  public function postDecodeRequestPriority() {
    return 0;
  }

  /**
   * Return the preEncodeRequest hook priority for this instance, fixed at 0
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  public function preEncodeRequestPriority() {
    return 0;
  }

  /**
   * Return the preEncodeResponse hook priority for this instance, fixed at 0
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  public function preEncodeResponsePriority() {
    return 0;
  }

  /**
   * Return the postDecodeResponse hook priority for this instance, fixed at 0
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  public function postDecodeResponsePriority() {
    return 0;
  }

}
