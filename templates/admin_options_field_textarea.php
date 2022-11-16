<tr valign="top" data-field-key="<?php echo $field_key; ?>">
  <th scope="row" class="titledesc"></th>
  <td class="forminp">
    <fieldset>
      <legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
      <label style="display:block;margin:1rem 0 0.5rem;font-size:1rem" for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
      <textarea style="display:block;width:100%" class="input-textarea regular-input <?php echo esc_attr( $data['class'] ); ?>" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>" <?php disabled( $data['disabled'], true ); ?> <?php echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok. ?> ><?php echo esc_attr( $this->get_option( $key ) ); ?></textarea>
      <?php echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>
    </fieldset>
  </td>
</tr>
