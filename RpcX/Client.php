<?php

/**
 * Client.php  Json RPC-X Client class
 *
 * Json RPC-X Client.
 *
 */

namespace Json\RpcX;

/**
 * Needed for:
 *  - Rpcx
 *
 */
use \Json\Rpcx;
/**
 * Needed for:
 *  - Tools::jsonDecode(),
 *  - Tools::jsonEncode(),
 *  - Tools::validateResponse()
 *
 */
use \Json\RpcX\Tools;
/**
 * Needed for:
 *  - Exception
 *
 */
use \Json\RpcX\Exception;
/**
 * Needed for:
 *  - ClientChain
 *
 */
use \Json\RpcX\ClientChain;

/**
 * Json RPC-X Client
 *
 */
class Client extends Rpcx {

  /**
   * Backend handler
   *
   * A backend handler is a callable supporting the following arguments:
   *  - string $action: the action being attempted, this value further
   *        determines the following parameters, possible values are:
   *    - 'call': try to execute the call given, parameters are:
   *      - string $request: Json RPC-X request to send, as string.
   *        This call should return the server's Json RPC-X response, or false
   *        in case of errors.
   *
   * @var callable
   */
  protected $handler = null;

  /**
   * Request queue
   *
   * @var array
   */
  protected $queue = [];

  /**
   * Cache the last result seen
   *
   * @var array|object|null
   */
  protected $result = null;

  /**
   * Cache the last metadata seen
   *
   * @var array|null
   */
  protected $metadata = null;

  /**
   * Register a new extension provider to be used, it will return true on success, false on failure
   *
   * @param callable $extensionProvider  Extension provider to register
   * @return boolean
   */
  protected static function extend(Client $client, callable $extensionProvider) {
    return $client->addExtension($extensionProvider);
  }

  /**
   * Static public constructor
   *
   * @param callable $handler  Backend handler to use
   * @return self
   */
  protected static function create(callable $handler) {
    return new self($handler);
  }

  /**
   * Retrieve the last results of a given client
   *
   * This function returns the last results cached for a given client, note,
   * though, that the results are also returned upon calling "->exec" fluidly,
   * this function is here for symmetry with metadata() and convenience.
   *
   * NB: this function needs to be declared "protected" because PHP will
   *     gleefully call it in a non-static context; the magic "__callStatic"
   *     method forwards calls here nevertheless.
   *
   * @param Client $client  Client to retrieve the last results for
   * @return array|object|null
   */
  protected static function result(Client $client) {
    return $client->result;
  }

  /**
   * Retrieve the last metadata of a given client
   *
   * This function returns the last metadata cached for a given client, note
   * that this is the only way of retrieving the metadata.
   *
   * NB: this function needs to be declared "protected" because PHP will
   *     gleefully call it in a non-static context; the magic "__callStatic"
   *     method forwards calls here nevertheless.
   *
   * @param Client $client  Client to retrieve the last metada for
   * @return array|null
   */
  protected static function metadata(Client $client) {
    return $client->metadata;
  }

  /**
   * Flush the given client's queue
   *
   * NB: this function needs to be declared "protected" because PHP will
   *     gleefully call it in a non-static context; the magic "__callStatic"
   *     method forwards calls here nevertheless.
   *
   * @param Client $client  Client to flush the queue for
   * @return self
   */
  protected static function flush(Client $client) {
    $client->queue = [];
    return $client;
  }

  /**
   * Duplicate the given client, creating a fresh one with the same handler
   *
   * NB: this function needs to be declared "protected" because PHP will
   *     gleefully call it in a non-static context; the magic "__callStatic"
   *     method forwards calls here nevertheless.
   *
   * @param Client $client  Client to duplicate
   * @return self
   */
  protected static function dup(Client $client) {
    $return                     = new self($client->handler);
    $return->extensionProviders = $client->extensionProviders;
    return $return;
  }

