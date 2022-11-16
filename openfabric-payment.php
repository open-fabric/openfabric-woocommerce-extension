<?php
/**
 * Plugin Name: ACME Payment
 * Plugin URI: https://acme.co
 * Description: ACME payment method plugin for WooCommerce
 * Version: 0.1.0
 * Author: ACME
 * Author URI: https://acme.co
 * Text Domain: openfabric
 * Domain Path: /languages
 *
 * License: Apache-2.0
 * License URI: https://www.apache.org/licenses/LICENSE-2.0
 */
defined('ABSPATH') or die();

add_action('plugins_loaded', function() {
  require_once( plugin_dir_path(__FILE__) . 'includes/class-of-payment-method-base.php');

  class ACME_Payment_Method extends OF_Payment_Method_Base {

    public function __construct() {
      parent::__construct(__FILE__);
    }

    public function setup_properties() {
      parent::setup_properties();

      $this->id                 = 'acme';
      $this->title              = __( 'ACME', 'acme' );
      $this->method_title       = __( 'ACME', 'acme' );
      $this->method_description = __( 'Allow customers to pay with ACME', 'acme' );
    }
  }

  add_filter('woocommerce_payment_gateways', [ACME_Payment_Method::class, 'register_payment_method']);
});

register_activation_hook(__FILE__, function() {
  set_transient("${__FILE__}_activated", true, 30);
});
