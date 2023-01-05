<?php
defined('ABSPATH') or die();

if (!class_exists('OF_Payment_Method_Base')) {
  /**
   * Base class for all payment methods plugin to based on.
   *
   * This class actually do not implements the {@code WC_Payment_Gateway}, what
   * it does is to build a map of delegates from plugin file to the actual
   * class, and store them as delegates. The reason is that this base plugin
   * file might be included with multiple plugins, which will cause class name
   * clashing issue.
   *
   * We can not relies on {@code class_exists} and try to only define each class
   * once, as we do want each plugins to retain vendor-specific changes. {@code
   * class_exists} also will not work as the older version might get loaded
   * first, thus break plugins that relies on any of the newer version
   * functions.
   */
  class OF_Payment_Method_Base extends WC_Payment_Gateway {
    public $plugin_file;
    private $impl;

    public function __construct( $plugin_file ) {
      $this->plugin_file = $plugin_file;

      $base_dir = dirname( $plugin_file );
      $this->impl = require( $base_dir . '/includes/class-of-payment-method-impl.php' );

      $this->setup_properties();
      $this->impl->initialize( $this );
    }

    /**
     * delegates callbacks required by WooCommerce to the implementation
     * these can not be done with the {@code __call} magic method, since
     * they are also defined by the base {@code WC_Payment_Gateway} class
     */
    public function setup_properties() {
      $this->impl->setup_properties();
    }

    public function is_available() {
      return $this->impl->is_available();
    }

    public function admin_options() {
      return $this->impl->admin_options();
    }

    public function process_admin_options() {
      return $this->impl->process_admin_options();
    }

    public function process_payment( $order_id ) {
      return $this->impl->process_payment( $order_id );
    }

    public function process_refund( $order_id, $amount = null, $reason = '' ) {
      return $this->impl->process_refund( $order_id, $amount, $reason );
    }

    public function process_order( $order ) {
      return $this->impl->process_order( $order );
    }

    public function process_order_item( $item_id, $item, $order ) {
      return $this->impl->process_order_item( $item_id, $item, $order );
    }

    static function register_payment_method( $payment_methods ) {
      $payment_methods[] = get_called_class();
      return $payment_methods;
    }
  }
}
