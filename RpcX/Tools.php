<?php

/**
 * Tools.php  Json RPC-X Tools
 *
 * Json RPC-X static tools class.
 *
 */

namespace Json\RpcX;

/**
 * Json RPC-X static tools class
 *
 */
final class Tools {

  /**
   * Private constructor prevbents instantiation
   *
   * @throws \Exception
   */
  private function __construct() {
    throw new \Exception(__CLASS__ . ': cannot instantiate');
  }

  /////////////////////////////////////////////////////////////////////////////

  /**
   * Decode the given JSON string safely, taking static options into account
   *
   * @param string $json  JSON string to decode
   * @param boolean $throw  Whether to throw an exception on error or not (if not, return null)
   * @param int $maxDepth  Maximum JSON decode depth
   * @param int $jsonOptions  JSON decoding options
   * @return null|mixed
   * @throws \Exception
   */
  public static function jsonDecode($json, $throw = true, $maxDepth = 1024, $jsonOptions = null) {
    if (null === $jsonOptions) {
      $jsonOptions = 0;
      if (defined('JSON_BIGINT_AS_STRING')) {
        $jsonOptions |= JSON_BIGINT_AS_STRING;
      }
    }
    // ensure UTF-8 encoding
    if (!preg_match('~~u', $json)) {
      $json = utf8_encode($json);
    }
    // do plain JSON decoding
    $return = json_decode($json, false, $maxDepth, $jsonOptions);

    // check for errors
    if (JSON_ERROR_NONE !== ($jsonLastError = json_last_error())) {
      if ($throw) {
        throw new \Exception(json_last_error_msg(), $jsonLastError);
      } else {
        return null;
      }
    } else {
      return $return;
    }
  }

  /**
   * Return a JSON string built from the given value, taking static options into account
   *
   * @param string $val  Value to encode as JSON
   * @param boolean $throw  Whether to throw an exception on error or not (if not, return null)
   * @param int $maxDepth  Maximum JSON encode depth
   * @param int $jsonOptions  JSON encoding options
   * @return string|null
   * @throws \Exception
   */
  public static function jsonEncode($val, $throw = true, $maxDepth = 1024, $jsonOptions = null) {
    if (null === $jsonOptions) {
      $jsonOptions = 0;
      if (defined('JSON_BIGINT_AS_STRING')) {
        $jsonOptions |= JSON_BIGINT_AS_STRING;
      }
      if (defined('JSON_UNESCAPED_SLASHES')) {
        $jsonOptions |= JSON_UNESCAPED_SLASHES;
      }
      if (defined('JSON_UNESCAPED_UNICODE')) {
        $jsonOptions |= JSON_UNESCAPED_UNICODE;
      }
      if (defined('JSON_PRESERVE_ZERO_FRACTION')) {
        $jsonOptions |= JSON_PRESERVE_ZERO_FRACTION;
      }
    }
    // do plain JSON decoding
    $return = json_encode($val, $jsonOptions, $maxDepth);

    // check for errors
    if (JSON_ERROR_NONE !== ($jsonLastError = json_last_error())) {
      if ($throw) {
        throw new \Exception(json_last_error_msg(), $jsonLastError);
      } else {
        return null;
      }
    } else {
      // ensure UTF-8 encoding
      if (!preg_match('~~u', $return)) {
        $return = utf8_encode($return);
      }

      return $return;
    }
  }

  /**
   * Validate the given request object against Json RPC 2.0 rules
   *
   * @param \stdClass $request  Request object to validate
   * @return boolean
   */
  public static function validateRequest(\stdClass $request) {
    if (!property_exists($request, 'jsonrpc')) {
      return false; // missing 'jsonrpc' member
    } else if ('2.0' !== $request->jsonrpc) {
      return false; // unrecognized Json RPC version
    }

    if (property_exists($request, 'id')) {
      if (!is_string($request->id) && !is_integer($request->id) && !is_float($request->id) && null !== $request->id) {
        return false; // unsupported 'id' value
      }
    }

    if (!property_exists($request, 'method')) {
      return false; // missing 'method' member
    } else if (!is_string($request->method)) {
      return false; // unsupported 'method' value
    } else if (!self::validateName($request->method)) {
      return false; // invalid name
    }

    if (property_exists($request, 'params')) {
      if (!is_array($request->params) && !is_object($request->params)) {
        return false; // unsupported 'params' value
      }
      foreach (array_keys((array) $request->params) as $param) {
        if (!ctype_digit((string) $param) && !self::validateName($param)) {
          return false; // invalid parameters name
        }
      }
    }

    if ([] !== array_diff(array_keys(get_object_vars($request)), ['jsonrpc', 'id', 'method', 'params'])) {
      return false; // unknown members found
    }

    return true;
  }

