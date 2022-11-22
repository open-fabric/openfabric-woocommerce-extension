<section id="of-payment-method-section" class="of-payment-settings-section">
  <header class="of-payment-settings-section-header" style="margin-right: -1.5rem">
    <table>
      <?php echo $this->generate_title_html( $section_key, $section ); ?>
    </table>
  </header>

  <div class="of-payment-settings-section-content">
    <div id="<?php echo $section_key ?>-tabs">
      <ul>
        <?php foreach ($section['tabs'] as $tab => $label) { ?>
          <li><a href="#<?php echo $section_key; ?>-tabs-<?php echo $tab; ?>"><?php echo $label; ?></a></li>
        <?php } ?>
      </ul>
      <?php foreach ($section['tabs'] as $tab => $label) { ?>
        <div id="<?php echo $section_key; ?>-tabs-<?php echo $tab; ?>">
          <table style="width:100%">
            <?php
            foreach ($form_fields as $field_key => $field) {
              if ($field['section'] == $section_key && $field['tab'] == $tab) {
                echo $this->generate_settings_html(array($field_key => $field), false);
              }
            }
            ?>
          </table>

          <div class="of-payment-settings-section-inner" style="padding-top:1rem;padding-bottom:0.5rem;margin:0 -1.3rem">
            <?php $env = $tab == true ? 'yes' : 'no'; ?>
            <?php $timestamp = get_option( "{$this->id}_check_connection_timestamp_{$env}" ); ?>
            <?php if (!empty($timestamp)) { ?>
              <span class="of-green-check">Tested on <?php echo date('Y-m-d H:i:s', $timestamp) ?></span>
            <?php } ?>
          </div>

          <div class="of-payment-settings-section-inner of-payment-settings-section-footer" style="margin:0 -1.3rem">
            <div>
              <a href="">Test Connection</a>
            </div>

            <div>
              <button class="button-primary woocommerce-edit-button">Edit</button>
              <button class="button woocommerce-cancel-button">Cancel</button>
              <button class="button-primary woocommerce-save-button">Save</button>
            </div>
          </div>
        </div>
      <?php } ?>
    </div>
  </div>
</section>
