<?php

/**
 * Tools.php  Json RPC-X AUTH extension - Extended Lamport authentication backend - Tools static class
 *
 * Class implementing the common tools for a extended Lamport authentication scheme.
 *
 */

namespace Json\RpcX\Extension\Auth\Backend\Lamport;

/**
 * Needed for:
 *  - Tools::compareSimpleGlob(),
 *  - Tools::simpleGlob()
 *
 */
use \Json\RpcX\Tools as RpcXTools;
/**
 * Needed for:
 *  - Exception::invalidParams()
 *
 */
use \Json\RpcX\Exception;

/**
 * Class implementing the common tools for a extended Lamport authentication scheme.
 *
 */
class Tools {

  /**
   * Get the length of a binary string (ie. ignoring multibyte conversion)
   *
   * @param string $string  String to get the length for
   * @return int
   */
  public static function bstrlen($string) {
    return function_exists('mb_strlen') ? mb_strlen($string, '8bit') : strlen($string);
  }

  //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  /**
   * Invert every edge in the given graph
   *
   * The graph given must be specified as an array, mapping nodes to a list
   * of neighbours. Note that EVERY NODE MUST APPEAR AS KEY, even if its list
   * of neighbours is empty.
   *
   * @param array $g  Graph to invert
   * @return array
   */
  public static function graphInvert(array $g) {
    $vs = array_keys($g);

    $return = array_fill_keys($vs, []);
    foreach ($g as $v => $ws) {
      foreach ($ws as $w) {
        $return[$w][] = $v;
      }
    }
    return $return;
  }

  /**
   * Compute the strongly connected components of a graph
   *
   * This function uses Tarjan's algorithm as taken from https://en.wikipedia.org/wiki/Tarjan%27s_strongly_connected_components_algorithm.
   *
   * This function returns a list of sets of nodes determining the strongly
   * connected components of the given graph.
   *
   * @param array $g  Graph to calculate SCCs for
   * @return array
   */
  public static function graphSccs(array $g) {
    $strongconnect = function ($v, array &$state, array &$return) use (&$strongconnect) {
      $state['vs'][$v]['idx'] = $state['vs'][$v]['low'] = $state['idx'] ++;
      $state['vs'][$v]['ons'] = true;

      $state['stack'][] = $v;

      foreach ($state['g'][$v] as $w) {
        if (null === $state['vs'][$w]['idx']) {
          $strongconnect($w, $state, $return);
          $state['vs'][$v]['low'] = min($state['vs'][$v]['low'], $state['vs'][$w]['low']);
        } else if ($state['vs'][$w]['ons']) {
          $state['vs'][$v]['low'] = min($state['vs'][$v]['low'], $state['vs'][$w]['idx']);
        }
      }

      if ($state['vs'][$v]['low'] === $state['vs'][$v]['idx']) {
        $component = [];
        do {
          $w                      = array_pop($state['stack']);
          $state['vs'][$w]['ons'] = false;
          $component[]            = $w;
        } while ($w !== $v);
        $return[] = $component;
      }
    };

    $vs = array_keys($g);

    $state = [
        'g'     => $g,
        'idx'   => 0,
        'stack' => [],
        'vs'    => array_fill_keys($vs, ['idx' => null, 'low' => null, 'ons' => false]),
    ];

    $return = [];
    foreach ($vs as $v) {
      if (null === $state['vs'][$v]['idx']) {
        $strongconnect($v, $state, $return);
      }
    }
    return $return;
  }

  /**
   * Compute the graph's cyclone nodes
   *
   * This function computes the cyclone nodes of a given grpah.
   *
   * A node is a cyclone node, if it belongs to an SCC which has 0 out-degree
   * in the graph's condensation.
   *
   * @param array $g  Graph to compute cyclones for
   * @return array
   */
  public static function graphCyclones(array $g) {
    $sccs   = self::graphSccs($g);
    $return = [];
    foreach ($sccs as $scc) {
      $cyclone = true;
      foreach ($scc as $v) {
        if ([] !== array_diff($g[$v], $scc)) {
          $cyclone = false;
          break;
        }
      }
      if ($cyclone) {
        $return = array_merge($return, $scc);
      }
    }
    return $return;
  }