  /**
   * Validate the given response object against Json RPC 2.0 rules
   *
   * @param \stdClass $response  Response object to validate
   * @return boolean
   */
  public static function validateResponse(\stdClass $response) {
    if (!property_exists($response, 'jsonrpc')) {
      return false; // missing 'jsonrpc' member
    } else if ('2.0' !== $response->jsonrpc) {
      return false; // unrecognized Json RPC-X version
    }

    if (!property_exists($response, 'id')) {
      return false; // missing 'id' member
    } else if (!is_string($response->id) && !is_integer($response->id) && !is_float($response->id) && null !== $response->id) {
      return false; // unsupported 'id' value
    }

    if (!property_exists($response, 'result') && !property_exists($response, 'error')) {
      return false; // missing 'result' and 'error' members
    }
    if (property_exists($response, 'result') && property_exists($response, 'error')) {
      return false; // both 'result' and 'error' members found
    }

    if ([] !== array_diff(array_keys(get_object_vars($response)), ['jsonrpc', 'id', 'result', 'error'])) {
      return false; // unknown members found
    }

    if (property_exists($response, 'error')) {
      if (!is_object($response->error)) {
        return false; // unsupported 'error' value
      }

      if (!property_exists($response->error, 'code')) {
        return false; // missing 'error.code' member
      } else if (!is_integer($response->error->code)) {
        return false; // unsupported 'error.code' value
      }

      if (!property_exists($response->error, 'message')) {
        return false; // missing 'error.message' member
      } else if (!is_string($response->error->message)) {
        return false; // unsupported 'error.message' value
      }

      if ([] !== array_diff(array_keys(get_object_vars($response->error)), ['code', 'message', 'data'])) {
        return false; // unknown error members found
      }
    }

    return true;
  }

  /**
   * Return the number of microseconds since the epoch
   *
   * @return int
   */
  public static function microtime() {
    $m = microtime();
    return (int) (substr($m, 11) . substr($m, 2, 6));
  }

  /**
   * Expand a string in basic brace pattern syntax to a list of possible strings
   *
   * This function will expand "basic brace patterns" (BBPs) into a list of
   * possible strings.
   *
   * BBPs are generated as follows:
   *   a. every character other than "{", "|", or "}" is a BBP,
   *   b. if A and B are BBPs, then A B is a BBP,
   *   c. if A1, A2, ..., An are BBPs, then { A1 | A2 | ... | An } is a BBP,
   * and their interpretation is that of standing for any possible combination
   * of strings using alternation (represented by "|") and grouping
   * (represented by "{" / "}" pairs).
   *
   * @param string $s  Pattern string to expand
   * @return string
   */
  public static function expandBraces($s) {
    // match maximal brace patterns, replace each by a new slot indicator,
    // and look for suboptions indexed by slot number
    $i  = 0;
    $ss = [];
    $xs = [preg_replace_callback('~\{((?>[^{}]+)|(?R))*\}~', function ($m) use (&$i, &$ss) {
                  $ss[] = self::expandBraces(substr($m[0], 1, -1));
                  return '{' . $i++ . '}';
                }, $s)];
    // replace each slot by each possible option for that slot
    $nxs = [];
    foreach ($ss as $i => $opts) {
      foreach ($opts as $opt) {
        foreach ($xs as $x) {
          $nxs[] = str_replace("{{$i}}", $opt, $x);
        }
      }
      $xs  = $nxs;
      $nxs = [];
    }
    // apply possible alternations
    foreach ($xs as $x) {
      $nxs = array_merge($nxs, explode('|', $x));
    }
    // finally, remove duplicates and return
    return array_unique($nxs);
  }

