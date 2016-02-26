<?php

/**
 * Backend.php  Json RPC-X AUTH extension - Extended Lamport authentication PhpRedis-based persistence backend
 *
 * Class implementing a PhpRedis backend for the Extended Lamport authentication scheme.
 *
 */

namespace Json\RpcX\Extension\Auth\Backend\Lamport\Persistence\PhpRedis;

/**
 * Class implementing a PhpRedis backend for the Extended Lamport authentication scheme.
 *
 * Server-side keys:
 *  - '<prefix>:pending:{<token>}': dummy key, which, if existing, means that
 *        the token in question has a pending registration,
 *  - '<prefix>:state:{<token>}': hash (ie. dictionary) with 'token', 'salt',
 *        'hash', and 'idx' fields.
 *
 * Client-side keys:
 *  - '<prefix>:data:{<token>}': hash (ie. dictionary) holding the current
 *        metadata for this token, having fields:
 *    - 'current': current index within this hashlist (integer),
 *    - 'salt': the salt identifying this hashlist (hexadecimal string),
 *  - '<prefix>:salts:{<token>}': a set with all the salts associated with
 *        this token as hexadecimal strings,
 *  - '<prefix>:hashes:{<token>}:<salt>': a zset with the hashes in the
 *        hashlist identified by the salt in question for the given token
 *        as elements and their index as score.
 *
 */
class Backend {

  /**
   * Number of hashes to pipe at a time
   *
   * @var int
   */
  const HASH_BATCH_SIZE = 100;

  /**
   * PhpRedis connection
   *
   * @var \Redis
   */
  protected $redis;

  /**
   * Hexadecimal SHA1 of the "enable" script
   *
   * @var string
   */
  protected $enableSha1;

  /**
   * Hexadecimal SHA1 of the "next" script
   *
   * @var string
   */
  protected $nextSha1;

  /**
   * Hexadecimal SHA1 of the "register" script
   *
   * @var string
   */
  protected $registerSha1;

  /**
   * Hexadecimal SHA1 of the "synchronize" script
   *
   * @var string
   */
  protected $synchronizeSha1;

  /**
   * Key namespacing prefix
   *
   * @var string
   */
  protected $prefix = 'rpc.x.auth.lamport';

  /**
   * Execute the given SHA1 on the given client instance passing the given arguments, return the result or throw an exception
   *
   * @param \Redis $redis  Redis client to use
   * @param string $sha1  SHA1 of the script to evaluate (hexadecimal string)
   * @param int $numKeys  Number of keys among the given arguments
   * @param mixed[] $args  Arguments to pass to the Lua script
   * @return mixed
   * @throws \Exception
   */
  protected static function evalSha(\Redis $redis, $sha1, $numKeys, ...$args) {
    if (!ctype_xdigit($sha1)) {
      throw new \Exception(__CLASS__ . ": non-hexadecimal SHA1 '{$sha1}' given");
    }
    if ($numKeys < 0 || count($args) < $numKeys) {
      throw new \Exception(__CLASS__ . ": given number of keys '{$numKeys}' is not between 0 and " . count($args));
    }

    $redis->clearLastError();
    $result = $redis->evalsha($sha1, $args, $numKeys);
    $error  = $redis->getLastError();
    $redis->clearLastError();

    if (null !== $error) {
      throw new \Exception(__CLASS__ . ": Redis error - {$error}");
    }

    return $result;
  }

  /**
   * Construct a new persistence backend
   *
   * @param \Redis $redis  Already connected php-redis object
   * @param string $prefix  Key namespacing prefix to use
   * @throws \Exception
   */
  public function __construct(\Redis $redis, $prefix = 'rpc.x.auth.lamport') {
    if (!$redis->isConnected()) {
      throw new \Exception(__CLASS__ . ': redis is not connected');
    }
    $this->redis  = $redis;
    $this->prefix = $prefix;

    if (false === ($this->enableSha1 = $this->redis->script('load', file_get_contents(dirname(__FILE__) . '/enable.lua')))) {
      throw new \Exception(__CLASS__ . ": error loading 'enable' script");
    }
    if (false === ($this->nextSha1 = $this->redis->script('load', file_get_contents(dirname(__FILE__) . '/next.lua')))) {
      throw new \Exception(__CLASS__ . ": error loading 'next' script");
    }
    if (false === ($this->registerSha1 = $this->redis->script('load', file_get_contents(dirname(__FILE__) . '/register.lua')))) {
      throw new \Exception(__CLASS__ . ": error loading 'register' script");
    }
    if (false === ($this->synchronizeSha1 = $this->redis->script('load', file_get_contents(dirname(__FILE__) . '/synchronize.lua')))) {
      throw new \Exception(__CLASS__ . ": error loading 'synchronize' script");
    }
  }

