<section class="of-payment-settings-section">
  <header class="of-payment-settings-section-header">
    <table>
      <?php echo $this->generate_title_html( $section_key, $section ); ?>
    </table>
  </header>

  <div class="of-payment-settings-section-content of-payment-settings-section-inner">
    <table style="width:100%">
      <?php
      foreach ($form_fields as $field_key => $field) {
        if ($field['section'] == $section_key) {
          echo $this->generate_settings_html(array($field_key => $field), false);
        }
      }
      ?>
    </table>
  </div>
</section>