  /**
   * Determine whether the given string is a valid simple glob pattern
   *
   * A simple glob pattern is a normal string, where the following sequences
   * are interpreted specially:
   *  - '*': stands for any one of a-z, A-Z, 0-9, or the underscore,
   *  - '**': as for '*', but also match the dot,
   * in particular, the '*' will match a prefix of, suffix of, or actual
   * namespace segment, while the '**' may match multiple segments (not
   * necesarily complete, prefixes and suffixes are game too).
   *
   * @param string $pattern  Simple glob pattern to validate
   * @return boolean
   */
  public static function validateSimpleGlob($pattern) {
    return false === strpos($pattern, '***');
  }

  /**
   * Determine whether the given string matches the given simple glob pattern
   *
   * A simple glob pattern is a normal string, where the following sequences
   * are interpreted specially:
   *  - '*': stands for any one of a-z, A-Z, 0-9, or the underscore,
   *  - '**': as for '*', but also match the dot,
   * in particular, the '*' will match a prefix of, suffix of, or actual
   * namespace segment, while the '**' may match multiple segments (not
   * necesarily complete, prefixes and suffixes are game too).
   *
   * @param string $pattern  Simple glob pattern to use
   * @param string $string  String to try to match
   * @return boolean
   */
  public static function simpleGlob($pattern, $string) {
    if (!self::validateSimpleGlob($pattern)) {
      return null;
    }
    $regex = strtr(preg_quote($pattern, '~'), [
        '\*\*' => '[a-zA-Z0-9_.]*',
        '\*'   => '[a-zA-Z0-9_]*',
    ]);
    return (bool) preg_match("~^{$regex}\$~", $string);
  }

  /**
   * Compare two simpleGlob expressions for generality
   *
   * This function will return:
   *   - 1: if the first pattern is more general than the second,
   *   - -1: if the first pattern is less general than the second,
   *   - 0: if both patterns are as general,
   *   - false: if the patterns are incomparable,
   *   - null: if any of the patterns are malformed,
   * where the comparison is done by checking whether one pattern would
   * match the other (provided the '*' was an allowed character to match).
   *
   * @param string $patternA  Left-hand pattern to compare
   * @param string $patternB  Right-hand pattern to compare
   * @return int|false|null
   */
  public static function compareSimpleGlob($patternA, $patternB) {
    if (!self::validateSimpleGlob($patternA) || !self::validateSimpleGlob($patternB)) {
      return null;
    }

    $replacements = [
        '\*\*' => '[a-zA-Z0-9_.*]*',
        '\*'   => '[a-zA-Z0-9_*]*',
    ];

    $matchAB = preg_match('~^' . strtr(preg_quote($patternA, '~'), $replacements) . '$~', $patternB);
    $matchBA = preg_match('~^' . strtr(preg_quote($patternB, '~'), $replacements) . '$~', $patternA);

    switch (true) {
      case ($matchAB && $matchBA): return 0;
      case ($matchAB && !$matchBA): return 1;
      case (!$matchAB && $matchBA): return -1;
      case (!$matchAB && !$matchBA): return false;
    }
  }

