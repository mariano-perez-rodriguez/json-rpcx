<?php

/**
 * Http.php  Json RPC-X Http handler
 *
 * Json RPC-X Http handler.
 *
 */

namespace Json\RpcX\Handlers;

/**
 * Json RPC-X Http handler
 *
 */
class Http {

  /**
   * Url to hit
   *
   * @var string
   */
  protected $url;

  /**
   * Context options
   *
   * @var array
   */
  protected $contextOptions = null;

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
   * The constructor merely initialices the context options and url
   *
   * @param string $url  Url to hit (must validate according to FILTER_VALIDATE_URL)
   * @param float|null $timeout  Timeout to apply
   * @param string|null $bindTo  Interface to bind the TCP connection to
   * @param array $additionalHeaders  Additional headers to send (either as an array of strings, or as "header name" => "header value" pairs)
   * @param string|null $proxy  Proxy to connect through
   * @param boolean $fullUri  Whether to pass a full uri to the proxy or not
   * @param string $userAgent  User agent to send in the request
   * @throws \Exception
   */
  public function __construct($url, $timeout = null, $bindTo = null, array $additionalHeaders = [], $proxy = null, $fullUri = false, $userAgent = 'JsonRPCX') {
    if (false === ($this->url = filter_var($url, FILTER_VALIDATE_URL))) {
      throw new \Exception(__CLASS__ . ": invalid url '{$url}' given");
    }
    if (null !== $timeout && false === ($vTimeout = filter_var($timeout, FILTER_VALIDATE_FLOAT))) {
      throw new \Exception(__CLASS__ . ": invalid timeout '{$timeout}' given");
    }

    $aHeaders = [];
    $i        = 0;
    foreach ($additionalHeaders as $k => $v) {
      if (ctype_digit($k)) {
        list($kk, $vv) = array_filter(array_map('trim', explode(':', $v, 2)), 'strlen') + [null, null];
        if (null === $kk) {
          throw new \Exception(__CLASS__ . ": invalid header given at position {$i}");
        }
        if (null === $vv) {
          throw new \Exception(__CLASS__ . ": empty header given at position {$i}");
        }
        $aHeaders[] = "{$kk}: {$vv}";
      } else {
        $aHeaders[] = "{$k}: {$v}";
      }
      $i++;
    }

    $this->contextOptions = [
        'http' => [
            'method'        => 'POST',
            'header'        => [
        'Content-Type: application/json; charset=utf-8',
        'Accept: application/json',
        'Accept-Charset: utf-8',
        'Connection: close',
        'Cache-Control: no-cache, no-store',
        'Expires: 0',
        'Pragma: no-cache',
            ] + $aHeaders,
            'user_agent'    => (string) $userAgent,
            'ignore_errors' => true,
        ],
    ];

    if (null !== $proxy) {
      $this->contextOptions['http']['proxy'] = $proxy;
    }
    if ($fullUri) {
      $this->contextOptions['http']['request_fulluri'] = true;
    }
    if (null !== $timeout) {
      $this->contextOptions['http']['timeout'] = $vTimeout;
    }
    if (null !== $bindTo) {
      $this->contextOptions['tcp'] = ['bindto' => $bindTo];
    }
  }

  /**
   * Perform an HTTP call
   *
   * This method will encode the content as utf-8 if not already in that
   * encoding, and perform an HTTP request on the configured url and context.
   * It will return false on errors, or the response on success.
   *
   * @param string $content  Content to send
   * @return string|false
   */
  protected function doCall($content = null) {
    if (null !== $content) {
      $content = preg_replace('~^\s*((?U).*)\s*$~', '$1', $content);
      if (!preg_match('~~u', $content)) {
        $content = utf8_encode($content);
      }
    }
    $options = [
        'http' => [
            'header' => [
                'Content-Length: ' . self::bstrlen($content),
                'Content-MD5: ' . base64_encode(md5($content, true)),
                'Date: ' . gmdate('D, d M Y H:i:s T'),
            ],
        ]] + $this->contextOptions;
    return file_get_contents($this->url, false, stream_context_create($options + (null !== $content ? ['http' => ['content' => $content]] : [])));
  }

  /**
   * Execute the given action with the given parameters
   *
   * This method connects to the configured url and context, and reads the
   * response, returning false on errors.
   *
   * @param string $action  Action to take
   * @param mixed ...$params  Parameters for the action given
   * @return mixed
   * @throws \Exception
   */
  public function __invoke($action, ...$args) {
    switch (strtolower($action)) {
      case 'call':
        if (1 !== count($args)) {
          throw new \Exception(__CLASS__ . ": wrong number of arguments passed to 'call' action");
        }
        return $this->doCall($args[0]);
      default:
        throw new \Exception(__CLASS__ . ": unknown action '{$action}'");
    }
  }

}
