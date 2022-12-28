<?php
if (!defined('ABSPATH') || class_exists('OF_WooCommerce_Admin_Integration')) {
  return;
}

class OF_WooCommerce_Admin_Integration {

  public function __construct() {
  }

  public function register_integrations() {
    add_filter('manage_edit-shop_order_columns', array($this, 'order_list_column_names'), 20);
    add_action('manage_shop_order_posts_custom_column', array($this, 'order_list_column_values'), 20, 2);
  }

  public function order_list_column_names($columns) {
    $custom_columns = array();

    foreach($columns as $key => $column) {
      $custom_columns[$key] = $column;

      // Add custom columns after "Status" column
      if ($key == 'order_status') {
        $custom_columns['wc_order_key'] = __( 'Order ID', 'openfabric');
        $custom_columns['of_txn_id'] = __( 'Transaction ID', 'openfabric');
      }
    }

    return $custom_columns;
  }

  public function order_list_column_values($column, $order_id) {
    if ($column == 'wc_order_key') {
      $order = new WC_Order($order_id);
      echo $order->get_order_key();
      return;
    }

    if ($column == 'of_txn_id') {
      $order = new WC_Order($order_id);
      echo $order->get_meta('txn_id');
      return;
    }
  }
}