  /**
   * Compute the graph's anticyclone nodes
   *
   * This function computes the anticyclone nodes of a given grpah.
   *
   * A node is an anticyclone node, if it belongs to an SCC which has
   * 0 in-degree in the graph's condensation.
   *
   * This function works because of three facts:
   *  1. A given graph's SCCs are identical to those of its inverse,
   *  2. condensation and inversion commute,
   *  3. having out-degree 0 mens having in-degree 0 in the inverse.
   *
   * @param array $g  Graph to compute anticyclones for
   * @return array
   */
  public static function graphAnticyclones(array $g) {
    return self::graphCyclones(self::graphInvert($g));
  }

  //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  /**
   * Calculate the simpleGlob anticyclones amongst the given simpleGlobs
   *
   * @param array $sgs  List of simpleGlob patterns to analyze
   * @return array
   */
  public static function simpleGlobsAnticyclones(array $sgs) {
    $vsgs  = array_values($sgs);
    $graph = array_fill(0, count($vsgs), []);
    foreach ($vsgs as $v => $sg1) {  // ;)
      foreach ($vsgs as $u => $sg2) {
        if ($sg1 === $sg2) {
          continue;
        }
        switch (RpcXTools::compareSimpleGlob($sg1, $sg2)) {
          case -1:
            $graph[$v][] = $u;
            break;
          case 0:
            $graph[$v][] = $u;
            $graph[$u][] = $v;
            break;
          case 1:
            $graph[$u][] = $v;
            break;
        }
      }
    }
    // clean up the graph
    foreach ($graph as $v => &$ws) {
      $ws = array_unique($ws);
    }
    $return = [];
    foreach (self::graphAnticyclones($graph) as $i) {
      $return[] = $vsgs[$i];
    }
    return $return;
  }

  /**
   * Return the required number of tokens and the minimum remaining indexes for the given method under the given strength map
   *
   * This function will extract, from the given strength map and the given
   * method name, the number of tokens required for authentication, and the
   * number of remaining indexes that must be present for it to succeed, as
   * an array whose first element is the required number, and its second the
   * minimum remaining.
   *
   * @param array $strengthMap  Strength map to use
   * @param string $method  Method in question
   * @return array
   */
  public static function getReqMin(array $strengthMap, $method) {
    // extract appliccable simpleGlobs
    $sgs = [];
    foreach (array_keys($strengthMap) as $sg) {
      if (RpcXTools::simpleGlob($sg, $method)) {
        $sgs[] = $sg;
      }
    }

    // calculate final required and minimum values by iterating anticyclones
    $req  = -1;
    $tmin = -1;
    foreach (self::simpleGlobsAnticyclones($sgs) as $sg) {
      $req  = max($req, $strengthMap[$sg]['req']);
      $tmin = max($tmin, $strengthMap[$sg]['min']);
    }

    // return the required and minimum
    return [$req, max($tmin, $req)];
  }

  //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  /**
   * Validate the given token and throw exception if invalid
   *
   * @param string $token  Token to validate (hexadecimal string)
   * @throws Exception
   */
  public static function validateToken($token) {
    if (!ctype_xdigit($token)) {
      throw Exception::invalidParams('non-hexadecimal token');
    }
  }

  /**
   * Validate the given hash under the given algorithm and throw exception if invalid
   *
   * @param string $hash  Hash to validate (hexadecimal string)
   * @param string $algorithm  Algorith to validate against
   * @throws Exception
   */
  public static function validateHash($hash, $algorithm) {
    if (!ctype_xdigit($hash)) {
      throw Exception::invalidParams('non-hexadecimal hash');
    } else if (self::bstrlen($hash) !== self::bstrlen(hash($algorithm, ''))) {
      throw Exception::invalidParams('hash length mismatch');
    }
  }

  /**
   * Validate the given salt against the given length and throw exception if invalid
   *
   * @param string $salt  Salt to validate (hexadecimal string)
   * @param int $saltLength  Salt length to use
   * @throws Exception
   */
  public static function validateSalt($salt, $saltLength) {
    if (!ctype_xdigit($salt)) {
      throw Exception::invalidParams('non-hexadecimal salt');
    } else if (4 * self::bstrlen($salt) !== $saltLength) {
      throw Exception::invalidParams('salt length mismatch');
    }
  }