  /**
   * Constructor merely sets the backend handler to use
   *
   * @param callable $handler  Backend handler to use
   */
  public function __construct(callable $handler = null) {
    $this->handler = $handler;
  }

  /**
   * Parse a namespace, call verb, or modifier
   *
   * @param string $name  Namespace, call verb or modifier
   * @return self
   * @throws \Exception
   */
  public function __get($name) {
    return new ClientChain($this, ClientChain::NAME, $name);
  }

  /**
   * Parse a namespace, call verb, or modifier
   *
   * @param string $name  Namespace, call verb or modifier
   * @param array $args  Verb or modifier's argument(s)
   * @return self
   * @throws \Exception
   */
  public function __call($name, array $args) {
    return new ClientChain($this, ClientChain::PARAMS, $name, $args);
  }

  /**
   * Act as a proxy for static functions
   *
   * This magic method is needed in order to prevent PHP from calling a
   * static function from a non-static context.
   *
   * @param string $name
   * @param array $args
   * @return mixed
   */
  public static function __callStatic($name, array $args) {
    switch (strtolower($name)) {
      case 'extend':
        return self::extend(...$args);
      case 'create':
        return self::create(...$args);
      case 'result':
        return self::result(...$args);
      case 'metadata':
        return self::metadata(...$args);
      case 'flush':
        return self::flush(...$args);
      case 'dup':
        return self::dup(...$args);
      default:
        throw new \Exception(__CLASS__ . ": unknown manipulator '{$name}'");
    }
  }

  /**
   * Execute the queued requests and return the results
   *
   * This function will empty the request queue, build a Json RPC-X request
   * out of it, and parse the result, setting the results and metadata caches
   * in the process.
   *
   * If the queue consisted solely of notifications, null will be returned;
   * if a single request was found on queue, a single result (or exception)
   * will be returned; otherwise, an array indexed by request id will be
   * returned, containing either results, or exceptions for that particular
   * request id.
   *
   * @return array|mixed|null
   */
  protected function exec() {
    if (null === $this->handler) {
      throw new \Exception(__CLASS__ . ': cannot execute with a null handler');
    }
    $extensions = $this->getExtensions();

    $jsonRequests = [];
    $requestIds   = [];
    $idToPos      = [];

    try {
      $requests = self::extractRequests($this->queue);
      $nreqs    = count($requests);
      for ($i = 0; $i < $nreqs; $i++) {
        // apply preEncodeRequest hook and encode
        $jsonRequests[] = Tools::jsonEncode(self::runHook($extensions, 'preEncodeRequest', $requests[$i]['request']));
        // extract ids for non-notification requests and map their positions
        if (false !== $requests[$i]['id']) {
          $requestIds[]                 = $requests[$i]['id'];
          $idToPos[$requests[$i]['id']] = $i;
        }
      }
    } catch (\Exception $e) {
      throw new \Exception(__CLASS__ . ': request preflight failed', 0, $e);
    }
    // NB: this will NEVER send a batch of only one request
    $jsonRequest = 1 === count($jsonRequests) ? $jsonRequests[0] : '[' . implode(',', $jsonRequests) . ']';

    // try to execute the call
    try {
      $jsonResponse = call_user_func($this->handler, 'call', $jsonRequest);
    } catch (\Exception $e) {
      throw new \Exception(__CLASS__ . ': handler failed to send request', 0, $e);
    }
    if (false === $jsonResponse) {
      throw new \Exception(__CLASS__ . ': handler failed to send request');
    }

    // reset caches and queue
    $this->metadata = $this->result   = null;
    $this->queue    = [];

    // decode response
    try {
      $responses = Tools::jsonDecode($jsonResponse);
    } catch (\Exception $e) {
      throw new \Exception(__CLASS__ . ': response decoding failed', 0, $e);
    }
    // deal with empty response
    if (null === $responses) {
      if ([] === $requestIds) {
        return null;
      } else {
        throw new \Exception(__CLASS__ . ': empty response from server');
      }
    }
    // turn responses into an array
    if (!is_array($responses)) {
      $responses = [$responses];
    }

    // process responses
    $results     = [];
    $metadata    = [];
    $responseIds = [];
    // iterate through the responses
    foreach ($responses as $response) {
      $id = null;
      try {
        // run postDecodeResponse hook
        $newResponse = self::runHook($extensions, 'postDecodeResponse', $response);
        // validate the response
        if (!Tools::validateResponse($newResponse)) {
          throw new \Exception(__CLASS__ . ': invalid response');
        }
        // validate id
        $responseIds[] = $id            = $newResponse->id;
        if (!in_array($id, $requestIds)) {
          throw new \Exception(__CLASS__ . ": unknown id '{$id}'");
        }
        // extract callback
        $callback = $requests[$idToPos[$id]]['callback'];

        // determine response type and accumulate results
        if (property_exists($newResponse, 'result')) {
          $results[$id] = $callback !== false ? call_user_func($callback, $newResponse->result) : $newResponse->result;
        } else {
          $results[$id] = Exception::fromObject($newResponse->error);
        }
        // extract metadata
        $metadata[$id] = array_map(function ($extension) {
          return $extension->getMetadata();
        }, $extensions);
      } catch (\Exception $e) {
        if (null !== $id) {
          $results[$id]  = $e;
          $metadata[$id] = null;
        } else {
          $results[]  = $e;
          $metadata[] = null;
        }
      }
    }
    unset($extensions);

    // fill in missing responses
    foreach (array_diff($requestIds, $responseIds) as $missingId) {
      $results[$missingId]  = new \Exception(__CLASS__ . ': missing response');
      $metadata[$missingId] = null;
    }

    // store results
    if (1 === count($requestIds)) {
      $this->result   = array_values($results)[0];
      $this->metadata = array_values($metadata)[0];
    } else {
      $this->result   = $results;
      $this->metadata = $metadata;
    }

    // return
    return $this->result;
  }

