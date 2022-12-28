<?php
defined('ABSPATH') or die();

/**
 * Interface for request decorators, used by API_Client to decorate
 * WP_Request with useful features. Another way to do this is to utilize
 * the `http_request_args` hook, however that would also affect other
 * requests.
 */
interface OF_Request_Decorator {

  /**
   * Decorator's entrypoint.
   *
   * @param WP_Http $request The original request.
   * @return WP_Http
   */
  public function apply($request);
}
