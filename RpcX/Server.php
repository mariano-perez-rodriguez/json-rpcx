<?php

/**
 * Server.php  Json RPC-X Server class
 *
 * Json RPC-X Server.
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
 *  - Tools::validateRequest(),
 *  - Tools::microtime(),
 *  - Tools::simpleGlob(),
 *  - Tools::extractCallSpecFromObject(),
 *  - Tools::extractCallSpecFromClass(),
 *  - Tools::extractCallSpecFromCallable()
 *
 */
use \Json\RpcX\Tools;
/**
 * Needed for:
 *  - Exception::methodNotFound(),
 *  - Exception::invalidParams()
 *
 */
use \Json\RpcX\Exception;

/**
 * Json RPC-X Server
 *
 */
class Server extends Rpcx {

  /**
   * Service registry
   *
   * This array maps service names to service descriptors.
   *
   * @var array
   */
  protected $services = [];

  /**
   * Register a new extension provider to be used, it will return true on success, false on failure
   *
   * @param callable $extensionProvider  Extension provider to register
   * @return boolean
   */
  public function extend(callable $extensionProvider) {
    return $this->addExtension($extensionProvider);
  }

  /**
   * Attach the given service provider under the given name
   *
   * This function will try to attach the given service provider using the
   * given name, and return an array of registered names, or false if any such
   * names already existed.
   *
   * A service provider may be:
   *  - a class name: all of its public static methods are registered, each
   *      under its own name formed by appending the method name to the given
   *      service name, separated by ".",
   *  - an object: all of its public non-static methods are registered, each
   *      under its own name formed by appending the method name to the given
   *      service name, separated by ".",
   *  - a callable: it is attached under the given name alone,
   * in case of object or class name attachment, magic methods are NOT
   * registered.
   *
   * @param string $name  Name to use
   * @param mixed $service  Service provider to try to attach
   * @return array|false
   * @throws \Exception
   */
  public function attach($name, $service) {
    if ('rpc.' === strtolower(substr($name, 0, 4))) {
      throw new \Exception(__CLASS__ . ": cannot attach service to 'rpc' namespace");
    }
    $services = false;
    // determine the type of service to attach
    switch (true) {
      // set up service from an object (not a closure)
      case is_object($service) && 'closure' !== strtolower(get_class($service)):
        $services = Tools::extractCallSpecFromObject($name, $service);
        break;
      // set up service from a class name
      case is_string($service) && class_exists($service):
        $services = Tools::extractCallSpecFromClass($name, $service);
        break;
      // set up service from an arbitrary callable
      case is_callable($service):
        $services = Tools::extractCallSpecFromCallable($name, $service);
        break;
      // unsupported service
      default:
        throw new \Exception(__CLASS__ . ': unsupported service type');
    }
    // determine if services could not be extracted
    if (false === $services) {
      return false;
    }
    // determine if duplicated services found
    if ([] !== array_intersect_key($this->services, $services)) {
      return false;
    }
    // register new services
    $this->services = array_merge($this->services, $services);
    // return registered service names
    return array_keys($services);
  }

  /**
   * Map a namespace to another, ie. add a namespace alias
   *
   * This method will make the second argument a service alias of the first,
   * adding mappings with the second argument as prefix for every service
   * under the first one.
   *
   * This method will return false if not all the new names thus generated
   * are original.
   *
   * @param string $existing  Existing namespace to alias
   * @param string $new  New namespace to create
   * @return array|false
   */
  public function alias($existing, $new) {
    $nexisting = trim($existing, '.') . '.';
    $nnew      = trim($new, '.') . '.';
    if ('rpc.' === strtolower(substr($nnew, 0, 4))) {
      throw new \Exception(__CLASS__ . ": cannot attach service to 'rpc' namespace");
    }

    $len      = strlen($nexisting);
    $services = [];
    foreach ($this->services as $name => $service) {
      if ($nexisting === substr($name, 0, $len)) {
        $services[$nnew . substr($name, $len)] = $service;
      }
    }
    // determine if duplicated services found
    if ([] !== array_intersect_key($this->services, $services)) {
      return false;
    }
    // register new services
    $this->services = array_merge($this->services, $services);
    // return registered service names
    return array_keys($services);
  }

