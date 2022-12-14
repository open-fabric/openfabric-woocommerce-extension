<?php
if (!defined('ABSPATH') || class_exists('OF_Merchant_API')) {
  return;
}

require_once(plugin_dir_path(__FILE__) . 'class-of-helpers.php');

class OF_Merchant_API {
  private $endpoint;
  private $auth_api;
  private $decorators;

  public function __construct($endpoint, $auth_api, $decorators) {
    $this->endpoint = $endpoint;
    $this->auth_api = $auth_api;
    $this->decorators = $decorators;
  }

  public function create_transaction($order) {
    $access_token = $this->auth_api->get_access_token();
    if (is_wp_error($access_token)) {
      return $access_token;
    }

    $txn_trace_id = OF_Helpers::gen_uuid();
    $response = wp_remote_post(
      "{$this->endpoint}/v1/tenants/partners/transactions?txn_trace_id=$txn_trace_id",
      OF_Helpers::apply_decorators(array(
        'headers' => array(
          'Content-Type' => 'application/json',
          'Idempotency-Key' => $order['partner_reference_id'],
          'Authorization' => "Bearer {$access_token}",
        ),
        'body' => json_encode($order),
        'method' => 'POST',
        'data_format' => 'body',
        'timeout' => 60,
      ), $this->decorators)
    );

    OF_Helpers::log($response, 'create_transaction.response');

    $httpStatusCode = $response['response']['code'];
    $body = json_decode($response['body']);
    if ($httpStatusCode < 200 || $httpStatusCode > 299) {
      return new WP_Error(
        'error', "Error processing checkout. Please try again. ({$body->error_code} {$body->details[0]->message})"
      );
    }

    return array(
      'txn_id' => $body->id,
      'payment_redirect_web_url' => $body->payment_redirect_web_url
    );
  }

  public function is_transaction_approved($id) {
    $url = "{$this->endpoint}/v1/tenants/partners/transactions/{$id}";

    $response = wp_remote_get($url, OF_Helpers::apply_decorators(array(
      'headers' => array(
        'Content-Type' => 'application/json',
        'Authorization' => "Bearer {$this->auth_api->get_access_token()}",
      ),
      'timeout' => 60,
    ), $this->decorators));

    OF_Helpers::log($response, 'is_transaction_approved.response');

    $httpStatusCode = $response['response']['code'];
    $body = json_decode($response['body']);

    if ($httpStatusCode < 200 || $httpStatusCode > 299) {
      $errorMessage = $body->details[0]->message ?? $body->Message;
      return array('error' => 'Error processing checkout. Please try again. ({$body->error_code} {$errorMessage})');
    }

    $response_body = wp_remote_retrieve_body($response);
    $response_data = json_decode($response_body, true);
    return strcasecmp($response_data['status'], 'approved') == 0;
  }

  public function configure($webhook_url, $webhook_auth_header, $webhook_auth_value, $pg_name, $credentials) {
    $payload = array(
      'webhook' => array(
        'type' => 'webhook',
        'subscribed_events' => array('*'),
        'config' => array(
          'url' => $webhook_url,
          'authConfig' => array(
            'header' => $webhook_auth_header,
            'value' => $webhook_auth_value
          )
        )
      ),
      'payment_gateway' => array(
        'pg_name' => $pg_name,
        'credential_format' => 'api_key_pair',
        'credentials' => array(
          'api_key_pair' => $credentials,
        ),
      )
    );

    $url = "{$this->endpoint}/v1/tenants/partners/config";
    $response = wp_remote_request($url, OF_Helpers::apply_decorators(array(
      'headers' => array(
        'Content-Type' => 'application/json',
        'Authorization' => "Bearer {$this->auth_api->get_access_token()}",
      ),
      'body' => json_encode($payload),
      'method' => 'PUT',
      'data_format' => 'body',
      'timeout' => 60,
    ), $this->decorators));

    OF_Helpers::log($response, 'webhook_setup.response');

    $httpStatusCode = $response['response']['code'];
    if ($httpStatusCode < 200 || $httpStatusCode > 299) {
      $errorMessage = json_decode($response['body']);
      return new WP_Error(
        'error', "{$errorMessage->message} ($httpStatusCode)"
      );
    }

    return json_decode($response['body']);
  }

  public function refund($order_key, $txn_id, $amount, $reason) {
    $body = array(
      'amount' => $amount,
      'reason' => $reason
    );

    $url = "{$this->endpoint}/v1/tenants/partners/transactions/{$txn_id}/refunds";
    $response = wp_remote_request($url, OF_Helpers::apply_decorators(array(
      'headers' => array(
        'Content-Type' => 'application/json',
        'Authorization' => "Bearer {$this->auth_api->get_access_token()}",
        'Idempotency-Key' => $order_key,
      ),
      'body' => json_encode($body),
      'method' => 'POST',
      'data_format' => 'body',
      'timeout' => 60,
    ), $this->decorators));

    OF_Helpers::log( $response, 'refund.response' );

    $httpStatusCode = $response['response']['code'];
    if ($httpStatusCode < 200 || $httpStatusCode > 299) {
      $errorMessage = json_decode($response['body']);
      return array("error" => "{$errorMessage->message} ($httpStatusCode)");
    }

    $response_body = wp_remote_retrieve_body($response);
    $response_data = json_decode($response_body, true);
    return $response_data;
  }
}
