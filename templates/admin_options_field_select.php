<?php
// Ref: https://woocommerce.github.io/code-reference/files/woocommerce-includes-abstracts-abstract-wc-settings-api.html#source-view.685
?>
<tr valign="top">
  <th scope="row" class="titledesc"></th>
  <td class="forminp">
    <fieldset>
      <legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
      <label style="display:block;margin:1rem 0 0.5rem;font-size:1rem" for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
      <select class="select <?php echo esc_attr( $data['class'] ); ?>" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" <?php disabled( $data['disabled'], true ); ?> <?php echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok. ?>>
        <?php foreach ( (array) $data['options'] as $option_key => $option_value ) : ?>
          <?php if ( is_array( $option_value ) ) : ?>
            <optgroup label="<?php echo esc_attr( $option_key ); ?>">
              <?php foreach ( $option_value as $option_key_inner => $option_value_inner ) : ?>
                <option value="<?php echo esc_attr( $option_key_inner ); ?>" <?php selected( (string) $option_key_inner, esc_attr( $value ) ); ?>><?php echo esc_html( $option_value_inner ); ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php else : ?>
            <option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( (string) $option_key, esc_attr( $value ) ); ?>><?php echo esc_html( $option_value ); ?></option>
          <?php endif; ?>
        <?php endforeach; ?>
      </select>
      <?php echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>
    </fieldset>
  </td>
</tr>