  /**
   * Invoke the given action on the backend
   *
   * @param string $action  Action to perform
   * @param mixed ...$args  arguments for the action in question
   * @return mixed
   */
  public function __invoke($action, ...$args) {
    switch (strtolower($action)) {
      case 'enable':
        if (1 !== count($args)) {
          return false;
        }
        return $this->doEnable(...$args);
      case 'register':
        if (4 !== count($args)) {
          return false;
        }
        return $this->doRegister(...$args);
      case 'get':
        if (1 !== count($args)) {
          return false;
        }
        return $this->doGet(...$args);
      case 'set':
        if (4 !== count($args)) {
          return false;
        }
        return $this->doSet(...$args);
      //
      case 'next':
        if (3 !== count($args)) {
          return false;
        }
        return $this->doNext(...$args);
      case 'prepare':
        if (3 !== count($args)) {
          return false;
        }
        return $this->doPrepare(...$args);
      case 'synchronize':
        if (3 !== count($args)) {
          return false;
        }
        return $this->doSynchronize(...$args);
      //
      default:
        throw new \Exception(__CLASS__ . ": unknown action '{$action}'");
    }
  }

  //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  /**
   * Enable future registration of the given token
   *
   * @param string $token  Token to enable registration for (hexadecimal string)
   * @return boolean
   */
  protected function doEnable($token) {
    return self::evalSha($this->redis, $this->enableSha1, 1, $token /* dummy */, $this->prefix /* key construction */);
  }

  /**
   * Add the server-side data associated with the given token if pending registration
   *
   * @param string $token  Token in question (hexadecimal string)
   * @param string $salt  Salt to use (hexadecimal string)
   * @param string $hash  Hash to use (hexadecimal string)
   * @param int $idx  Index to use
   * @return boolean
   */
  protected function doRegister($token, $salt, $hash, $idx) {
    return self::evalSha($this->redis, $this->registerSha1, 1, $token /* dummy */, $this->prefix /* key construction */, $salt, $hash, $idx);
  }

  /**
   * Return the server-side data associated with the given token
   *
   * @param string $token  Token to get data for (hexadecimal string)
   * @return array
   */
  protected function doGet($token) {
    return $this->redis->hGetAll("{$this->prefix}:state:{{$token}}");
  }

  /**
   * Set the server-side data associated with the given token
   *
   * @param string $token  Token in question (hexadecimal string)
   * @param string $salt  Salt to use (hexadecimal string)
   * @param string $hash  Hash to use (hexadecimal string)
   * @param int $idx  Index to use
   * @return boolean
   */
  protected function doSet($token, $salt, $hash, $idx) {
    return $this->redis->hMset("{$this->prefix}:state:{{$token}}", ['token' => $token, 'salt' => $salt, 'hash' => $hash, 'idx' => $idx]);
  }

  //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  /**
   * Prepare a list of hashes for a given token and salt
   *
   * @param string $token  Token to prepare (hexadecimal string)
   * @param string $salt  Salt to associate (hexadecimal string)
   * @param array $hashes  Hashes to prepare (hexadecimal strings)
   * @return boolean
   */
  protected function doPrepare($token, $salt, array $hashes) {
    // add the salt to the known salts set, fail if not new
    if (0 === $this->redis->sAdd("{$this->prefix}:salts:{{$token}}", $salt)) {
      return false;
    }
    // chunk and add
    $i = 0;
    foreach (array_chunk($hashes, self::HASH_BATCH_SIZE) as $chunk) {
      $pipe = $this->redis->multi(\Redis::PIPELINE);
      foreach ($chunk as $hash) {
        $pipe->zAdd("{$this->prefix}:hashes:{{$token}}:{$salt}", $i++, $hash);
      }
      // in case of errors...
      if ([] !== array_filter($pipe->exec(), function ($x) {
                return 1 !== $x;
              })) {
        // remove the newly created key and salt
        $this->redis
                ->multi()
                ->delete("{$this->prefix}:hashes:{{$token}}:{$salt}")
                ->sRem("{$this->prefix}:salts:{{$token}}", $salt)
                ->exec();
        // and fail
        return false;
      }
    }
    // everything went fine
    return true;
  }

  /**
   * Extract the next available hashes from the given token
   *
   * @param string $token  Token to get next hashes for (hexadecimal string)
   * @param int $count  Number of hashes to extract
   * @param int $remaining  Number of hashes that must, at least, remain afterwards
   * @return boolean
   */
  protected function doNext($token, $count, $remaining) {
    return self::evalSha($this->redis, $this->nextSha1, 1, $token /* dummy */, $this->prefix /* key construction */, $count, $remaining);
  }

  /**
   * Perform token synchronization
   *
   * @param string $token  Token to synchronize (hexadecimal string)
   * @param string $salt  Salt to associate (hexadecimal string)
   * @param int $idx  Index to ensure
   * @return boolean
   */
  protected function doSynchronize($token, $salt, $idx) {
    return self::evalSha($this->redis, $this->synchronizeSha1, 1, $token /* dummy */, $this->prefix /* key construction */, strtolower($salt), $idx);
  }

}
