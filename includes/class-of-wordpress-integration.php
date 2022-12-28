<?php
if (!defined('ABSPATH') || class_exists('OF_Wordpress_Integration')) {
  return;
}

class OF_Wordpress_Integration {
  protected $plugin_id;
  protected $plugin_file;

  public function __construct($plugin_id, $plugin_file) {
    $this->plugin_id = $plugin_id;
    $this->plugin_file = $plugin_file;
  }

  /**
   * Register various hooks and actions to integrate with Wordpress.
   * For the specific purpose of each actions, please refer to the
   * individual methods' doc.
   */
  public function register() {
    $basename = plugin_basename($this->plugin_file);

    add_action('admin_notices', array($this, 'activation_notice'));
    add_filter("plugin_action_links_{$basename}", array($this, 'action_links'));
  }

  /**
   * Add "Settings" link to plugin action page.
   */
  public function action_links( $actions ) {
    return array_merge(array(
      'settings' => sprintf(
        '<a href="%s">%s</a>',
        $this->settings_page_url(),
        __( 'Settings', 'openfabric'),
      ),
    ), $actions);
  }

  /**
   * Generate the URL to the settings page of the plugin.
   */
  public function settings_page_url() {
    return admin_url(
      add_query_arg(
        array(
          'page' => 'wc-settings',
          'tab' => 'checkout',
          'section' => $this->plugin_id,
        ),
        'admin.php'
      )
    );
  }

  /**
   * Display notice message in WP Admin after the plugin is activated.
   */
  public function activation_notice() {
    $key = "{$this->plugin_file}_activated";

    if ( get_transient( $key ) ) {
?>
  <div class="updated notice is-dismissible">
    <p>Thank you for using this plugin! Please continue setting up from the
      <a href="<?php echo $this->settings_page_url() ?>">Settings</a> page.</p>
  </div>
<?php
      delete_transient( $key );
    }
  }
}
