<?php

/**
 * ClientBackend.php  Json RPC-X AUTH extension - Extended Lamport authentication client-side backend
 *
 * Class implementing an extended Lamport authentication scheme (client-side), usable as an AUTH backend.
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
 *  - Client,
 *  - Client::dup()
 *
 */
use \Json\RpcX\Client;

/**
 * Class implementing an extended Lamport authentication scheme (client-side), usable as an AUTH backend.
 *
 */
class ClientBackend {

  /**
   * Persistence provider backend to use
   *
   * This callable must accept the following parameters:
   *   - string $action: the action being asked, this value further determines
   *         the following parameters; possible values are:
   *     - 'next': get the given number of unused hashes for the given token,
   *           parameters are:
   *       - string $token: token in question (hexadecimal string),
   *       - int $count: number of hashes to return,
   *       - int $remaining: the number of remaining hashes to leave behind at
   *             the least,
   *           this action should return an array of the next $count hashes,
   *           the one with the highest index first, as hexadecimal strings, or
   *           false, if not enough hashes remaining or not enough to leave
   *           behind,
   *     - 'prepare': prepare a hash list for syncronization for the given
   *           token, parameters are:
   *       - string $token: token in question (hexadecimal string),
   *       - string $salt: hashing salt to use as an hexadecimal string,
   *       - array $hashes: hash list to prepare,
   *           this action must record the given hash list as a "tentative" hash
   *           list, said hash list will NOT be used until the changes are made
   *           permanent by a further "sync" action,
   *     - 'synchronize': synchronize the internal (from the persistence
   *           provider's perspective) state with the server's for the given
   *           token, parameters are:
   *       - string $token: token in question (hexadecimal string),
   *       - string $salt: hashing salt to use (hexadecimal string),
   *       - int $idx: current hashing index,
   *           this action should observe the following conditions:
   *             1. if $salt is NOT among either the current hash list salt nor
   *                any prepared hash list's salt, this action MUST return false,
   *             2. if $salt is equal to the CURRENT hash list salt, it should
   *                simply update the index (removing used hashes, if applicable)
   *                and return true in case of succes, false otherwise,
   *             3. if $salt is NOT equal to the current hash list's salt, but
   *                it IS equal to a prepared hash list's salt, this action MUST
   *                REMOVE every hash list (either current or prepared) other
   *                than the one having the given salt, and return true if
   *                successful, false otherwise
   *           these conditions are in place to ensure a consistent state is
   *           shared between the client and the server.
   *
   * NOTE: ALL of these ctions MUST be atomic in order to ensure a consistent
   *       client/server state.
   *
   * @var callable
   */
  protected $backend;

  /**
   * Token to use client-side for generation (hexadecimal string)
   *
   * @var string
   */
  protected $token;

  /**
   * Hashing algorithm to use
   *
   * This MUST be a member of the array returned by "hash_algos()".
   *
   * If this member is not provided during construction, it is filled by the
   * static constructor by calling the rpc method "rpc.x.auth.metadata".
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
   * If this member is not provided during construction, it is filled by the
   * static constructor by calling the rpc method "rpc.x.auth.metadata".
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
  protected $strengthMap;

  /**
   * Number of tokens that the client must generate during a renewal
   *
   * If this member is not provided during construction, it is filled by the
   * static constructor by calling the rpc method "rpc.x.auth.metadata".
   *
   * @var int
   */
  protected $minBatchSize;

  /**
   * Salt length in bits (MUST be a multiple of 8)
   *
   * If this member is not provided during construction, it is filled by the
   * static constructor by calling the rpc method "rpc.x.auth.metadata".
   *
   * @var int
   */
  protected $saltLength;

  /**
   * Determine whether the given responbse is a parsed error
   *
   * @param mixed $response  Response to analyze
   * @return boolean
   */
  protected static function isError($response) {
    return is_a($response, '\\Exception');
  }

