<?php

/**
 * Https.php  Json RPC-X Https handler
 *
 * Json RPC-X Https handler.
 *
 */

namespace Json\RpcX\Handlers;

/**
 * Needed for:
 *  - Http
 *
 */
use \Json\RpcX\Handlers\Http;

/**
 * Json RPC-X Https handler
 *
 */
class Https extends Http {

  /**
   * The constructor merely initialices the context options
   *
   * @param string $url  Url to hit (must validate according to FILTER_VALIDATE_URL)
   * @param float|null $timeout  Timeout to apply
   * @param string|null $bindTo  Interface to bind the TCP connection to
   * @param array $additionalHeaders  Additional headers to send (either as an array of strings, or as "header name" => "header value" pairs)
   * @param string|null $proxy  Proxy to connect through
   * @param boolean $fullUri  Whether to pass a full uri to the proxy or not
   * @param string $userAgent  User agent to send in the request
   * @param array $peer  An array containing fields 'name' and 'fingerprint', responding to 'peer_name' and 'peer_fingerprint' formats
   * @param boolean $allowSelfSigned  Whether to allow self-signed certificates
   * @param array $ca  An array containing fields 'file' and 'path', responding to 'cafile' and 'capath' formats
   * @param array $cert  An array containing fields 'local' and 'passphrase', responding to 'local_cert' and 'passphrase' formats (if no 'local' given, 'passphrase' will be ignored)
   * @throws \Exception
   */
  public function __construct($url, $timeout = null, $bindTo = null, array $additionalHeaders = [], $proxy = null, $fullUri = false, $userAgent = 'JsonRPCX', array $peer = [], $allowSelfSigned = false, array $ca = [], array $cert = []) {
    parent::__construct($url, $timeout, $bindTo, $additionalHeaders, $proxy, $fullUri, $userAgent);
    $this->contextOptions['ssl'] = [
        'verify_peer'             => true,
        'verify_peer_name'        => true,
        'verify_depth'            => 5,
        'allow_self_signed'       => (bool) $allowSelfSigned,
        'ciphers'                 => 'kEDH+AESGCM:RSA+AESGCM:RSA+AES+SHA:RC4-SHA:HIGH:EECDH+aRSA+AESGCM:EECDH+aRSA+AES:EDH+aRSA+AESGCM:EDH+aRSA+AES:ECDHE-RSA-RC4-SHA:ECDHE-RSA-AES256-SHA384:ECDHE-RSA-AES256-SHA:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-RSA-AES128-SHA256:ECDHE-RSA-AES128-SHA:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-RC4-SHA:ECDHE-ECDSA-AES256-SHA384:ECDHE-ECDSA-AES256-SHA:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-ECDSA-AES128-SHA256:ECDHE-ECDSA-AES128-SHA:ECDHE-ECDSA-AES128-GCM-SHA256:DHE-RSA-AES256-SHA256:DHE-RSA-AES256-SHA:DHE-RSA-AES128-SHA256:DHE-RSA-AES128-SHA:DHE-RSA-AES128-GCM-SHA256:DHE-DSS-AES256-SHA:DHE-DSS-AES128-SHA256:DHE-DSS-AES128-GCM-SHA256:DES-CBC3-SHA:AES256-GCM-SHA384:AES256:AES128-GCM-SHA256:AES128:-DHE-RSA-AES128-SHA:!eNULL:!aNULL:!SEED:!RC4:!PSK:!NULL:!MD5:!LOW:!IDEA:!EXPORT:!EXP:!ECDSA:!DSS:!DES:!ADH:!3DES',
        'capture_peer_cert'       => false,
        'capture_peer_cert_chain' => false,
        'SNI_enabled'             => true,
        'disable_compression'     => true,
    ];

    $peer += ['name' => null, 'fingerprint' => null];
    if (null !== $peer['name']) {
      $this->contextOptions['ssl']['peer_name'] = $peer['name'];
    }
    if (null !== $peer['fingerprint']) {
      $this->contextOptions['ssl']['peer_fingerprint'] = $peer['fingerprint'];
    }
    $ca += ['file' => null, 'path' => null];
    if (null !== $ca['file']) {
      $this->contextOptions['ssl']['cafile'] = $ca['file'];
    }
    if (null !== $ca['path']) {
      $this->contextOptions['ssl']['capath'] = $ca['path'];
    }
    $cert += ['local' => null, 'passphrase' => null];
    if (null !== $cert['local']) {
      $this->contextOptions['ssl']['local_cert'] = $cert['local'];
      if (null !== $cert['passphrase']) {
        $this->contextOptions['ssl']['passphrase'] = $cert['passphrase'];
      }
    }
  }

}
