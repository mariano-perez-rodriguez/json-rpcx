<?php

/**
 * ExtensionInterface.php  Json RPC-X interface for extension implementations
 *
 * Interface to be implemented by every Json RPC-X extension.
 *
 */

namespace Json\RpcX;

/**
 * Interface to be implemented by every Json RPC-X extension
 *
 * This interface defines the functionality a Json RPC-X extension must expose
 * in order ton interact with the Json RPC-X core.
 *
 */
interface ExtensionInterface {

  /**
   * Return the extension's name
   *
   * @return string
   */
  function name();

  //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  /**
   * Return the extension's metadata collected from the request/response cycle
   *
   * @return mixed
   */
  function getMetadata();

  //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  /**
   * Process an exception 'rpc.x.<extension>.**' command
   *
   * @param string $method
   * @param array $params
   * @return mixed
   * @throws Exception
   */
  function command($method, array $params = []);

  //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  /**
   * Execute the postDecodeRequest hook
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
  function postDecodeRequest(\stdClass $request);

  /**
   * Execute the preEncodeRequest hook
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
  function preEncodeRequest(\stdClass $request);

  /**
   * Execute the postDecodeResponse hook
   *
   * This method is fed the decoded response object and it must return the
   * "new" response object to consider.
   *
   * Note that, after all postDecodeResponse hooks are executed, the resulting
   * response object must validate against the standard Json RPC format;
   * whith this in mind, this method should remove "consumed" fields from
   * the response.
   *
   * @param \stdClass $response  Response object to act upon
   * @return \stdClass
   */
  function postDecodeResponse(\stdClass $response);

  /**
   * Execute the preEncodeResponse hook
   *
   * This method is fed the unencoded response object and it must return
   * the "new" response object to consider.
   *
   * Note that, after all the preEncodeResponse hooks are executed, the resulting
   * response object must validate against the client's expectations;
   * with this in mind, this method should add "additional" fields to the
   * response.
   *
   * @param \stdClass $response  Response object to act upon
   * @return \stdClass
   */
  function preEncodeResponse(\stdClass $response);

  //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  /**
   * Return the postDecodeRequest hook priority for this instance
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  function postDecodeRequestPriority();

  /**
   * Return the preEncodeRequest hook priority for this instance
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  function preEncodeRequestPriority();

  /**
   * Return the preEncodeResponse hook priority for this instance
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  function preEncodeResponsePriority();

  /**
   * Return the postDecodeResponse hook priority for this instance
   *
   * This an instance method instead of a class or constant one in order to
   * allow for different priorities depending dynamically on the request in
   * question.
   *
   * @return int
   */
  function postDecodeResponsePriority();
}
