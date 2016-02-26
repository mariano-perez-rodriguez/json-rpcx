<?php

/**
 * ServerBackend.php  Json RPC-X AUTH extension - Extended Lamport authentication server-side backend
 *
 * Class implementing an extended Lamport authentication scheme (server-side), usable as an AUTH backend.
 *
 */

namespace Json\RpcX\Extension\Auth\Backend\Lamport;

/**
 * Needed for:
 *  - Tools::validateSimpleGlob()
 *
 */
use \Json\RpcX\Tools as RpcXTools;
/**
 * Needed for:
 *  - Tools::bstrlen(),
 *  - Tools::getReqMin(),
 *  - Tools::pack()
 *
 */
use \Json\RpcX\Extension\Auth\Backend\Lamport\Tools as LamportTools;
/**
 * Needed for:
 *  - Exception::methodNotFound()
 *
 */
use \Json\RpcX\Exception;

/**
 * Class implementing an extended Lamport authentication scheme (server-side), usable as an AUTH backend.
 *
 */
class ServerBackend {

  /**
   * Persistence provider backend to use
   *
   * This callable must accept the following parameters:
   *   - string $action: the action being asked, this value further determines
   *         the following parameters; possible values are:
   *     - 'enable': enable registration of the given token, parameters are:
   *       - string $token: token to enable registration for,
   *         this function should return true on success, false on errors,
   *     - 'register': register a new token in the server, parameters are:
   *       - string $token: token to register (hexadecimal string),
   *       - string $salt: salt to use (hexadecimal string),
   *       - string $hash: hash to set (hexadecimal string),
   *       - int $idx: token's index to use (integer),
   *         this action should return true on success, false on errors,
   *     - 'get': try to get the data associated with a given token,
   *           parameters are:
   *       - string $token: token to fetch data for (hexadecimal string),
   *         this action should either return an array with fields:
   *           - 'token': the token being asked for (hexadecimal string),
   *           - 'salt': this token's current salt (hexadecimal string),
   *           - 'hash': this token's current hash (hexadecimal string),
   *           - 'idx': this token's current index (integer),
   *         or false on errors,
   *     - 'set': try to set the data given, parameters are:
   *       - string $token: token to set data for (hexadecimal string),
   *       - string $salt: token's new salt, or null to keep the current one
   *             (hexadecimal string),
   *       - string $hash: token's new hash (hexadecimal string),
   *       - int $idx: token's new index (integer),
   *         this action should return true on sccess, and false on errors.
   *
   * @var callable
   */
  protected $backend;

  /**
   * Hashing algorithm to use
   *
   * This MUST be a member of the array returned by "hash_algos()".
   *
   * @var string
   */
  protected $algorithm;

  /**
   * Strength map to use
   *
   * This array maps service method names to the number of hashes required in
   * order to be granted access and the number of remaining indexes there must
   * exist at least.
   *
   * The method names are specified using a simpleGlob expression.
   *
   * By default, all services require one hash to be presented, and tolerate
   * up to 100 remaining indexes, except for the "rpc.x.auth.lamport.status"
   * (which requires no token and tolerates up to 0 indexes left), the
   * "rpc.x.auth.lamport.renew" (which requires 10 tokens and tolerates up to
   * 10 indexes left), and the "rpc.x.auth.lamport.metadata" (which requires
   * no token be even specified and has no tolerance restrictions) ones, which
   * are needed to interact and recover authentification.
   *
   * If a method name matches more than one pattern, the highest requirement
   * among cyclone nodes induced by the ordering of the patterns in simpleGlob
   * order is used (note the plural form: a single method name may match
   * multiple patterns, and they may well be incomparable between them).
   *
   * This array must map simpleGlob patters to arrays of the form:
   *   - 'req': number of tokens required,
   *   - 'min': minimum amount of indexes remaining to tolerate.
   *
   * @var array
   */
  protected $strengthMap = [
      '**'                          => ['req' => 1, 'min' => 100],
      //
      'rpc.x.auth.lamport.status'   => ['req' => 0, 'min' => 0],
      //
      'rpc.x.auth.lamport.renew'    => ['req' => 10, 'min' => 10],
      //
      'rpc.x.auth.lamport.register' => ['req' => -1, 'min' => 0],
      'rpc.x.auth.lamport.metadata' => ['req' => -1, 'min' => 0],
  ];

  /**
   * Number of tokens that the client must generate during a renewal
   *
   * @var int
   */
  protected $minBatchSize = 100000;

  /**
   * Salt length in bits (MUST be a multiple of 8)
   *
   * @var int
   */
  protected $saltLength = 128;

