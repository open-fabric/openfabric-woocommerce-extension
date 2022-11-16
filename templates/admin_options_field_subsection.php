<tr valign="top">
  <th scope="row" class="titledesc"></th>
  <td class="forminp">
    <fieldset>
      <h4 style="margin:1rem 0 0.5rem;font-size:1rem"><?php echo wp_kses_post( $data['title'] ); ?></h4>
      <?php echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>
    </fieldset>
  </td>
</tr>
