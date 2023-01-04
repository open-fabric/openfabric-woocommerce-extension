<?php
require_once(plugin_dir_path(__FILE__) . 'class-of-wordpress-integration.php');
require_once(plugin_dir_path(__FILE__) . 'class-of-woocommerce-integration.php');
require_once(plugin_dir_path(__FILE__) . 'class-of-auth-api.php');
require_once(plugin_dir_path(__FILE__) . 'class-of-merchant-api.php');
require_once(plugin_dir_path(__FILE__) . 'class-of-instrumentation-support.php');
require_once(plugin_dir_path(__FILE__) . 'class-of-helpers.php');


return new class extends WC_Payment_Gateway {
  private $gateway;

  protected $auth_endpoint;
  protected $merchant_endpoint;
  protected $decorators = array();

  protected $admin_scripts = array();
  protected $admin_styles = array();

  protected $payment_gateways = array(
    'xendit' => 'Xendit',
    'stripe' => 'Stripe',
    'paymaya' => 'PayMaya',
    'paymongo' => 'PayMongo',
    /*     'checkout' => 'Checkout.com', */
    /*     'adyen' => 'Adyen', */
  );

  protected $auth_endpoints = array(
    /*     'sandbox' => 'https://auth.dev.openfabric.co', */
    'sandbox' => 'https://auth.sandbox.openfabric.co',
    'production' => 'https://auth.openfabric.co',
  );

  protected $merchant_endpoints = array(
    /*     'sandbox' => 'https://api.dev.openfabric.co', */
    'sandbox' => 'https://api.sandbox.openfabric.co',
    'production' => 'https://api.openfabric.co',
  );

  protected $wp_integration;
  protected $wc_admin_integration;

  public function initialize( $gateway ) {
    $this->gateway = $gateway;
    $this->id = $this->gateway->id;

    $this->init_form_fields();
    $this->init_settings();

    $this->title = $this->get_option( 'title' );
    $this->description = $this->get_option( 'description' );

    $this->supports = array(
      'products',
      'refunds',
    );

    $env = $this->get_option( 'is_live' ) == 'yes' ? 'production' : 'sandbox';
    $this->auth_endpoint = $this->auth_endpoints[$env];
    $this->merchant_endpoint = $this->merchant_endpoints[$env];

    add_action(
      sprintf('woocommerce_update_options_payment_gateways_%s', $this->gateway->id),
      array($this, 'process_admin_options')
    );

    $this->admin_scripts[$this->gateway->id . '_admin'] = 'admin.js';
    $this->admin_styles[$this->gateway->id . '_admin'] = 'admin.css';
    add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

    add_action("woocommerce_api_{$this->gateway->id}_payment_failed", array($this, 'handle_payment_failed'));
    add_action("woocommerce_api_{$this->gateway->id}_payment_success", array($this, 'handle_payment_success'));
    add_action("woocommerce_api_{$this->gateway->id}_merchant_webhook", array($this, 'handle_merchant_webhook'));

    $plugin_data = get_plugin_data($this->gateway->plugin_file);
    $this->decorators = array(
      new OF_Instrumentation_Support($plugin_data['Version']),
    );

    $this->wp_integration = new OF_Wordpress_Integration(
      $this->gateway->id, $this->gateway->plugin_file
    );
    $this->wp_integration->register();

    $this->wc_admin_integration = new OF_WooCommerce_Admin_Integration();
    $this->wc_admin_integration->register_integrations();
  }

  function handle_merchant_webhook() {
    $payload = json_decode(file_get_contents('php://input'), true);
    OF_Helpers::log($payload, 'webhook_update');

    $webhook_auth_header = get_option( 'webhook_auth_header' );
    $webhook_auth_value = get_option( 'webhook_auth_value' );

    $all_headers = getallheaders();
    $auth_value = array_change_key_case($all_headers, CASE_LOWER)[strtolower($webhook_auth_header)];

    if (strcasecmp($auth_value, $webhook_auth_value) != 0) {
      error_log(print_r("Invalid webhook", TRUE));
      return;
    }

    $txn_ref_id = array_change_key_case($payload['data'], CASE_LOWER)['txn_ref_id'];
    $txn_state = array_change_key_case($payload['data'], CASE_LOWER)['txn_state'];
    $txn_pg_charge_id = array_change_key_case($payload['data'], CASE_LOWER)['txn_pg_charge_id'];
    $order_id = wc_get_order_id_by_order_key($txn_ref_id);

    $order = new WC_Order($order_id);
    $order->update_meta_data('txn_pg_charge_id', $txn_pg_charge_id);
    $order->add_meta_data('webhook_update', time());
    $order->save_meta_data();

    if (strcasecmp($txn_state, 'approved') == 0) {
      $order->update_status('completed');
    } else if (strcasecmp($txn_state, 'failed') == 0) {
      $order->update_status('failed');
    } else {
      $order->update_status('on-hold');
    }
  }

  /**
   * This method updates the order status and metadata based on the parameters
   * we get when redirected back from the payment method's website - in the case
   * of success/approved.
   */
  function handle_payment_failed() {
    OF_Helpers::log($_GET, 'webhook_update.failed');

    $order = new WC_Order($_GET['order_id']);
    $order->update_status('failed', __('Payment has been cancelled.', 'openfabric'));
    wp_redirect($this->get_return_url($order));
  }

  /**
   * This method updates the order status and metadata based on the parameters
   * we get when redirected back from the payment method's website - in the case
   * of failure/declined.
   */
  function handle_payment_success() {
    OF_Helpers::log($_GET, 'webhook_update.success');

    $order = new WC_Order($_GET['order_id']);
    $webhook_update = $order->get_meta('webhook_update');
    if (!empty($webhook_update)) {
      return wp_redirect($this->get_return_url($order));
    }

    $txn_pg_charge_id = $_GET['txn_pg_charge_id'] ?? null;
    $txn_id = $_GET['txn_id'] ?? null;
    // $txn_trace_id = $_GET['txn_trace_id'] ?? null;

    $order->add_meta_data('txn_pg_charge_id', $txn_pg_charge_id);
    $order->save_meta_data();

    $client_credentials = $this->get_client_credentials();
    $auth_API = new OF_Auth_API(
      $this->auth_endpoint,
      $client_credentials['client_id'],
      $client_credentials['client_secret']
    );

    // Transaction check
    $merchant_API = new OF_Merchant_API(
      $this->merchant_endpoint,
      $auth_API,
      $this->decorators
    );
    $is_approved = $merchant_API->is_transaction_approved($txn_id);

    if (isset($is_approved['error'])) {
      wc_add_notice($is_approved['error'], 'error');
      $order->update_status('failed', __('Payment has been cancelled.', 'openfabric'));
    } else if ($is_approved) {
      $order->update_status('completed');
    }
    wp_redirect($this->get_return_url($order));
  }

  public function setup_properties() {
    // This specifies whether this payment method requires any additional fields
    // on the checkout page, and does not affect admin fields.
    $this->has_fields = false;
  }

  public function needs_setup() {
    return true;
  }

  /**
   * Determine whether the plugin has been fully setup and is available for use.
   */
  public function is_available() {
    $client_credentials = $this->get_client_credentials();
    $pg_credentials = $this->get_pg_credentials();

    return !empty($client_credentials)
        && !empty($client_credentials['client_id'])
        && !empty($client_credentials['client_secret'])
        && !empty($pg_credentials['public_api_key'])
        && !empty($pg_credentials['private_api_key'])
        && get_transient("{$this->gateway->id}_tenant_id");
  }

  public function init_form_fields() {
    $this->form_fields = array_merge(
      $this->general_setting_fields(),
      $this->transaction_mode_fields(),
      $this->payment_method_setting_fields(),
      $this->payment_gateway_setting_fields(),
    );
  }

  public function general_setting_fields() {
    return array(
      'enabled' => array(
        'title' => sprintf(
          __('Enable %s on Checkout', 'openfabric'),
          $this->gateway->method_title
        ),
        'description' => sprintf(
          __('When enabled, %s will appear on checkout.', 'openfabric'),
          $this->gateway->method_title
        ),
        'type' => 'checkbox',
        'default' => 'yes',
        'section' => 'general',
      ),
      'display_settings' => array(
        'title' => __('Display Settings', 'openfabric'),
        'description' => __('Enter payment method details that will be displayed at checkout and in order notes.', 'openfabric'),
        'type' => 'subsection',
        'section' => 'general',
      ),
      'title' => array(
        'title' => __('Name', 'openfabric'),
        'description' => __('Title the customer sees during checkout and order notes.', 'openfabric'),
        'type' => 'text',
        'section' => 'general',
      ),
      'description' => array(
        'title' => __('Description', 'openfabric'),
        'description' => __('Description the customer sees during checkout and order notes.', 'openfabric'),
        'type' => 'textarea',
        'section' => 'general',
        'custom_attributes' => array(
          'rows' => 5
        )
      ),
    );
  }

  public function transaction_mode_fields() {
    return array(
      'is_live' => array(
        'title' => __('Environment', 'openfabric'),
        'description' => sprintf(
          __(
            'When mode = Test, we will use Test %s and Payment Gateway credentials to run a transaction. Use this mode to run test transactions.<br />When mode = Live, we will use your Live %s and Payment Gateway credentials to run a transaction.',
            'openfabric'
          ),
          $this->gateway->title, $this->gateway->title
        ),
        'type' => 'checkbox',
        'style' => 'toggle',
        'labels' => array(
          false => 'Test',
          true => 'Live',
        ),
        'default' => 'no',
        'section' => 'transaction_mode',
      ),
    );
  }

  public function payment_method_setting_fields() {
    return array(
      'test_client_id' => array(
        'title' => __('Client ID', 'openfabric'),
        'description' => __(
          'alphanumeric values only. No space or special characters.',
          'openfabric'
        ),
        'type' => 'text',
        'section' => 'payment_method',
        'tab' => false
      ),
      'test_client_secret' => array(
        'title' => __('Client Secret', 'openfabric'),
        'description' => __(
          'alphanumeric values only. No space or special characters.',
          'openfabric'
        ),
        'type' => 'text',
        'section' => 'payment_method',
        'tab' => false
      ),
      'live_client_id' => array(
        'title' => __('Client ID', 'openfabric'),
        'description' => __(
          'alphanumeric values only. No space or special characters.',
          'openfabric'
        ),
        'type' => 'text',
        'section' => 'payment_method',
        'tab' => true
      ),
      'live_client_secret' => array(
        'title' => __('Client Secret', 'openfabric'),
        'description' => __(
          'alphanumeric values only. No space or special characters.',
          'openfabric'
        ),
        'type' => 'text',
        'section' => 'payment_method',
        'tab' => true
      ),
    );
  }

  public function payment_gateway_setting_fields() {
    return array(
      'payment_gateway' => array(
        'title' => __('Payment Gateway', 'openfabric'),
        'type' => 'select',
        'options' => $this->payment_gateways,
        'section' => 'payment_gateway',
        'default' => 'stripe',
      ),
      'notice' => array(
        'title' => sprintf(
          __('Ensure that you have enabled test mode on your %s account and provide test keys', 'openfabric'),
          $this->payment_gateways[ $this->get_option( 'payment_gateway' ) ]
        ),
        'type' => 'notice',
        'section' => 'payment_gateway',
      ),
      'stripe_live_public_api_key' => array(
        'title' => __('Stripe Live Publishable Key', 'openfabric'),
        'description' => __('Stripe Live Publishable key', 'openfabric'),
        'type' => 'textarea',
        'payment_gateway' => 'stripe',
        'section' => 'payment_gateway',
        'tab' => true
      ),
      'stripe_live_private_api_key' => array(
        'title' => __('Stripe Live Secret Key', 'openfabric'),
        'description' => __('Stripe Live Secret Key', 'openfabric'),
        'type' => 'textarea',
        'payment_gateway' => 'stripe',
        'section' => 'payment_gateway',
        'tab' => true
      ),
      'stripe_test_public_api_key' => array(
        'title' => __('Stripe Test Publishable Key', 'openfabric'),
        'description' => __('Stripe Test Publishable key', 'openfabric'),
        'type' => 'textarea',
        'payment_gateway' => 'stripe',
        'section' => 'payment_gateway',
        'tab' => false
      ),
      'stripe_test_private_api_key' => array(
        'title' => __('Stripe Test Private Key', 'openfabric'),
        'description' => __('Stripe Test Private Key', 'openfabric'),
        'type' => 'textarea',
        'payment_gateway' => 'stripe',
        'section' => 'payment_gateway',
        'tab' => false
      ),
      'xendit_live_public_api_key' => array(
        'title' => __('Xendit Live Public API key', 'openfabric'),
        'description' => __('Xendit Live Public API key', 'openfabric'),
        'type' => 'textarea',
        'payment_gateway' => 'xendit',
        'section' => 'payment_gateway',
        'tab' => true
      ),
      'xendit_live_private_api_key' => array(
        'title' => __('Xendit Live Private API key', 'openfabric'),
        'description' => __('Xendit Live Private API key', 'openfabric'),
        'type' => 'textarea',
        'payment_gateway' => 'xendit',
        'section' => 'payment_gateway',
        'tab' => true
      ),
      'xendit_test_public_api_key' => array(
        'title' => __('Xendit Test Public API key', 'openfabric'),
        'description' => __('Xendit Test Public API key', 'openfabric'),
        'type' => 'textarea',
        'payment_gateway' => 'xendit',
        'section' => 'payment_gateway',
        'tab' => false
      ),
      'xendit_test_private_api_key' => array(
        'title' => __('Xendit Test Private API key', 'openfabric'),
        'description' => __('Xendit Test Private API key', 'openfabric'),
        'type' => 'textarea',
        'payment_gateway' => 'xendit',
        'section' => 'payment_gateway',
        'tab' => false
      ),
      'paymaya_live_public_api_key' => array(
        'title' => __('PayMaya Live Public API key', 'openfabric'),
        'description' => __('PayMaya Live Public API key', 'openfabric'),
        'type' => 'textarea',
        'payment_gateway' => 'paymaya',
        'section' => 'payment_gateway',
        'tab' => true
      ),
      'paymaya_live_private_api_key' => array(
        'title' => __('PayMaya Live Private API key', 'openfabric'),
        'description' => __('PayMaya Live Private API key', 'openfabric'),
        'type' => 'textarea',
        'payment_gateway' => 'paymaya',
        'section' => 'payment_gateway',
        'tab' => true
      ),
      'paymaya_test_public_api_key' => array(
        'title' => __('PayMaya Test Public API key', 'openfabric'),
        'description' => __('PayMaya Test Public API key', 'openfabric'),
        'type' => 'textarea',
        'payment_gateway' => 'paymaya',
        'section' => 'payment_gateway',
        'tab' => false
      ),
      'paymaya_test_private_api_key' => array(
        'title' => __('PayMaya Test Private API key', 'openfabric'),
        'description' => __('PayMaya Test Private API key', 'openfabric'),
        'type' => 'textarea',
        'payment_gateway' => 'paymaya',
        'section' => 'payment_gateway',
        'tab' => false
      ),
      'paymongo_live_public_api_key' => array(
        'title' => __('PayMongo Live Public API key', 'openfabric'),
        'description' => __('PayMongo Live Public API key', 'openfabric'),
        'type' => 'textarea',
        'payment_gateway' => 'paymongo',
        'section' => 'payment_gateway',
        'tab' => true
      ),
      'paymongo_live_private_api_key' => array(
        'title' => __('PayMongo Live Private API key', 'openfabric'),
        'description' => __('PayMongo Live Private API key', 'openfabric'),
        'type' => 'textarea',
        'payment_gateway' => 'paymongo',
        'section' => 'payment_gateway',
        'tab' => true
      ),
      'paymongo_test_public_api_key' => array(
        'title' => __('PayMongo Test Public API key', 'openfabric'),
        'description' => __('PayMongo Test Public API key', 'openfabric'),
        'type' => 'textarea',
        'payment_gateway' => 'paymongo',
        'section' => 'payment_gateway',
        'tab' => false
      ),
      'paymongo_test_private_api_key' => array(
        'title' => __('PayMongo Test Private API key', 'openfabric'),
        'description' => __('PayMongo Test Private API key', 'openfabric'),
        'type' => 'textarea',
        'payment_gateway' => 'paymongo',
        'section' => 'payment_gateway',
        'tab' => false
      ),
    );
  }

  public function admin_enqueue_scripts() {
    $screen = get_current_screen();
    if (!$screen->in_admin() || strcmp($screen->id, 'woocommerce_page_wc-settings') != 0) {
      return;
    }

    wp_enqueue_script('jquery-ui-tabs');

    foreach ($this->admin_scripts as $key => $file) {
      wp_enqueue_script($key, plugin_dir_url(__FILE__) . $file, array());
    }

    foreach ($this->admin_styles as $key => $file) {
      wp_enqueue_style($key, plugin_dir_url(__FILE__) . $file);
    }
  }

  /**
   * Processes and saves options.
   * If there is an error thrown, will continue to save and validate fields, but will leave the erroring field out.
   *
   * @return bool was anything saved?
   */
  public function process_admin_options() {
    $saved = parent::process_admin_options();
    $data = $this->get_post_data();

    $env = $this->get_option( 'is_live', 'no' ) == 'yes' ? 'production' : 'sandbox';
    $client_credentials = $this->get_client_credentials();
    update_option("{$this->gateway->id}_check_connection_timestamp_{$this->get_option( 'is_live' )}", null);

    // Connectivity check
    $auth_api = new OF_Auth_API(
      $this->auth_endpoints[$env],
      $client_credentials['client_id'],
      $client_credentials['client_secret'],
    );
    $response = $auth_api->get_access_token();

    if ( is_wp_error( $response ) ) {
      $this->add_error(
        sprintf(
          __('We encountered an issue with connecting with %s. Please try again later.', 'openfabric'),
          $this->title
        )
      );
      $this->display_errors();
      return false;
    }

    // Webhook setup
    $merchant_API = new OF_Merchant_API(
      $this->merchant_endpoints[$env],
      $auth_api,
      $this->decorators
    );

    $current_url = get_site_url();
    $webhook_url = $current_url . "?wc-api={$this->gateway->id}_merchant_webhook";

    $webhook_auth_header = get_option( 'webhook_auth_header' ) || OF_Helpers::gen_uuid();
    $webhook_auth_value = get_option( 'webhook_auth_value' ) || OF_Helpers::gen_uuid();

    update_option('webhook_auth_header', $webhook_auth_header);
    update_option('webhook_auth_value', $webhook_auth_value);

    $pg_name = $this->get_option( 'payment_gateway' );
    $pg_credentials = $this->get_pg_credentials();

    $webhook_response = $merchant_API->configure($webhook_url, $webhook_auth_header, $webhook_auth_value, $pg_name, $pg_credentials);
    if ( is_wp_error( $webhook_response ) ) {
      OF_Helpers::log( $webhook_response, 'settings' );
      $this->add_error(
        __('We encountered an issue with connecting with Tenant . Please try again later.', 'openfabric')
      );
      $this->display_errors();
      return false;
    }

    set_transient("{$this->gateway->id}_tenant_id", $webhook_response->webhook->tenant_id);
    update_option("{$this->gateway->id}_check_connection_timestamp_{$this->get_option( 'is_live' )}", time());

    return $saved;
  }

  /**
   * Process Payment.
   *
   * Process the payment. Override this in your gateway. When implemented, this should.
   * return the success and redirect in an array. e.g:
   *
   *        return array(
   *            'result'   => 'success',
   *            'redirect' => $this->get_return_url( $order )
   *        );
   *
   * @param int $order_id Order ID.
   * @return array
   */
  function process_payment($order_id) {
    $order = new WC_Order($order_id);

    // Mark as on-hold (we're awaiting the cheque)
    $order->update_status('on-hold', __('Awaiting payment', 'woocommerce'));

    $client_credentials = $this->get_client_credentials();
    $auth_API = new OF_Auth_API(
      $this->auth_endpoint,
      $client_credentials['client_id'],
      $client_credentials['client_secret']
    );
    $merchant_API = new OF_Merchant_API(
      $this->merchant_endpoint,
      $auth_API,
      $this->decorators
    );

    $transaction_request = $this->process_order( $order );
    $gateway_redirect_response = $merchant_API->create_transaction( $transaction_request );
    if (is_wp_error($gateway_redirect_response)) {
      wc_add_notice($gateway_redirect_response->get_error_message('error'), 'error');
      return array(
        'result' => 'failure',
      );
    }

    $order->update_meta_data('txn_id', $gateway_redirect_response['txn_id']);
    $order->save_meta_data();

    do_action('woocommerce_set_cart_cookies', true);

    return array(
      'result' => 'success',
      'redirect' => $gateway_redirect_response['payment_redirect_web_url']
    );
  }

  /**
   * Process refund.
   *
   * If the gateway declares 'refunds' support, this will allow it to refund.
   * a passed in amount.
   *
   * @param int $order_id Order ID.
   * @param float|null $amount Refund amount.
   * @param string $reason Refund reason.
   * @return boolean True or false based on success, or a WP_Error object.
   */
  public function process_refund($order_id, $amount = null, $reason = '') {
    $client_credentials = $this->get_client_credentials();
    $auth_API = new OF_Auth_API(
      $this->auth_endpoint,
      $client_credentials['client_id'],
      $client_credentials['client_secret']
    );
    $merchant_API = new OF_Merchant_API(
      $this->merchant_endpoint,
      $auth_API,
      $this->decorators
    );

    $order = new WC_Order($order_id);
    $txn_id = $order->get_meta('txn_id');
    $result = $merchant_API->refund(
      $order->get_order_key(),
      $txn_id, $amount, $reason
    );

    $order->set_meta_data('txn_refund_id', $result['pg_refund_id']);
    $order->save_meta_data();

    return true;
  }

  public function admin_options() {
    $form_fields = $this->get_form_fields();

    $form_sections = array(
      'general' => array(
        'title' => __('General Settings', 'openfabric'),
        'description' => __(
          sprintf('Control presentment of %s on the Checkout flow.', $this->method_title),
          'openfabric'
        ),
        'type' => 'section'
      ),
      'transaction_mode' => array(
        'title' => __('Transaction Mode', 'openfabric'),
        'description' => __(
          '<p>Control how the transaction are executed.</p><p>Test = To run test transaction in test env<br/>Live = To run live transactions in prod env</p>',
          'openfabric'
        ),
        'type' => 'section',
      ),
      'payment_method' => array(
        'title' => __(
          sprintf('%s Account Settings', $this->method_title),
          'openfabric'
        ),
        'description' => __(
          sprintf(
            '<p>The plugin will use your %s account credentials to initiate transactions.</p><p>Provide your %s Account credentials.</p><p>Your account credentials are available @</p>',
            $this->title, $this->title, $this->title
          ),
          'openfabric',
        ),
        'type' => 'section',
        'tabs' => array(
          true => 'Live',
          false => 'Test',
        ),
        'tab' => $this->get_option( 'is_live' ),
      ),
      'payment_gateway' => array(
        'title' => __(
          'Payment Gateway Account Settings',
          'openfabric'
        ),
        'description' => __(
          '<p>Your payment gateway account details will be used to automatically charge and settle funds using the virtual card we generate for a transaction.</p><p>You may need to fetch these details from your payment gateway account.</p><p>You can provide your payment gateway account details for live/test and test connection.</p>',
          'openfabric',
        ),
        'type' => 'section',
        'tabs' => array(
          true => 'Live',
          false => 'Test',
        ),
        'tab' => $this->get_option( 'is_live' ),
      ),
    );

    foreach ($form_sections as $section_key => $section) {
      if (method_exists($this, 'generate_'.$section_key.'_section_html')) {
        echo $this->{'generate_'.$section_key.'_section_html'}($section_key, $section, $form_fields);
      } else {
        include(plugin_dir_path(__FILE__) . '../templates/admin_options_section.php');
      }
    }
  }

  public function process_order_item( $item_id, $item, $order ) {
    $product = $item->get_product();

    $categories = array();
    foreach ( wc_get_product_term_ids($item->get_id(), 'product_cat') as $cat_id ) {
      $categories[] = get_term_by( 'id', $cat_id, 'product_cat' )->name;
    }

    $line_item = array(
      'item_id' => $item->get_id(),
      'name' => $item->get_name(),
      'description' => $product->get_description(),
      'variation_name' => wc_get_formatted_variation($product, true)
                     || wc_get_formatted_variation($item, true)
                     || $item->get_name(),
      'quantity' => $item->get_quantity(),
      'original_price' => $product->get_regular_price(),
      'price' => $product->get_price(),
      'amount' => $item->get_total(),
    );

    if ($product->is_taxable()) {
      /* $line_item['tax_code'] = ; */
      $line_item['tax_amount_percent'] = round( $item->get_subtotal_tax() / $item->get_subtotal() ) * 100.0;
    }

    if ($product->is_on_sale()) {
      $line_item['discount_amount'] = (float)($product->get_regular_price() - $product->get_price());
    }

    return $line_item;
  }

  public function process_order( $order ) {
    $current_url = get_site_url();
    $merchant_result_url = $current_url . "?wc-api={$this->gateway->id}_payment_success&order_id={$order->get_id()}";
    $merchant_fail_url = $current_url . "?wc-api={$this->gateway->id}_payment_failed&order_id={$order->get_id()}";

    $merchant_reference_id = $order->get_order_key();
    $tax_amount_percent = round(($order->get_total_tax() / $order->get_total()) * 100);

    $items = array();
    foreach ($order->get_items() as $item_id => $item) {
      $items[] = $this->process_order_item( $item_id, $item, $order );
    }

    return array(
      "partner_reference_id" => $merchant_reference_id,
      "tenant_id" => get_transient("{$this->id}_tenant_id"),
      "partner_redirect_success_url" => $merchant_result_url,
      "partner_redirect_fail_url" => $merchant_fail_url,
      "pg_name" => $this->get_option( 'payment_gateway' ),
      "pg_flow" => "charge",
      "customer_info" => array(
        "mobile_number" => $order->get_billing_phone(),
        "first_name" => $order->get_billing_first_name(),
        "last_name" => $order->get_billing_last_name(),
        "email" => $order->get_billing_email(),
      ),
      "amount" => $order->get_total(),
      "currency" => $order->get_currency(),
      "status" => "Created",
      "transaction_details" => array(
        "shipping_address" => array(
          "country_code" => $order->get_shipping_country() ?: $order->get_billing_country(),
          "address_line_1" => $order->get_shipping_address_1() ?: $order->get_billing_address_1(),
          "post_code" => $order->get_shipping_postcode() ?: $order->get_billing_postcode(),
        ),
        "billing_address" => array(
          "country_code" => $order->get_billing_country(),
          "address_line_1" => $order->get_billing_address_1(),
          "post_code" => $order->get_billing_postcode(),
        ),
        "items" => $items,
        "tax_amount_percent" => $tax_amount_percent,
        "shipping_amount" => $order->get_shipping_total(),
        "original_amount" => $order->get_total(),
      ),
    );
  }

  public function generate_payment_method_section_html($section_key, $section, $form_fields) {
    ob_start();
    include(plugin_dir_path(__FILE__) . '../templates/admin_options_payment_method_section.php');
    return ob_get_clean();
  }

  public function generate_payment_gateway_section_html($section_key, $section, $form_fields) {
    ob_start();
    include(plugin_dir_path(__FILE__) . '../templates/admin_options_payment_gateway_section.php');
    return ob_get_clean();
  }

  public function generate_notice_html($key, $data) {
    $field_key = $this->get_field_key( $key );
    $defaults  = array(
      'title'             => '',
      'class'             => '',
      'css'               => '',
      'type'              => 'text',
      'description'       => '',
      'custom_attributes' => array(),
    );

    $data = wp_parse_args( $data, $defaults );
    ob_start();
    include(plugin_dir_path(__FILE__) . '../templates/admin_options_field_notice.php');
    return ob_get_clean();
  }

  public function generate_checkbox_html($key, $data) {
    if ( isset( $data['style'] ) && $data['style'] == 'toggle' ) {
      return $this->generate_toggle_html($key, $data);
    }

    $field_key = $this->get_field_key( $key );
    $defaults  = array(
      'title'             => '',
      'label'             => '',
      'disabled'          => false,
      'class'             => '',
      'css'               => '',
      'type'              => 'text',
      'desc_tip'          => false,
      'description'       => '',
      'custom_attributes' => array(),
    );

    $data = wp_parse_args( $data, $defaults );

    if ( ! $data['label'] ) {
      $data['label'] = $data['title'];
    }
    ob_start();
    include(plugin_dir_path(__FILE__) . '../templates/admin_options_field_checkbox.php');
    return ob_get_clean();
  }

  public function generate_text_html( $key, $data ) {
    $field_key = $this->get_field_key( $key );
    $defaults  = array(
      'title'             => '',
      'disabled'          => false,
      'class'             => '',
      'css'               => '',
      'placeholder'       => '',
      'type'              => 'text',
      'desc_tip'          => false,
      'description'       => '',
      'custom_attributes' => array(),
    );

    $data = wp_parse_args( $data, $defaults );

    ob_start();
    include(plugin_dir_path(__FILE__) . '../templates/admin_options_field_text.php');
    return ob_get_clean();
  }

  public function generate_textarea_html( $key, $data ) {
    $field_key = $this->get_field_key( $key );
    $defaults  = array(
      'title'             => '',
      'disabled'          => false,
      'class'             => '',
      'css'               => '',
      'placeholder'       => '',
      'type'              => 'text',
      'desc_tip'          => false,
      'description'       => '',
      'custom_attributes' => array(),
    );

    $data = wp_parse_args( $data, $defaults );

    ob_start();
    include(plugin_dir_path(__FILE__) . '../templates/admin_options_field_textarea.php');
    return ob_get_clean();
  }

  public function generate_select_html( $key, $data ) {
    $field_key = $this->get_field_key( $key );
    $defaults  = array(
      'title'             => '',
      'disabled'          => false,
      'class'             => '',
      'css'               => 'min-width: 20rem',
      'placeholder'       => '',
      'type'              => 'text',
      'desc_tip'          => false,
      'description'       => '',
      'custom_attributes' => array(),
      'options'           => array(),
    );

    $data = wp_parse_args( $data, $defaults );
    $value = $this->get_option( $key );

    ob_start();
    include(plugin_dir_path(__FILE__) . '../templates/admin_options_field_select.php');
    return ob_get_clean();
  }

  public function generate_subsection_html( $key, $data ) {
    $field_key = $this->get_field_key( $key );
    $defaults  = array(
      'title'             => '',
      'disabled'          => false,
      'class'             => '',
      'css'               => '',
      'description'       => '',
      'placeholder'       => '',
      'type'              => 'text',
      'desc_tip'          => false,
      'custom_attributes' => array(),
    );

    $data = wp_parse_args( $data, $defaults );

    ob_start();
    include(plugin_dir_path(__FILE__) . '../templates/admin_options_field_subsection.php');
    return ob_get_clean();
  }

  public function generate_toggle_html( $key, $data ) {
    $field_key = $this->get_field_key( $key );
    $defaults  = array(
      'title'             => '',
      'disabled'          => false,
      'class'             => '',
      'css'               => '',
      'placeholder'       => '',
      'type'              => 'checkbox',
      'desc_tip'          => false,
      'description'       => '',
      'custom_attributes' => array(),
    );

    $data = wp_parse_args( $data, $defaults );

    ob_start();
    include(plugin_dir_path(__FILE__) . '../templates/admin_options_field_toggle.php');
    return ob_get_clean();
  }

  private function get_pg_credentials() {
    $env = $this->get_option( 'is_live' ) == 'yes' ? 'live' : 'test';
    $pg_name = $this->get_option( 'payment_gateway' );
    return array(
      'public_api_key' => $this->get_option( "{$pg_name}_{$env}_public_api_key" ),
      'private_api_key' => $this->get_option( "{$pg_name}_{$env}_private_api_key" ),
    );
  }

  private function get_client_credentials() {
    $env = $this->get_option( 'is_live' ) == 'yes' ? 'live' : 'test';
    return array(
      'client_id' => $this->get_option( "{$env}_client_id" ),
      'client_secret' => $this->get_option( "{$env}_client_secret" ),
    );
  }
};
