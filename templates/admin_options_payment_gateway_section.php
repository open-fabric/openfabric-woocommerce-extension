<section id="of-payment-gateway-section" class="of-payment-settings-section">
  <header class="of-payment-settings-section-header" style="margin-right: -1.5rem">
    <div style="padding-right: 1rem">
      <table>
        <?php echo $this->generate_title_html( $section_key, $section ); ?>
      </table>
    </div>
  </header>

  <div class="of-payment-settings-section-content">
    <div class="of-payment-settings-section-inner" style="padding-bottom:0">
      <table>
        <?php
        foreach ($form_fields as $field_key => $field) {
          if ($field['section'] == $section_key && !isset($field['tab'])) {
            echo $this->generate_settings_html(array($field_key => $field), false);
          }
        }
        ?>
      </table>
    </div>

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
              if ($field['section'] == $section_key && isset($field['tab']) && $field['tab'] == $tab) {
                echo $this->generate_settings_html(array($field_key => $field), false);
              }
            }
            ?>
          </table>
        </div>
      <?php } ?>
    </div>

    <div class="of-payment-settings-section-inner of-payment-settings-section-footer">
      <div>
        <!-- <a href="">Test Connection</a> -->
      </div>

      <div>
        <button class="button-primary woocommerce-edit-button">Edit</button>
        <button class="button woocommerce-cancel-button">Cancel</button>
        <button class="button-primary woocommerce-save-button">Save</button>
      </div>
    </div>
  </div>
</section>
