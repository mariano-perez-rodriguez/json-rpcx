<?php

/**
 * Rpcx.php  Json RPC-X Abstract Base Class
 *
 * Json RPC-X ABC.
 *
 */

namespace Json;

/**
 * Json RPC-X ABC
 *
 */
abstract class Rpcx {

  /**
   * Extension registry
   *
   * This array maps extension names to extension providers.
   *
   * An extension provider is a callable suporting the following arguments:
   *  - string $action: the action being attempted, this value further
   *        determines the following parameters; possible values are:
   *    - 'build': try to create a new extension, takes no further arguments.
   *        This call should return a new extension, created according to the
   *        configuration passed, if any.
   *    - 'config': accept a configuration option, parameters are:
   *      - string $name: configuration parameter's name,
   *      - mixed $value: configuration parameter's value.
   *        This call should return true on success, false on failure.
   *    - 'reset': reset the configuration options given (this action is taken
   *          before reconfiguring for a new extension). This call should
   *          return true on success, false on failure.

   *
   * @var array
   */
  protected $extensionProviders = [];

  /**
   * This method will register a new extension provider to be used, it will return true on success, false on failure
   *
   * @param callable $extensionProvider  Extension provider to register
   * @return boolean
   * @throws \Exception
   */
  final protected function addExtension(callable $extensionProvider) {
    $extension = call_user_func($extensionProvider, 'build');
    if (!is_a($extension, 'Json\\RpcX\\ExtensionInterface')) {
      throw new \Exception(__CLASS__ . ': extension provider did not produce an instance of Json\\RpcX\\ExtensionInterface');
    }
    $name = $extension->name();
    if (!is_string($name)) {
      throw new \Exception(__CLASS__ . ': could not retrieve extension name');
    }
    if (array_key_exists($name, $this->extensionProviders)) {
      return false;
    }
    $this->extensionProviders[$name] = $extensionProvider;
    return true;
  }

  /**
   * Return the registered extensions' names
   *
   * @return array
   */
  final protected function getExtensionNames() {
    return array_keys($this->extensionProviders);
  }

  /**
   * Ask all the extension providers for a new extension
   *
   * @return array
   */
  final protected function getExtensions() {
    return array_map(function ($extensionProvider) {
      return call_user_func($extensionProvider, 'build');
    }, $this->extensionProviders);
  }

  /**
   * Ask the given extension's name provider for a new extension
   *
   * @param string $extension  Name of the extension to create
   * @return RpcX\ExtensionInterface
   */
  final protected function getExtension($extension) {
    if (!array_key_exists($extension, $this->extensionProviders)) {
      throw new \Exception(__CLASS__ . ": unregistered extension '{$extension}'");
    }
    return call_user_func($this->extensionProviders[$extension], 'build');
  }

  /**
   * Ask an extension provider to accept a configuration value
   *
   * @param string $extension  Name of the extension to configure
   * @param string $name  Configuration value name
   * @param mixed $value  Configuration value proper
   * @return boolean
   */
  final protected function configExtension($extension, $name, $value) {
    if (!array_key_exists($extension, $this->extensionProviders)) {
      throw new \Exception(__CLASS__ . ": unregistered extension '{$extension}'");
    }
    return call_user_func($this->extensionProviders[$extension], 'config', $name, $value);
  }

  /**
   * Ask all the extension providers to reset their configurations
   *
   * @return array
   */
  final protected function resetExtensions() {
    return array_map(function ($extensionProvider) {
      return call_user_func($extensionProvider, 'reset');
    }, $this->extensionProviders);
  }

  /**
   * Ask an extension provider to reset its configuration
   *
   * @param string $extension  Name of the extension to reset
   * @return boolean
   */
  final protected function resetExtension($extension) {
    if (!array_key_exists($extension, $this->extensionProviders)) {
      throw new \Exception(__CLASS__ . ": unregistered extension '{$extension}'");
    }
    return call_user_func($this->extensionProviders[$extension], 'reset');
  }

  /**
   * Run the given hook through the given extensions for the given argument
   *
   * @param array $extensions  Extension to run the hook through
   * @param string $hook  Hook name to run
   * @param string|\stdClass $arg  Argument to feed the hooks
   * @return string|\stdClass
   * @throws \Exception
   */
  final protected static function runHook(array $extensions, $hook, $arg) {
    $hookPriority = "{$hook}Priority";
    if (!method_exists('Json\\RpcX\\ExtensionInterface', $hook) || !method_exists('Json\\RpcX\\ExtensionInterface', $hookPriority)) {
      throw new \Exception(__CLASS__ . ": unknown hook name '{$hook}'");
    }

    usort($extensions, function ($a, $b) use ($hookPriority) {
      $aPriority = call_user_func([$a, $hookPriority]);
      $bPriority = call_user_func([$b, $hookPriority]);
      switch (true) {
        case $aPriority < $bPriority:
          return -1;
        case $aPriority == $bPriority:
          return 0;
        case $aPriority > $bPriority:
          return 1;
      }
    });

    $return = $arg;
    foreach ($extensions as $extension) {
      $return = call_user_func([$extension, $hook], $return);
    }

    return $return;
  }

}