  /**
   * Expose extension configuration method from parent
   *
   * @param string $extension  Name of the extension to configure
   * @param string $name  Configuration value name
   * @param mixed $value  Configuration value proper
   * @return self
   */
  public function config($extension, $name, $value) {
    if (!$this->configExtension($extension, $name, $value)) {
      throw new \Exception(__CLASS__ . ": extension configuration failed for '{$extension}' option '{$name}'");
    }
    return $this;
  }

  /**
   * Expose extension configuration reset method from parent
   *
   * @param string $extension  Name of the extension to reset configuration for
   * @return self
   */
  public function reset($extension) {
    if (!$this->resetExtension($extension)) {
      throw new \Exception(__CLASS__ . ": extension configuration reset failed for '{$extension}'");
    }
    return $this;
  }

  /**
   * Serve the given Json RPC-X request and return an appropriate response, or null if no response is to be sent
   *
   * @param string $requestJson  Json RPC-X request string
   * @return string|null
   */
  public function serve($requestJson) {
    // create extensions for this serving
    $extensions = $this->getExtensions();

    // decode
    try {
      if (null === ($requests = Tools::jsonDecode($requestJson, false))) {
        throw Exception::parseError();
      }
    } catch (Exception $e) {
      return self::buildErrorResponse($e);
    } catch (\Exception $e) {
      return self::buildErrorResponse(Exception::serverError(-32000, 'Internal error - unexpected exception'));
    }

    // determine if batch
    if (!($isBatch = is_array($requests))) {
      $requests = [$requests];
    }

    // process requests one by one
    $responses = [];
    foreach ($requests as $request) {
      $id = self::extractId($request);
      try {
        // apply postDecodeRequest hook and validate
        if (!Tools::validateRequest($newRequest = self::runHook($extensions, 'postDecodeRequest', $request))) {
          throw Exception::invalidRequest();
        } else {
          $id = self::extractId($newRequest);
        }

        // execute and build response
        $params   = property_exists($newRequest, 'params') ? (array) $newRequest->params : [];
        if (null !== ($response = self::buildResultResponse($this->execute($extensions, $newRequest->method, $params), $id))) {
          // enqueue response
          $responses[] = $response;
        }
      } catch (Exception $e) {
        $responses[] = self::buildErrorResponse($e, $id);
        continue;
      } catch (\Exception $e) {
        $responses[] = self::buildErrorResponse(Exception::serverError(-32000, 'Internal error - unexpected exception'), $id);
        continue;
      }
    }

    $jsonResponses = [];
    foreach (array_filter($responses) as $response) {
      $id = self::extractId($response);

      // apply preEncodeResponse hook and encode
      if (null === ($jsonResponse = Tools::jsonEncode(self::runHook($extensions, 'preEncodeResponse', $response), false))) {
        $jsonResponse = Tools::jsonEncode(self::buildErrorResponse(Exception::serverError(-32001, 'Internal error - encoding failed'), $id), false);
      }
      $jsonResponses[] = $jsonResponse;
    }

    // free extensions
    unset($extensions);

    // filter away orphans and return
    if ([] !== ($jsonResponses = array_filter($jsonResponses))) {
      return ($isBatch || 1 !== count($jsonResponses)) ? '[' . implode(',', $jsonResponses) . ']' : $jsonResponses[0];
    } else {
      return null;
    }
  }

  /**
   * Extract an id from the given reference object, or return false if no id property found, or null if no such extraction possible
   *
   * @param \stdClass $ref  Reference object from which to extract an id
   * @return mixed|null|false
   */
  protected static function extractId(\stdClass $ref) {
    if (!property_exists($ref, 'id')) {
      return false;
    } else {
      return (!is_string($ref->id) && !is_integer($ref->id) && !is_float($ref->id) && null !== $ref->id) ? null : $ref->id;
    }
  }

  /**
   * Build a succesful Json RPC response from the given result with a sensible id, or null if no id found
   *
   * @param mixed $result  Result to return
   * @param mixed $id  Id to use, or false for notifications
   * @return \stdClass|null
   */
  protected static function buildResultResponse($result, $id) {
    if (false === $id) {
      return null;
    }

    $return = new \stdClass();

    $return->jsonrpc = '2.0';
    $return->id      = $id;
    $return->result  = $result;

    return $return;
  }