  /**
   * Create a client-side Lamport backend
   *
   * @param callable $backend  Provider backend to use
   * @param string $token  Token to use (hexadecimal string)
   * @param Client $client  Client to use for metadata retrieval (may be null if all metadata given)
   * @param string $algorithm  Hashing algorithm to use (may be null, in which case it will be fetched with the given client)
   * @param array $strengthMap  Strength map to use (may be null, in which case it will be fetched with the given client)
   * @param int $minBatchSize  Minimum batch renewal size (may be null, in which case it will be fetched with the given client)
   * @param int $saltLength  Salt length in bits (MUST be a multiple of 8) (may be null, in which case it will be fetched with the given client)
   * @return self
   * @throws \Exception
   */
  public function __construct(callable $backend, $token, Client $client = null, $algorithm = null, array $strengthMap = null, $minBatchSize = null, $saltLength = null) {
    // NB: this contraption (ie. "call_user_func('is_callable', $callable)" is
    //     used instead of "is_callable($callable)" because by using it, we're
    //     leaving the class scope and can decide whether the callable is
    //     indeed callable from outside this class (and prevents malicious
    //     input like "self::privateMethod" from being accepted).
    if (!call_user_func('is_callable', $backend)) {
      throw new \Exception(__CLASS__ . ': invalid backend');
    }

    if (!ctype_xdigit($token)) {
      throw new \Exception(__CLASS__ . ": invalid token '{$token}'");
    } else {
      $token = strtolower($token);
    }

    if (null === $client && (null === $algorithm || null === $strengthMap || null === $minBatchSize || null === $saltLength)) {
      throw new \Exception(__CLASS__ . ': unmet dependencies');
    }

    if (null !== $algorithm) {
      if (!in_array(strtolower($algorithm), array_map('strtolower', hash_algos()))) {
        throw new \Exception(__CLASS__ . ": unknown hashing algorithm '{$algorithm}' given");
      } else {
        $algorithm = strtolower($algorithm);
      }
    }

    if (null !== $strengthMap) {
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
    }

    if (null !== $minBatchSize) {
      if (0 < $minBatchSize) {
        throw new \Exception(__CLASS__ . ': insufficient batch size');
      } else if (2 ** 32 - 1 < $minBatchSize) {
        throw new \Exception(__CLASS__ . ': batch size too high');
      } else {
        $minBatchSize = (int) $minBatchSize;
      }
    }

    if (null !== $saltLength) {
      if (0 < $saltLength) {
        throw new \Exception(__CLAS__ . ': insufficient salt length');
      } else if (0 !== $saltLength % 8) {
        throw new \Exception(__CLAS__ . ': misaligned salt length');
      } else {
        $saltLength = (int) $saltLength;
      }
    }

    if (null === $algorithm || null === $strengthMap || null === $minBatchSize || null === $saltLength) {
      if (self::isError($metadata = Client::dup($client)->rpc->x->auth->lamport->metadata()->exec)) {
        throw new \Exception(__CLASS__ . ': unable to determine parameters for authentication');
      }
      $algorithm    = $metadata->algorithm;
      $strengthMap  = json_decode(json_encode($metadata->strengthMap), true);
      $minBatchSize = $metadata->minBatchSize;
      $saltLength   = $metadata->saltLength;
    }

    $this->backend      = $backend;
    $this->token        = $token;
    $this->algorithm    = $algorithm;
    $this->strengthMap  = $strengthMap;
    $this->minBatchSize = $minBatchSize;
    $this->saltLength   = $saltLength;
  }

  /**
   * Invoke an action on this backend
   *
   * NOTE: the "verify" and "command" actions will raise an exception, since
   *       this is a client-side-only backend.
   *
   * @param string $action  Action to act upon
   * @param mixed ...$args  arguments for the action in question
   * @return mixed
   */
  public function __invoke($action, ...$args) {
    switch (strtolower($action)) {
      case 'generate':
        return $this->generate(...$args);
      case 'verify':
        throw new \Exception(__CLASS__ . ': unsupported action');
      case 'command':
        throw new \Exception(__CLASS__ . ': unsupported action');
    }
    throw new \Exception(__CLASS__ . ": unknown action '{$action}'");
  }

  /**
   * Generate an authentication string
   *
   * @param string $method  Method being called
   * @param array $params  Parameters being used (ignored)
   * @return string
   * @throws \Exception
   */
  protected function generate($method, array $params = null) {
    // just keep NetBeans happy
    false && $params;

    // extract the required and minimum remaining tokens
    list($req, $min) = LamportTools::getReqMin($this->strengthMap, $method);

    // if no requirements, just return false
    if (-1 === $req) {
      return false;
    }
    // if only a token is to be provided, just return it
    if (0 === $req) {
      return $this->token;
    }

    // ask the backend for hashes
    if (false === ($hashes = call_user_func($this->backend, 'next', $this->token, $req, $min))) {
      throw new \Exception(__CLASS__ . ': backend failed to get hashes');
    }
    // enqueue token
    array_unshift($hashes, $this->token);

    // return auth object
    return implode(':', $hashes);
  }

