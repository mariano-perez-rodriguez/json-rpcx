<?php

/**
 * Provider.php  Json RPC-X POW extension provider
 *
 * Class implementing a POW extension provider.
 *
 */

namespace Json\RpcX\Extension\Pow;

/**
 * Needed for:
 *  - Pow
 *
 */
use \Json\RpcX\Extension\Pow\Extension;

/**
 * Class implementing a POW extension provider.
 *
 */
class Provider {

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
   * Set the internal algorithm parameter
   *
   * @param string $algorithm  Value to set algorithm to (must be one of "hash_algos()")
   * @return self
   * @throws \Exception
   */
  protected function setAlgorithm($algorithm) {
    if (!in_array(strtolower($algorithm), array_map('strtolower', hash_algos()))) {
      throw new \Exception(__CLASS__ . ": unknown hashing algorithm '{$algorithm}' given");
    }
    $this->algorithm = strtolower($algorithm);

    return $this;
  }

  /**
   * Set the internal difficulty parameter
   *
   * @param int|float $difficulty  Value to set difficulty to
   * @return self
   * @throws \Exception
   */
  protected function setDifficulty($difficulty) {
    // hash length in bits
    $hashlen = 8 * self::bstrlen(hash($thi->algorithm, __CLASS__, true));

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
    $this->difficulty = $difficulty;

    return $this;
  }

  /**
   * Set the internal timeout parameter
   *
   * @param int|null $timeout  Value to set timeout to (or null, for no timeout)
   * @return self
   * @throws \Exception
   */
  protected function setTimeout($timeout) {
    if (null !== $timeout) {
      if (!is_integer($timeout)) {
        throw new \Exception(__CLASS__ . ': timeout must be integer or null');
      } else if ($timeout <= 0) {
        throw new \Exception(__CLASS__ . ': timeout must be positive');
      }
    }
    $this->timeout = $timeout;

    return $this;
  }

  /**
   * Handle provider commands
   *
   * @param string $command  Command to handle
   * @param string  $name  Configuration name to set (only on "config" command)
   * @param mixed $value  Configuration value to use (only on "config" command)
   * @return Extension|boolean
   * @throws \Exception
   */
  public function __invoke($command, $name = null, $value = null) {
    // just keep NetBeans happy
    false && $value;

    switch (strtolower($command)) {
      case 'build':
        return new Extension($this->algorithm, $this->difficulty, $this->timeout);
      case 'config':
        switch (strtolower($name)) {
          case 'algorithm':
            $this->setAlgorithm($value);
            return true;
          case 'difficulty':
            $this->setDifficulty($value);
            return true;
          case 'timeout':
            $this->setTimeout($value);
            return true;
          default:
            throw new \Exception(__CLASS__ . ": unknown parameter '{$name}'");
        }
      case 'reset':
        $this->algorithm  = 'sha1';
        $this->difficulty = 0.1;
        $this->timeout    = null;
        return true;
      default:
        throw new \Exception(__CLASS__ . ": unknown command '{$command}'");
    }
  }

}