  /**
   * Validate a given number of indexes against the minimum expected and throw exception if invalid
   *
   * @param int $idx  Index to validate
   * @param int $minBatchSize  Minimum number of indexes to accept
   * @throws Exception
   */
  public static function validateIdx($idx, $minBatchSize) {
    if ($idx < $minBatchSize) {
      throw Exception::invalidParams('insufficient indexes');
    } else if (2 ** 32 - 1 < $idx) {
      throw Exception::invalidParams('too many indexes');
    }
  }

  //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  /**
   * Pack the given arguments into a form suitable for hashing
   *
   * @param string $token  Token to pack (hexadecimal string)
   * @param int $idx  Index to pack
   * @param string $salt  Salt to pack (hexadecimal string)
   * @param string $hash hash to pack (hexadecimal string)
   * @return string
   * @throws \Exception
   */
  public static function pack($token, $idx, $salt, $hash) {
    if (false === ($bhash  = hex2bin($hash)) || false === ($bsalt  = hex2bin($salt)) || false === ($btoken = hex2bin($token))) {
      throw new \Exception(__CLASS__ . ': invalid arguments for packing');
    }
    $bidx = pack('N', $idx);

    return "|{$bidx}||{$btoken}|||{$bsalt}||||{$bhash}|||||";
  }

  /**
   * This method will generate a list of hashes from the given token and seed, using the given algorithm, generating a seed of the given length and of the given size
   *
   * This function will generate the given number of chained hashes, using
   * the given seed and token, under the given algorithm, generating a salt
   * of the given length.
   *
   * This function will return an array with the generated salt as its first
   * element, the last hash as its second, and the list of hashes as its third
   * (this list has the initial hashes at the front, and the deeper hashes
   * at the back).
   *
   *
   * NOTE: the token given is a user identifier, much like a username.
   *
   * NOTE: the hashing seed will most likely be a cryptographycally-secure
   *       derivation of a user-supplied password; the hashing seed will be
   *       used as-is by the backend, so any key derivation that needs to be
   *       applied MUST be applied beforehand.
   *
   * @param string $token  Token to use (hexadecimal string)
   * @param string $seed  Hashing seed (hexadecimal string)
   * @param string $algorithm  Hashing algorithm to use (one of "hash_algos()")
   * @param int $batchSize  Number of hashes to generate (32 bits)
   * @param int $saltLength  Length in bits of the salt to geerate
   * @return array
   * @throws \Exception
   */
  public static function hashList($token, $seed, $algorithm, $batchSize, $saltLength) {
    // generate salt
    if (function_exists('mcrypt_create_iv')) {
      $salt = bin2hex(mcrypt_create_iv($saltLength / 8, MCRYPT_DEV_URANDOM));
    } else if (file_exists('/dev/urandom') && is_readable('/dev/urandom')) {
      $salt = bin2hex(file_get_contents('/dev/urandom', false, null, -1, $saltLength / 8));
    } else {
      throw new \Exception(__CLASS__ . ': no suitable source of randomness found');
    }

    // build hash list
    $hash   = $seed;
    $hashes = [];
    for ($i = 0; $i < $batchSize; $i++) {
      $hashes[] = $hash     = hash($algorithm, self::pack($token, $i, $salt, $hash));
    }

    // return it
    return [$salt, $hash, $hashes];
  }

  //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  /**
   * Ask the given backend for data on the given token, and apply basic validations
   *
   * This function will return an array with 'token', 'salt', 'hash', and
   * 'idx' fields, or false on validation error.
   *
   * @param callable $backend  Persistence backend to use
   * @param string $token  Token to use (hexadecimal string)
   * @return array|false
   * @throws Exception
   */
  public static function getData(callable $backend, $token) {
    if (false === ($data = call_user_func($backend, 'get', $token))) {
      return false;
    } else if (['token', 'salt', 'hash', 'idx'] !== array_keys((array) $data)) {
      return false;
    } else if ($token !== $data['token']) {
      return false;
    }

    return $data;
  }

}