  /**
   * Register a hashing list, using the given client for registration and synchronization, from the given seed and of the given size
   *
   * THIS IS A SERVICE METHOD FOR THE END USER.
   *
   * NOTE: the hashing seed will most likely be a cryptographycally-secure
   *       derivation of a user-supplied password; the hashing seed will be
   *       used as-is by the backend, so any key derivation that needs to be
   *       applied MUST be applied beforehand.
   *
   *
   * @param Client $client  Client to use
   * @param string $seed  Hashing seed (hexadecimal string)
   * @param int $batchSize  Number of hashes to generate (32 bits)
   * @return boolean
   */
  public function register(Client $client, $seed, $batchSize = null) {
    if (null === $batchSize) {
      $batchSize = $this->minBatchSize;
    }
    // validate
    LamportTools::validateIdx($batchSize, $this->minBatchSize);

    // build hash list
    list($salt, $hash, $hashes) = LamportTools::hashList($this->token, $seed, $this->algorithm, $batchSize, $this->saltLength);

    // prepare the backend
    if (false === call_user_func($this->backend, 'prepare', $this->token, $salt, $hashes)) {
      throw new \Exception(__CLASS__ . ': backend failed to prepare renewal');
    }

    // do renewal proper
    if (self::isError(Client::dup($client)->rpc->x->auth->lamport->register($this->token, $hash, $salt, $batchSize)->exec)) {
      throw new \Exception(__CLASS__ . ': failed to perform registration');
    }

    // synchronize
    $this->synchronize($client);
  }

  /**
   * Renew a hashing list, using the given client for renewal and synchronization, from the given seed and of the given size
   *
   * THIS IS A SERVICE METHOD FOR THE END USER.
   *
   * NOTE: Since renewal requires authentication, the given Client MUST be
   *       able to authenticate itself to the server, thus, a Client
   *       implementing this same backend is needed.
   *
   * NOTE: the hashing seed will most likely be a cryptographycally-secure
   *       derivation of a user-supplied password; the hashing seed will be
   *       used as-is by the backend, so any key derivation that needs to be
   *       applied MUST be applied beforehand.
   *
   *
   * @param Client $client  Client to use (this client MUST use the current backend, otherwise renewal may fail)
   * @param string $seed  Hashing seed (hexadecimal string)
   * @param int $batchSize  Number of hashes to generate (32 bits)
   * @return boolean
   */
  public function renew(Client $client, $seed, $batchSize = null) {
    if (null === $batchSize) {
      $batchSize = $this->minBatchSize;
    }
    // validate
    LamportTools::validateIdx($batchSize, $this->minBatchSize);

    // build hash list
    list($salt, $hash, $hashes) = LamportTools::hashList($this->token, $seed, $this->algorithm, $batchSize, $this->saltLength);

    // prepare the backend
    if (false === call_user_func($this->backend, 'prepare', $this->token, $salt, $hashes)) {
      throw new \Exception(__CLASS__ . ': backend failed to prepare renewal');
    }

    // do renewal proper
    if (self::isError(Client::dup($client)->rpc->x->auth->lamport->renew($this->token, $hash, $salt, $batchSize)->exec)) {
      throw new \Exception(__CLASS__ . ': failed to perform renewal');
    }

    // synchronize
    $this->synchronize($client);
  }

  /**
   * Use the given client object to synchronize the backend
   *
   * THIS IS A SERVICE METHOD FOR THE END USER.
   *
   * @param Client $client  Client to use
   * @return boolean
   * @throws \Exception
   */
  public function synchronize(Client $client) {
    if (self::isError($status = Client::dup($client)->rpc->x->auth->lamport->status($this->token)->exec)) {
      throw new \Exception(__CLASS__ . ': failed to perform syncronization');
    }
    if ($status->token !== $this->token) {
      throw new \Exception(__CLASS__ . ': inconsistent state');
    }
    return call_user_func($this->backend, 'synchronize', $status->token, $status->salt, $status->idx);
  }

}