  /**
   * Parse a @method or @staticmethod tag
   *
   * This function will parse the contents of a @method or @staticmethod tag
   * and return an array with the method's name as first element, and the
   * method's metadata as second, or false if parsing failed.
   *
   * NB: unparseable arguments will be IGNORED
   * NB: this function does not currently support default values
   *
   * @param string $tag  @method or @staticmethod tag value
   * @return array|false
   */
  public static function parseMethodTag($tag) {
    /// @todo support default values for arguments
    $matches = [];
    if (!preg_match('~^(?P<return>[^\s]+(?=\s+[^\s]+\s*\())?\s*(?P<name>[^\s\(]+)\s*\((?P<params>[^\)]*)\)\s*(?P<summary>.*)?$~', $tag, $matches)) {
      return false;
    }
    // clean up parameters string and turn into a spec list
    $specs = array_filter(array_map(function ($param) {
              list($head, $rparam) = explode(' ', trim($param), 2);
              if (in_array(substr($head, 0, 1), ['$', '&'])) {
                return ['name' => trim($head, '$&')];
              } else {
                list($head2, $rparam) = explode(' ', trim($rparam), 2);
                if (!in_array(substr($head2, 0, 1), ['$', '&'])) {
                  return null;
                } else {
                  return ['name' => trim($head2, '$&'), 'type' => $head];
                }
              }
            }, explode(',', $matches['params'])));
    // load parameters
    $params = [];
    $i      = 0;
    foreach ($specs as $spec) {
      $spec['pos']           = $i++;
      $params[$spec['name']] = $params[$spec['pos']]  = $spec;
    }

    return [$matches['name'], [
            'summary'     => $matches['summary'] ? : null,
            'description' => null,
            'params'      => ['specs' => $params],
            'return'      => $matches['return'] ? : null,
    ]];
  }

  /**
   * Parse a function's, class', or property's docblock
   *
   * This function will parse a function's or method's bockblock and return
   * an array of the form:
   *   - summary: summary given to the function / method,
   *   - description: description block of the function / method,
   *   - tags: an array of phpDoc tags, of the form:
   *     - name: tag's name,
   *     - value: tag's value (no parsing is done for tags).
   *
   * A summary is considered finished when an empty line is found, or when
   * the line ends in a full stop.
   *
   * NOTE: the doc lines are trimmed of whitespace prior to being
   *       accumulated, so internal art may not be preserved.
   *
   * This method returns false if no docblock exists for the given element.
   *
   * @param \ReflectionClass|\ReflectionFunctionAbstract|\ReflectionProperty $ref  Reflected function / class / property to parse the docblock for
   * @return array|false
   */
  public static function extractDoc($ref) {
    // get docblock or fail
    if (false === ($doc = $ref->getDocComment())) {
      return false;
    }

    // split into lines, remove initial asterisk, and trim
    $adoc = array_map(function ($line) {
      return trim(preg_replace('~^\s*\*~', '', $line));
    }, preg_split('~$\R^~m', substr($doc, 3, -2)));

    // remove initially empty lines
    while ([] !== $adoc && '' === $adoc[0]) {
      array_shift($adoc);
    }

    // extract summary
    $summary = '';
    while ([] !== $adoc && '' !== $adoc[0] && '.' !== substr($adoc[0], -1, 1)) {
      $summary .= ' ' . array_shift($adoc);
    }
    if ([] !== $adoc && '' !== $adoc[0]) {
      $summary .= substr(array_shift($adoc), 0, -1);
    }

    // remove initially empty lines (again)
    while ([] !== $adoc && '' === $adoc[0]) {
      array_shift($adoc);
    }

    // extract description (everything up until the first initial tag)
    $description = '';
    while ([] !== $adoc && '@' !== substr($adoc[0], 0, 1)) {
      $description .= "\n" . array_shift($adoc);
    }

    // extract tags
    $tags = [];
    $i    = 0;
    while ([] !== $adoc) {
      $line = array_shift($adoc);
      if ('@' !== substr($line, 0, 1)) {
        $tags[$i]['value'] = trim($tags[$i]['value'] . ' ' . $line);
      } else {
        $i++;
        $parts    = explode(' ', $line);
        $name     = trim(substr(array_shift($parts), 1));
        $value    = trim(implode(' ', $parts));
        $tags[$i] = [
            'name'  => $name,
            'value' => $value,
        ];
      }
    }

    // return
    return [
        'summary'     => trim($summary),
        'description' => trim($description),
        'tags'        => array_values($tags),
    ];
  }