  /**
   * Extract ids, requests, and callbacks from the given queue
   *
   * This method will return an array with fields:
   *  - 'id': the request if, false if this is a notification,
   *  - 'request': Json RPC-X request object for this particular request,
   *  - 'callback': a callback to apply to the result, or false if no callback
   *        is to be applied (notifications always have this field set to
   *        false).
   *
   * @param array $queue  Queue to extract data from
   * @return array
   * @throws \Exception
   */
  protected static function extractRequests(array $queue) {
    // extract known ids
    $ids = array_map(function ($spec) {
      // keep only the ids
      return $spec['id'];
    }, array_filter($queue, function ($spec) {
              // we're only interested in ids given for non-notifications
              return false !== $spec['id'] && false === $spec['notify'];
            }));
    $currId = 0;
    // verify duplicates
    if (count($ids) !== count(array_unique($ids))) {
      throw new \Exception(__CLASS__ . ': duplicate ids found');
    }

    // resulting array
    $return = [];
    foreach ($queue as $spec) {
      // extract id and callback
      if (false === $spec['notify']) {
        if (false !== $spec['id']) {
          $id = $spec['id'];
        } else {
          // look for an unused id
          while (in_array($currId, $ids)) {
            $currId++;
          }
          $ids[] = $id    = $currId;
        }
      } else {
        $id = false;
      }

      $request          = new \stdClass();
      $request->jsonrpc = '2.0';
      $request->method  = $spec['method'];

      if (false !== $spec['params']) {
        $request->params = $spec['params'];
      }

      if (false !== $id) {
        $request->id = $id;
        $callback    = $spec['callback'];
      } else {
        $callback = false;
      }

      $return[] = [
          'id'       => $id,
          'request'  => $request,
          'callback' => $callback,
      ];
    }

    return $return;
  }

}
