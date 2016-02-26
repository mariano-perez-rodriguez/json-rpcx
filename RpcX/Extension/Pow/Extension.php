<?php

/**
 * Extension.php  Json RPC-X Proof-Of-Work extension
 *
 * Class to implement the Proof-Of-Work extension.
 *
 */

namespace Json\RpcX\Extension\Pow;

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
 *  - Tools::jsonEncode(),
 *  - Tools::microtime()
 *
 */
use \Json\RpcX\Tools;
/**
 * Needed for:
 *  - Exception::internalError()
 *
 */
use \Json\RpcX\Exception;
/**
 * Needed for:
 *  - MissingPow
 *
 */
use \Json\RpcX\Extension\Pow\MissingPow;
/**
 * Needed for:
 *  - InvalidPow
 *
 */
use \Json\RpcX\Extension\Pow\InvalidPow;
/**
 * Needed for:
 *  - Timeout
 *
 */
use \Json\RpcX\Extension\Pow\Timeout;
/**
 * Needed for:
 *  - Failed
 *
 */
use \Json\RpcX\Extension\Pow\Failed;

/**
 * Class to implement the Proof-Of-Work extension
 *
 * This class implements the Proof-Of-Work extension
 *
 */
class Extension implements ExtensionInterface {

  /**
   * Number of hashes to try between timeout checks
   *
   * @var int
   */
  const SAMPLING_INTERVAL = 1000;

  /**
   * Deal with boilerplate
   *
   */
  use ExtensionTrait;

  /**
   * Hashing algorithm to use for POW (must be one of "hash_algos()")
   *
   * @var string
   */
  protected $algorithm = 'sha1';

  /**
   * Minimum number of 0-bits to aim for, or proportion of the hash width if float
   *
   * @var int|float
   */
  protected $difficulty = 0.1;

  /**
   * Difficulty value cache, null if uninitialized
   *
   * @var int|null
   */
  private $difficultyCache = null;

  /**
   * Timeout in microseconds, or null for no timeout
   *
   * @var int|null
   */
  protected $timeout = null;

  /**
   * Get the length of a binary string (ie. ignoring multibyte conversion)
   *
   * @param string $string  String to get the length for
   * @return int
   */
  protected static function bstrlen($string) {
    return function_exists('mb_strlen') ? mb_strlen($string, '8bit') : strlen($string);
  }

  /**
   * Return the number of trailing zero bits in the hex string given
   *
   * @param string $val  Value in question (hexadecimal string)
   * @return int
   */
  protected static function hexTrailingZ($val) {
    $tval  = strtolower(trim($val));
    $ztval = rtrim($tval, '0');
    return 4 * (strlen($tval) - strlen($ztval)) + [
        '1'   => 0, '3'   => 0, '5'   => 0, '7'   => 0, '9'   => 0, 'b'   => 0, 'd'   => 0, 'f'   => 0,
        '2'   => 1, '6'   => 1, 'a'   => 1, 'e'   => 1,
        '4'   => 2, 'c'   => 2,
        '8'   => 3,
        false => 0,
            ][substr($ztval, -1)];
  }

  /**
   * Convert the given value to a 0-padded 64-bit hexadecimal string
   *
   * @param int $val  Value to convert
   * @return string
   */
  protected static function toHex($val) {
    return str_pad(base_convert($val, 10, 16), 16 /* 64 / 4 */, '0', STR_PAD_LEFT);
  }

  /**
   * Get the calculated difficulty
   *
   * @return int
   */
  protected function getDifficulty() {
    if (null === $this->difficultyCache) {
      $this->difficultyCache = is_integer($this->difficulty) ? $this->difficulty : (int) ceil(4 * $this->difficulty * self::bstrlen(hash($this->algorithm, __CLASS__)));
    }
    return $this->difficultyCache;
  }