  /**
   * Extract metadata from the given reflection object
   *
   * This method will return an array of the form:
   *  - 'summary': the object's summary (as stated in the reflection's phpDoc),
   *  - 'description': the object's description (as stated in the reflection's phpDoc),
   *  - 'return': the object's return specification (as stated in the reflection's phpDoc),
   *              an array of the form:
   *    - 'type': the return's type (as stated in the reflection's phpDoc),
   *    - 'description': the return's description (as stated in the reflection's phpDoc),
   *  - 'params': the object's parameters, an array of the form:
   *    - 'specs': parameter specifications, an array with elements of the form:
   *      - 'name': parameter's name,
   *      - 'pos': parameter's position,
   *      - 'default': the parameter's default value, if it has one,
   *      - 'type': the parameter's type (as stated in the reflection's phpDoc),
   *      - 'description': the parameter's description (as stated in the reflection's phpDoc),
   *      this array is indexed, either by position, or by name,
   *    - 'var': the method's variadic tail reference, an array of the form:
   *      - 'name': the name of the variadic tail,
   *      - 'pos': the position of the variadic tail,
   *
   * @param \ReflectionFunctionAbstract $ref  Reflection object to use
   * @return array
   */
  public static function extractCallableMetadata(\ReflectionFunctionAbstract $ref) {
    // extract parameter specification
    $params = ['specs' => []];
    foreach ($ref->getParameters() as $param) {
      $name = $param->getName(); // for named arguments
      $pos  = $param->getPosition(); // for positional arguments
      // build spec
      $spec = ['name' => $name, 'pos' => $pos];
      // default values are checked first because a parameter may be nullable
      // yet still have a default, non-null, value
      if ($param->isDefaultValueAvailable()) {
        $spec['default'] = $param->getDefaultValue();
      } else if ($param->allowsNull()) {
        $spec['default'] = null;
      }

      // store spec
      $params['specs'][$name] = $params['specs'][$pos]  = $spec;

      // deal with the variadic tail
      if ($param->isVariadic()) {
        $params['var'] = ['name' => $name, 'pos' => $pos];
      }
    }

    // extract phpDoc data
    $return      = 'void';
    $summary     = null;
    $description = null;
    if (false !== ($doc         = self::extractDoc($ref))) {
      $summary     = $doc['summary'] ? : null;
      $description = $doc['description'] ? : null;
      foreach ($doc['tags'] as $tag) {
        if ('param' === $tag['name']) {
          $matches = [];
          if (preg_match('~^(?P<type>[^\s]+)\s+(?:\$(?P<name>[^\s]+))(?:\s+(?P<description>.*))?$~', $tag['value'], $matches)) {
            if (array_key_exists($matches['name'], $params['specs']) && 'type' !== $matches['type']) {
              $name                                  = $matches['name'];
              $pos                                   = $params['specs'][$name]['pos'];
              $params['specs'][$name]['type']        = $params['specs'][$pos]['type']         = $matches['type'];
              $params['specs'][$name]['description'] = $params['specs'][$pos]['description']  = array_key_exists('description', $matches) ? $matches['description'] : null;
            }
          }
        } else if ('return' === $tag['name']) {
          $matches = [];
          if (preg_match('~^(?P<type>[^\s]+)(?:\s+(?P<description>.*))?$~', $tag['value'], $matches)) {
            $return = [
                'type'        => $matches['type'],
                'description' => array_key_exists('description', $matches) ? $matches['description'] : null,
            ];
          }
        }
      }
    }

    return [
        'summary'     => $summary,
        'description' => $description,
        'params'      => $params,
        'return'      => $return,
    ];
  }

  /**
   * Extract the given object as a service provider
   *
   * This method will extract each of the given object's public non-static
   * methods under the given service name (separated by a "."). It will return
   * all of the extracted method mappings.
   *
   *
   * @param string $name  Root name to use
   * @param object $object  Object to extract
   * @return array
   */
  public static function extractCallSpecFromObject($name, $object) {
    $ref = new \ReflectionObject($object);

    $methods = array_filter($ref->getMethods(), function ($method) {
      // kepp only public, non-static, non-magic methods
      return $method->isPublic() && !$method->isStatic() && '__' !== substr($method->name, 0, 2);
    });
    $return = [];
    foreach ($methods as $method) {
      // build service entry
      $entry             = self::extractCallableMetadata($method);
      $entry['callable'] = [$object, $method->name];

      // add to return value
      $return["{$name}.{$method->name}"] = $entry;
    }

    // look for "__call" magic methods
    if ($ref->hasMethod('__call')) {
      $method = $ref->getMethod('__call');
      if ($method->isPublic() && !$method->isStatic()) {
        foreach (self::extractDoc($ref)['tags'] as $tag) {
          if ('method' === $tag['name']) {
            if (false !== ($metadata = self::parseMethodTag($tag['value']))) {
              list($mname, $entry) = $metadata;
              foreach (self::expandBraces($mname) as $xname) {
                $entry['callable']          = [$object, $xname];
                $return["{$name}.{$xname}"] = $entry;
              }
            }
          }
        }
      }
    }

    // look for "__invoke" magic method
    if ($ref->hasMethod('__invoke')) {
      $method = $ref->getMethod('__invoke');
      if ($method->isPublic() && !$method->isStatic()) {
        // build service entry
        $entry             = self::extractCallableMetadata($method);
        $entry['callable'] = $object;

        // add to return value
        $return[$name] = $entry;
      }
    }

    return $return;
  }