  /**
   * Build an error Json RPC response from the given Exception with a sensible id, or null if no id found
   *
   * @param Exception $exception  Exception to return
   * @param mixed $id  Id to use, or false for notifications
   * @return \stdClass
   */
  protected static function buildErrorResponse(Exception $exception, $id = null) {
    if (false === $id) {
      return null;
    }

    $return = new \stdClass();

    $return->jsonrpc = '2.0';
    $return->id      = $id;
    $return->error   = $exception->asObject();

    return $return;
  }

  /**
   * Try to execute the given method with the given arguments
   *
   * @param array $extensions  Extensions registered
   * @param string $method  Method to execute
   * @param array $args  Arguments to pass
   * @return mixed
   * @throws Exception
   */
  protected function execute(array $extensions, $method, array $args = []) {
    // deal with rpc.x commands
    if ('rpc.x.' === strtolower(substr($method, 0, 6))) {
      return $this->xcommand($extensions, substr($method, 6), $args);
    }
    // deal with rpc commands
    if ('rpc.' === strtolower(substr($method, 0, 4))) {
      return $this->command(substr($method, 4), $args);
    }
    // Err out if nonexistent service
    if (!array_key_exists($method, $this->services)) {
      throw Exception::methodNotFound(['method' => $method]);
    }

    // initialize parameters
    $specs = $this->services[$method]['params']['specs'];
    $var   = array_key_exists('var', $this->services[$method]['params']) ? $this->services[$method]['params']['var'] : null;

    // initialize with defaults
    $params = [];
    $done   = [];
    foreach ($specs as $spec) {
      if (array_key_exists('default', $spec)) {
        $params[$spec['pos']] = $spec['default'];
        $done[$spec['name']]  = true;
      } else {
        $done[$spec['name']] = false;
      }
    }

    // load parameters
    $variadicTail = [];
    foreach ($args as $ref => $arg) {
      // convert numeric string to number
      if (ctype_digit($ref)) {
        $ref = (int) $ref;
      }
      // check for parameter existence / variadic nature
      if (!array_key_exists($ref, $specs)) {
        if (null === $var) {
          // if unknown parameter name or position, err out
          throw Exception::invalidParams();
        } else {
          $variadicTail[]     = $arg;
          $done[$var['name']] = true;
          continue;
        }
      } else if ($ref === $var['name']) {
        $variadicTail       = (array) $arg;
        $done[$var['name']] = true;
        continue;
      } else if ($ref === $var['pos']) {
        $variadicTail[]     = $arg;
        $done[$var['name']] = true;
        continue;
      }

      $params[$specs[$ref]['pos']] = $arg;
      $done[$specs[$ref]['name']]  = true;
    }

    // verify needed parameters
    if ([true] != array_unique(array_values($done))) {
      // this means that there are missing parameters in the method call
      throw Exception::invalidParams();
    }

    // add variadic tail
    if (null !== $var) {
      $i = $var['pos'];
      foreach ($variadicTail as $arg) {
        $params[$i++] = $arg;
      }
    }

    // if we got here, we have all we need to call the service in question,
    // wrap everything in a try block in order to restore the error handler
    // in every case
    try {
      // NB: we need to set an error handler up before calling the method proper
      //     in order to catch errors as exceptions
      set_error_handler(function ($errno, $errstr, $errfile, $errline, array $errcontext) {
        // just keep NetBeans happy
        false && $errfile && $errline && $errcontext;

        switch ($errno) {
          case E_USER_DEPRECATED:
          case E_USER_ERROR:
          case E_USER_NOTICE:
          case E_USER_WARNING:
            // throw a Json RPC-X Application Error Exception
            throw Exception::applicationError($errno, $errstr);
          case E_ERROR:
          case E_PARSE:
          case E_CORE_ERROR:
          case E_COMPILE_ERROR:
          case E_RECOVERABLE_ERROR:
            // throw a Json RPC-X Server Error Exception with constant code
            throw Exception::serverError(-32001, $errstr);
          default:
            // do NOT execute PHP's internal error handler regardless
            return true;
        }
      }, E_ALL);

      // execute away!
      $result = call_user_func_array($this->services[$method]['callable'], $params);
    } catch (Exception $e) {
      // Json RPC-X exceptions are rethrown
      throw $e;
    } catch (\Exception $e) {
      // PHP exceptions are collapsed to a Json RPC-X Server Error
      // just keep NetBeans happy
      false && $e;

      // throw a Json RPC-X Server Error Exception: Internal error - unexpected exception
      throw Exception::serverError(-32000, 'Internal error - unexpected exception');
    } finally {
      // always restore the error handler
      restore_error_handler();
    }

    // return the service's result
    return $result;
  }

