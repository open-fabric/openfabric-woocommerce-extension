<tr valign="top">
  <th scope="row" class="titledesc"></th>
  <td class="forminp">
    <fieldset>
      <legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
      <label style="font-size: 1rem;display:flex;align-items:center" for="<?php echo esc_attr( $field_key ); ?>">
        <?php echo wp_kses_post( $data['labels'][false] ); ?>
        <span class="of-toggle" style="margin:0 0.5rem">
          <input <?php disabled( $data['disabled'], true ); ?> class="<?php echo esc_attr( $data['class'] ); ?>" type="checkbox" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="1" <?php checked( $this->get_option( $key ), 'yes' ); ?> <?php echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok. ?> />
          <span class="of-toggle-switch"></span>
        </span>
        <?php echo wp_kses_post( $data['labels'][true] ); ?>
      </label>
      <br/>
      <?php echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>
    </fieldset>
  </td>
</tr>