  /**
   * Extract the given class as a service provider
   *
   * This method will extract each of the given class' public static
   * methods under the given service name (separated by a "."). It will return
   * all of the extracted method mappings.
   *
   *
   * @param string $name  Root name to use
   * @param string $class  Class name to extract
   * @return array
   */
  public static function extractCallSpecFromClass($name, $class) {
    $ref = new \ReflectionClass($class);

    $methods = array_filter($ref->getMethods(), function ($method) {
      // kepp only public, static, non-magic methods
      return $method->isPublic() && $method->isStatic() && '__' !== substr($method->name, 0, 2);
    });
    $return = [];
    foreach ($methods as $method) {
      // build service entry
      $entry             = self::extractCallableMetadata($method);
      $entry['callable'] = [$class, $method->name];

      // add to return value
      $return["{$name}.{$method->name}"] = $entry;
    }

    // look for "__callStatic" magic methods
    if ($ref->hasMethod('__callStatic')) {
      $method = $ref->getMethod('__callStatic');
      if ($method->isPublic() && $method->isStatic()) {
        foreach (self::extractDoc($ref)['tags'] as $tag) {
          // NB: @staticmethod is NOT part of the phpDoc standard, it is
          //     used here for lack of a better option
          if ('staticmethod' === $tag['name']) {
            if (false !== ($metadata = self::parseMethodTag($tag['value']))) {
              list($mname, $entry) = $metadata;
              foreach (self::expandBraces($mname) as $xname) {
                $entry['callable']          = [$class, $xname];
                $return["{$name}.{$xname}"] = $entry;
              }
            }
          }
        }
      }
    }

    return $return;
  }

  /**
   * Extract the given callable as a service provider
   *
   * This method will extract the given callable under the given service
   * name. It will return the extracted mapping (as a one-element array), or
   * false in case of errors.
   *
   *
   * @param string $name  Name to use
   * @param callable $callable  Callable to extract
   * @return array|false
   */
  public static function extractCallSpecFromCallable($name, callable $callable) {
    // NB: this contraption (ie. "call_user_func('is_callable', $callable)" is
    //     used instead of "is_callable($callable)" because by using it, we're
    //     leaving the class scope and can decide whether the callable is
    //     indeed callable from outside this class (and prevents malicious
    //     input like "self::privateMethod" from being accepted).
    if (!call_user_func('is_callable', $callable)) {
      return false;
    }
    $ref = null;
    try {
      switch (true) {
        case is_string($callable) && function_exists($callable):
        case is_object($callable) && 'closure' === strtolower(get_class($callable)):
          $ref = new \ReflectionFunction($callable);
          break;
        default:
          $ref = new \ReflectionMethod(...((array) $callable));
          break;
      }
    } catch (\Exception $e) {
      // just keep NetBeans happy
      false && $e;

      return false;
    }
    $entry             = self::extractCallableMetadata($ref);
    $entry['callable'] = $callable;
    // return service mapping
    return [$name => $entry];
  }

  /**
   * Check the given name against the Json RPC-X naming rules
   *
   * @param string $name  Name to check
   * @return bool
   */
  public static function validateName($name) {
    return (bool) preg_match('~^[A-Za-z][A-Za-z0-9_.]*$~', $name);
  }

}