  /**
   * Construct a server-side LamportBackend with the given parameters
   *
   * @param callable $backend  Persistence provider backend to use
   * @param string $algorithm  Hashing algorithm to use
   * @param array $strengthMap  Strength map to use
   * @param int $minBatchSize  Minimum renewal bacth size
   * @param int $saltLength  Salt length in bits (MUST be a multiple of 8)
   * @throws \Exception
   */
  public function __construct(callable $backend, $algorithm, array $strengthMap = null, $minBatchSize = 100000, $saltLength = 128) {
    // NB: this contraption (ie. "call_user_func('is_callable', $callable)" is
    //     used instead of "is_callable($callable)" because by using it, we're
    //     leaving the class scope and can decide whether the callable is
    //     indeed callable from outside this class (and prevents malicious
    //     input like "self::privateMethod" from being accepted).
    if (!call_user_func('is_callable', $backend)) {
      throw new \Exception(__CLASS__ . ': invalid backend');
    }

    if (!in_array(strtolower($algorithm), array_map('strtolower', hash_algos()))) {
      throw new \Exception(__CLASS__ . ": unknown hashing algorithm '{$algorithm}' given");
    }

    if (null === $strengthMap) {
      $strengthMap = $this->strengthMap;
    }
    $i = 0;
    foreach ($strengthMap as $pattern => $spec) {
      $i++;
      if (!RpcXTools::validateSimpleGlob($pattern)) {
        throw new \Exception(__CLASS__ . ": invalid pattern '{$pattern}' given at entry {$i}");
      }
      if (!array_key_exists('req', $spec)) {
        throw new \Exception(__CLASS__ . ": missing 'req' field at entry {$i}");
      }
      if (!array_key_exists('min', $spec)) {
        throw new \Exception(__CLASS__ . ": missing 'min' field at entry {$i}");
      }
      if (!is_integer($spec['req'])) {
        throw new \Exception(__CLASS__ . ": field 'req' is not an integer at entry {$i}");
      }
      if (!is_integer($spec['min'])) {
        throw new \Exception(__CLASS__ . ": field 'min' is not an integer at entry {$i}");
      }
    }

    if ($minBatchSize < 0) {
      throw new \Exception(__CLASS__ . ': insufficient batch size');
    } else if (2 ** 32 - 1 < $minBatchSize) {
      throw new \Exception(__CLASS__ . ': batch size too high');
    }

    if ($saltLength < 0) {
      throw new \Exception(__CLASS__ . ': insufficient salt length');
    } else if (0 !== $saltLength % 8) {
      throw new \Exception(__CLASS__ . ': misaligned salt length');
    }

    $this->backend      = $backend;
    $this->algorithm    = strtolower($algorithm);
    $this->strengthMap  = $strengthMap;
    $this->minBatchSize = (int) $minBatchSize;
    $this->saltLength   = (int) $saltLength;
  }

  /**
   * Invoke an action on this backend
   *
   * NOTE: the "generate" action will raise an exception, since this is a
   *       server-side-only backend.
   *
   * @param string $action  Action to act upon
   * @param mixed ...$args  arguments for the action in question
   * @return mixed
   */
  public function __invoke($action, ...$args) {
    switch (strtolower($action)) {
      case 'generate':
        throw new \Exception(__CLASS__ . ': unsupported action');
      case 'verify':
        return $this->verify(...$args);
      case 'command':
        return $this->command(...$args);
    }
    throw new \Exception(__CLASS__ . ": unknown action '{$action}'");
  }

  /**
   * Verify a given authentication string for validity
   *
   * An authentication string is a string of the form:
   *
   *     "token":"hash1":"hash2":... :"hashN"
   *
   * where "token" is the identification token being used, and "hash1" to
   * "hashN" are the required hashes ("hash1" is the one with the highest
   * index).
   *
   * @param string $auth  Authentication string
   * @param string $method  Method being called
   * @param array $params  Parameters being used (ignored)
   * @return string|boolean
   */
  protected function verify($auth, $method, array $params = null) {
    // just keep NetBeans happy
    false && $params;

    // extract the required and minimum remaining tokens
    list($req, $min) = LamportTools::getReqMin($this->strengthMap, $method);

    // if at least a token is required...
    if (-1 !== $req) {
      // extract token and hashes
      list($token, $hashes) = explode(':', $auth, 2) + [null, null];
      if (null === $token) {
        return 'No token given';
      }
      if (null !== $hashes) {
        $hashes = explode(':', $hashes);
      } else {
        $hashes = [];
      }

      // ask the backend for data for this token, apply basic validations
      if (false === ($data = LamportTools::getData($this->backend, $token))) {
        return false;
      }

      // check for structural conditions
      if ($data['idx'] < $min) {
        return 'Insufficient indexes left';
      }
      if (count($hashes) !== $req) {
        return false;
      }

      // verify hash chain
      $current = $data['hash'];
      $idx     = $data['idx'];
      foreach ($hashes as $hash) {
        $idx--;
        if ($current !== hash($this->algorithm, LamportTools::pack($token, $idx, $data['salt'], $hash))) {
          return false;
        }
        $current = $hash;
      }

      // set the new values
      if (false === call_user_func($this->backend, 'set', $token, $data['salt'], $current, $idx)) {
        return 'Backend failed';
      }
    }

    // everything went well
    return true;
  }

