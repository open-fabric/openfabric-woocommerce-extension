<?php
if (!defined('ABSPATH') || class_exists('OF_Auth_API')) {
  return;
}

require_once(plugin_dir_path(__FILE__) . 'class-of-helpers.php');

class OF_Auth_API {
  private $endpoint;
  private $client_id;
  private $client_secret;

  private $cache_key;

  public function __construct($endpoint, $client_id, $client_secret) {
    $this->endpoint = $endpoint;
    $this->client_id = $client_id;
    $this->client_secret = $client_secret;

    $this->cache_key = join(':', array(
      $endpoint, $client_id, $client_secret,
    ));
  }

  public function get_access_token() {
    $token = get_transient( $this->cache_key );
    if ( $token ) {
      return $token;
    }

    $response = wp_remote_post($this->endpoint . '/oauth2/token', array(
      'headers' => array(
        'Authorization' => 'Basic ' . base64_encode($this->client_id . ':' . $this->client_secret),
      ),
      'body' => array(
        'grant_type' => 'client_credentials',
      )
    ));

    OF_Helpers::log($response, 'get_access_token.response');

    $httpStatusCode = $response['response']['code'];
    if ($httpStatusCode < 200 || $httpStatusCode > 299) {
      delete_transient( $this->cache_key );

      $errorMessage = json_decode($response['body']);
      return new WP_Error(
        'error', "{$errorMessage->error} ($httpStatusCode)"
      );
    }

    $response_body = wp_remote_retrieve_body($response);
    $response_data = json_decode($response_body, true);

    $token = $response_data['access_token'];
    $expiration = $response_data['expires_in'];
    set_transient( $this->cache_key, $token, $expiration - 60 );

    return $token;
  }
}