  /**
   * Execute the given RPC command on the given arguments
   *
   * @param string $command  Command to execute
   * @param mixed $params  Parameters given
   * @return mixed
   */
  protected function command($command, array $params = []) {
    switch (strtolower($command)) {
      case 'echo':
        return $this->eco($params);
      case 'null':
        return $this->nil($params);
      case 'extensions':
        return $this->extensions($params);
      case 'services':
        return $this->serviceMetadata($params);
      default:
        throw Exception::methodNotFound(['method' => "rpc.{$command}"]);
    }
  }

  /**
   * Execute the given RPC-X command on the given arguments
   *
   * @param array $extensions  Extensions registered
   * @param string $command  Command to execute
   * @param mixed $params  Parameters given
   * @return mixed
   * @throws Exception
   */
  protected function xcommand(array $extensions, $command, array $params = []) {
    list($extension, $method) = array_filter(explode('.', $command, 2)) + [null, null];
    if (null === $extension || null === $method) {
      throw Exception::methodNotFound(['method' => "rpc.x.{$command}"]);
    }
    if (!array_key_exists($extension, $extensions)) {
      throw Exception::methodNotFound(['method' => "rpc.x.{$command}"]);
    }
    return $extensions[$extension]->command($method, $params);
  }

  /**
   * Echo back the parameters given
   *
   * @param array $params  Parameters given
   * @return mixed
   */
  protected function eco(array $params) {
    return $params;
  }

  /**
   * Always return null
   *
   * @param array $params  Parameters given
   * @return mixed
   */
  protected function nil(array $params) {
    // just keep NetBeans happy
    false && $params;

    return null;
  }

  /**
   * Return the registered extensions' names
   *
   * @param array $params  Parameters given
   * @return mixed
   */
  protected function extensions(array $params) {
    // just keep NetBeans happy
    false && $params;

    return $this->getExtensionNames();
  }

  /**
   * Return the attached services' metadata
   *
   * If no arguments are given, return all of the attached services'
   * metadata, otherwise, just return the ones asked for.
   *
   * @param array $params  Parameters given
   * @return mixed
   */
  protected function serviceMetadata(array $params) {
    if ([] === $params) {
      $params = ['**'];
    }
    if ([true] !== array_unique(array_map('Json\RpcX\Tools::validateSimpleGlob', $params))) {
      throw Exception::invalidParams();
    }

    $return = [];
    foreach ($params as $glob) {
      foreach ($this->services as $name => $service) {
        if (!array_key_exists($name, $return) && Tools::simpleGlob($glob, $name)) {
          $entry = [];
          if (array_key_exists('summary', $service)) {
            $entry['summary'] = $service['summary'];
          }
          if (array_key_exists('description', $service)) {
            $entry['description'] = $service['description'];
          }
          if (array_key_exists('return', $service)) {
            $entry['return'] = $service['return'];
          }
          foreach ($service['params']['specs'] as $i => $param) {
            if (!is_integer($i)) {
              continue;
            }
            $entry['params'][$param['name']] = ['pos' => $param['pos']];
            if (array_key_exists('default', $param)) {
              $entry['params'][$param['name']]['default'] = $param['default'];
            }
            if (array_key_exists('type', $param)) {
              $entry['params'][$param['name']]['type'] = $param['type'];
            }
            if (array_key_exists('description', $param) && $param['description']) {
              $entry['params'][$param['name']]['description'] = $param['description'];
            }
          }
          $return[$name] = $entry;
        }
      }
    }
    return $return;
  }

}