  /**
   * Execute an rpc.x.auth command
   *
   * @param string $method  Method to execute
   * @param array $params  Parameters to pass
   * @return mixed
   * @throws Exception
   */
  protected function command($method, array $params = []) {
    switch (strtolower($method)) {
      case 'lamport.status':
        if (1 !== count($params)) {
          throw Exception::invalidParams();
        }
        return $this->status(array_values($params)[0]);
      case 'lamport.renew':
        if (4 !== count($params)) {
          throw Exception::invalidParams();
        }
        $params = array_values($params);
        return $this->renew(...$params);
      case 'lamport.register':
        if (4 !== count($params)) {
          throw Exception::invalidParams();
        }
        $params = array_values($params);
        return $this->register(...$params);
      case 'lamport.metadata':
        return $this->metadata($params);
      default:
        throw Exception::methodNotFound(['method' => "rpc.x.auth.{$method}"]);
    }
  }

  /**
   * Return the stored data for the given token
   *
   * @param string $token  Token to get data for (hexadecimal string)
   * @return array
   * @throws Exception
   */
  protected function status($token) {
    // ask the backend for data for this token, apply basic validations
    if (false === ($data = LamportTools::getData($this->backend, $token))) {
      throw Exception::internalError();
    }

    return $data;
  }

  /**
   * Renew the authentication data stored in the persistence backend
   *
   * @param string $token  Token to use (hexadecimal string)
   * @param string $hash  New hash to set up (hexadecimal string)
   * @param string $salt  New salt to set up (hexadecimal string)
   * @param int $idx  Number of indexes to generate
   * @return boolean
   */
  protected function renew($token, $hash, $salt, $idx) {
    LamportTools::validateToken($token);
    LamportTools::validateHash($hash, $this->algorithm);
    LamportTools::validateSalt($salt, $this->saltLength);
    LamportTools::validateIdx($idx, $this->minBatchSize);

    // set the new values
    if (false === call_user_func($this->backend, 'set', $token, $salt, $hash, $idx)) {
      throw Exception::internalError();
    }

    return true;
  }

  /**
   * Enable registration for the given token
   *
   * THIS IS A SERVICE METHOD FOR THE END USER.
   *
   * @param string $token  Token to enable registration for (hexadecimal string)
   * @return boolean
   */
  public function enable($token) {
    // validate token
    if (!ctype_xdigit($token)) {
      throw Exception::invalidParams('non-hexadecimal token');
    }

    return call_user_func($this->backend, 'enable', $token);
  }

  /**
   * Add the authentication data given to the persistence backend
   *
   * @param string $token  Token to use (hexadecimal string)
   * @param string $hash  New hash to set up (hexadecimal string)
   * @param string $salt  New salt to set up (hexadecimal string)
   * @param int $idx  Number of indexes to generate
   * @return boolean
   */
  protected function register($token, $hash, $salt, $idx) {
    LamportTools::validateToken($token);
    LamportTools::validateHash($hash, $this->algorithm);
    LamportTools::validateSalt($salt, $this->saltLength);
    LamportTools::validateIdx($idx, $this->minBatchSize);

    // set the new values
    if (false === call_user_func($this->backend, 'register', $token, $salt, $hash, $idx)) {
      throw Exception::internalError();
    }

    return true;
  }

  /**
   * Return metadata from this backend, including strength mapping for the given method globs
   *
   * If an empty array is given, the strength map returned will be the whole
   * strength map itself, otherwise, it will be an array mapping globs given,
   * to an array with fields 'req' and 'min', with the required tokens, and
   * remaining indexes respectively.
   *
   * The returned array will have fields:
   *  - 'algorithm': the hashing algorithm used,
   *  - 'saltLength': the length in bits of the salt used,
   *  - 'minBatchSize': the number of hashes to generate AT THE LEAST when
   *        renewing,
   *  - 'strengthMap': the strength map to use, either for all the methods or
   *        for the ones given.
   *
   * @param array $methods  Array of method globs to look for
   * @return array
   */
  protected function metadata($methods) {
    if ([] === $methods) {
      $strengthMap = $this->strengthMap;
    } else {
      $strengthMap = [];
      foreach ($methods as $method) {
        list($req, $min) = LamportTools::getReqMin($this->strengthMap, $method);
        $strengthMap[$method] = ['req' => $req, 'min' => $min];
      }
    }

    return [
        'algorithm'    => $this->algorithm,
        'saltLength'   => $this->saltLength,
        'minBatchSize' => $this->minBatchSize,
        'strengthMap'  => $strengthMap,
    ];
  }

}
