<?php
/**
 * Yardlii Admin Settings → General Tab → WPUF Customisations
 * -----------------------------------------------------------
 * Adds a toggle for enabling/disabling the WPUF Enhanced Dropdown feature
 * and a configuration field for targeting specific pages.
 *
 * @since 1.0.0
 */
?>

<h2>🔍 WPUF Customisations</h2>
<p class="description">
  Configure front-end listing form behavior for WP User Frontend integrations.
</p>

<form method="post" action="options.php" class="yardlii-settings-form">
  <?php
    settings_fields('yardlii_general_group');
    $dropdown_enabled = (bool) get_option('yardlii_enable_wpuf_dropdown', true);
  ?>

  <table class="form-table yardlii-table">
    <tr valign="top">
      <th scope="row">
        <label for="yardlii_enable_wpuf_dropdown">
          Enable Enhanced Dropdown
        </label>
      </th>
      <td>
        <label class="yardlii-toggle">
	  <input type="checkbox" name="yardlii_wpuf_card_layout" value="1" <?php checked((bool)get_option('yardlii_wpuf_card_layout'), true); ?> />
          <span class="yardlii-toggle-slider"></span>
        </label>
        <p class="description">
          Transforms long WPUF forms into grouped <strong>"Cards"</strong>.
          <br><em>(Requires "Section Break" fields in your form to define the groups).</em>
        </p>
          <input type="checkbox" name="yardlii_wpuf_modern_uploader" value="1" <?php checked((bool)get_option('yardlii_wpuf_modern_uploader'), true); ?> />
          <span class="yardlii-toggle-slider"></span>
        </label>
        <p class="description">
          Reskins the standard upload button into a modern, dashed-border <strong>"Dropzone"</strong> area with a cloud icon.
        </p>
          <input type="checkbox" name="yardlii_enable_wpuf_dropdown" value="1" <?php checked($dropdown_enabled, true); ?> />
          <span class="yardlii-toggle-slider"></span>
        </label>
        <p class="description">
          When enabled, replaces the default WPUF taxonomy select field (Group) with the
          custom Yardlii Enhanced Dropdown interface.
        </p>

        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
            <label for="yardlii_wpuf_target_pages" style="display:block; margin-bottom:5px;">
                <strong><?php esc_html_e('Target Pages', 'yardlii-core'); ?></strong>
            </label>
            <input 
                type="text" 
                id="yardlii_wpuf_target_pages" 
                name="yardlii_wpuf_target_pages" 
                value="<?php echo esc_attr(get_option('yardlii_wpuf_target_pages', 'submit-a-post')); ?>" 
                class="regular-text"
                placeholder="e.g. submit-a-post, edit-listing, 123"
            />
            <p class="description" style="margin-top: 5px;">
                <?php esc_html_e('Enter the Page Slugs or IDs where the Enhanced Dropdown should load (comma-separated).', 'yardlii-core'); ?>
            </p>
        </div>
      </td>
    </tr>
  </table>

  <?php submit_button('Save WPUF Settings'); ?>
</form>