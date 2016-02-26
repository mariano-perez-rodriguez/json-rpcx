<?php

/**
 * Provider.php  Json RPC-X SHORT extension provider
 *
 * Class implementing a SHORT extension provider.
 *
 */

namespace Json\RpcX\Extension\Short;

/**
 * Needed for:
 *  - Short
 *
 */
use \Json\RpcX\Extension\Short\Extension;

/**
 * Class implementing a SHORT extension provider.
 *
 */
class Provider {

  /**
   * Request shortening parameter to use for construction
   *
   * @var boolean
   */
  protected $shortenRequest = true;

  /**
   * Request shorting honouring parameter to use for construction
   *
   * @var boolean
   */
  protected $honourShortRequest = true;

  /**
   * translations to use for construction
   *
   * @var array|null
   */
  protected $translations = null;

  /**
   * Set the internal shortenRequest parameter
   *
   * @param boolean $shortenRequest  Value to set shortenRequest to
   * @return self
   * @throws \Exception
   */
  protected function setShortenRequest($shortenRequest = true) {
    if (!is_bool($shortenRequest)) {
      throw new \Exception(__CLASS__ . ": 'shortenRequest' value must be boolean");
    }
    $this->shortenRequest = $shortenRequest;

    return $this;
  }

  /**
   * Set the internal honourShortRequest parameter
   *
   * @param boolean $honourShortRequest  Value to set honourShortRequest to
   * @return self
   * @throws \Exception
   */
  protected function setHonourShortRequest($honourShortRequest = true) {
    if (!is_bool($honourShortRequest)) {
      throw new \Exception(__CLASS__ . ": 'honourShortRequest' value must be boolean");
    }
    $this->honourShortRequest = $honourShortRequest;

    return $this;
  }

  /**
   * Set the internal translations parameter
   *
   * @param array $translations  Value to set translations to
   * @return self
   * @throws \Exception
   */
  protected function setTranslations(array $translations = null) {
    $this->translations = $translations;

    return $this;
  }

  /**
   * Constructor merely initialices the shortenRequest, honourShortRequest, and translations values
   *
   * @param boolean $shortenRequest  Value to set shortenRequest to
   * @param boolean $honourShortRequest  Value to set honourShortRequest to
   * @param array $translations  Value to set translations to
   */
  public function __construct($shortenRequest = true, $honourShortRequest = true, array $translations = null) {
    $this->setShortenRequest($shortenRequest);
    $this->setHonourShortRequest($honourShortRequest);
    $this->setTranslations($translations);
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
    switch (strtolower($command)) {
      case 'build':
        return new Extension($this->shortenRequest, $this->honourShortRequest, $this->translations);
      case 'config':
        switch (strtolower($name)) {
          case 'shortenrequest':
            $this->setShortenRequest($value);
            return true;
          case 'honourshortrequest':
            $this->setHonourShortRequest($value);
            return true;
          case 'translations':
            $this->setTranslations($value);
            return true;
          default:
            throw new \Exception(__CLASS__ . ": unknown parameter '{$name}'");
        }
      case 'reset':
        $this->shortenRequest     = true;
        $this->honourShortRequest = true;
        $this->translations       = null;
        return true;
      default:
        throw new \Exception(__CLASS__ . ": unknown command '{$command}'");
    }
  }

}