  /**
   * Constructor merely sets the algorithm to use, the difficulty to aim for, and the timeout to honour
   *
   * @param string $algorithm  Hashing algorithm to use
   * @param int|float $difficulty  Difficulty to aim for, either as a number of bits or as a fraction of the hash length
   * @param int|null $timeout  Timeout to use, or null for no timeout
   * @throws \Exception
   */
  public function __construct($algorithm = 'sha1', $difficulty = 0.1, $timeout = null) {
    if (!in_array(strtolower($algorithm), array_map('strtolower', hash_algos()))) {
      throw new \Exception(__CLASS__ . ": unknown hashing algorithm '{$algorithm}' given");
    }
    // hash length in bits
    $hashlen = 8 * self::bstrlen(hash($algorithm, __CLASS__, true));

    if (is_integer($difficulty)) {
      if ($difficulty < 0 || $hashlen <= $difficulty) {
        throw new \Exception(__CLASS__ . ": integer difficulty must be in the range [0, {$hashlen})");
      }
    } else if (is_float($difficulty)) {
      if ($difficulty < 0.0 || 1.0 <= $difficulty) {
        throw new \Exception(__CLASS__ . ': float difficulty must be in the range [0.0, 1.0)');
      }
    } else {
      throw new \Exception(__CLASS__ . ': difficulty must be integer or float');
    }

    if (null !== $timeout) {
      if (!is_integer($timeout)) {
        throw new \Exception(__CLASS__ . ': timeout must be integer or null');
      } else if ($timeout <= 0) {
        throw new \Exception(__CLASS__ . ': timeout must be positive');
      }
    }

    $this->algorithm  = $algorithm;
    $this->difficulty = $difficulty;
    $this->timeout    = $timeout;
  }

  /**
   * Execute the postDecodeRequest hook by verifying the proof of work
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
    if (!property_exists($request, 'pow')) {
      throw new MissingPow();
    } else if ([] !== array_diff(array_keys(get_object_vars($request->pow)), ['algo', 'nonce', 'proof'])) {
      throw new InvalidPow();
    } else if ($request->pow->algo !== $this->algorithm) {
      throw new InvalidPow();
    } else if (!ctype_xdigit($request->pow->nonce)) {
      throw new InvalidPow();
    } else if (!ctype_xdigit($request->pow->proof)) {
      throw new InvalidPow();
    } else if (self::hexTrailingZ($request->pow->proof) < $this->getDifficulty()) {
      throw new InvalidPow();
    }

    // extract proof
    $proof = strtolower(trim($request->pow->proof));

    // create prototype by cloning and removing the proof
    $proto = clone $request;
    unset($proto->pow->proof);

    if (null === ($json = Tools::jsonEncode($proto, false))) {
      throw Exception::internalError();
    }

    if ($proof !== strtolower(hash($this->algorithm, $json))) {
      throw new InvalidPow();
    }

    // unset the pow field and return
    unset($request->pow);
    return $request;
  }

  /**
   * Execute the preEncodeRequest hook by generating a proof of work
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
    $request->pow       = new \stdClass();
    $request->pow->algo = $this->algorithm;

    // get current difficulty
    $difficulty = $this->getDifficulty();

    // set start time and sampling counter
    $start = Tools::microtime();
    $ctr   = 0;
    // iterate through every int available
    for ($i = ~PHP_INT_MAX; $i !== PHP_INT_MAX; $i++) {
      if (null !== $this->timeout && self::SAMPLING_INTERVAL <= ++$ctr) {
        $ctr = 0;
        if ($this->timeout < Tools::microtime() - $start) {
          throw new Timeout();
        }
      }
      $request->pow->nonce = self::toHex($i);

      if (null === ($json = Tools::jsonEncode($request, false))) {
        throw Exception::internalError();
      }
      $proof = strtolower(hash($this->algorithm, $json));
      if ($difficulty <= self::hexTrailingZ($proof)) {
        $request->pow->proof = $proof;
        return $request;
      }
    }

    throw new Failed();
  }

  /**
   * Return the postDecodeRequest hook priority for this instance, fixed at -600
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  public function postDecodeRequestPriority() {
    return -600;
  }

  /**
   * Return the preEncodeRequest hook priority for this instance, fixed at 600
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  public function preEncodeRequestPriority() {
    return 600;
  }

}
