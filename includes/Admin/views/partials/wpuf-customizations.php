<?php
/**
 * Yardlii Admin Settings → General Tab → WPUF Customisations
 * -----------------------------------------------------------
 * Configuration for WPUF frontend styling and listing logic.
 *
 * @since 3.10.0
 */
?>

<h2>🔧 WPUF Customisations</h2>
<p class="description">
  Configure front-end listing form behavior and listing status logic.
</p>

<form method="post" action="options.php" class="yardlii-settings-form">
  <?php
    settings_fields('yardlii_general_group');
    
    // Retrieve options
    $dropdown_enabled = (bool) get_option('yardlii_enable_wpuf_dropdown', true);
    $card_enabled     = (bool) get_option('yardlii_wpuf_card_layout', false);
    $uploader_enabled = (bool) get_option('yardlii_wpuf_modern_uploader', false);
    $featured_enabled = (bool) get_option('yardlii_enable_featured_listings', false);
  ?>

  <table class="form-table yardlii-table">
    
    <tr valign="top">
      <th scope="row">
        <h3>🎨 Frontend Styling</h3>
        <p class="description">Controls the look of forms on the site.</p>
      </th>
      <td>
        <div style="background: #f9f9f9; border: 1px solid #e5e5e5; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
            <label for="yardlii_wpuf_target_pages" style="display:block; margin-bottom:5px;">
                <strong><?php esc_html_e('Target Pages (Scope)', 'yardlii-core'); ?></strong>
            </label>
            <input 
                type="text" 
                id="yardlii_wpuf_target_pages" 
                name="yardlii_wpuf_target_pages" 
                value="<?php echo esc_attr(get_option('yardlii_wpuf_target_pages', 'submit-a-post')); ?>" 
                class="regular-text"
                style="width: 100%;"
                placeholder="e.g. submit-a-post, edit-listing, 123"
            />
            <p class="description" style="margin-top: 5px;">
                <?php esc_html_e('Enter Page Slugs or IDs (comma-separated). The visual features below will ONLY load on these pages.', 'yardlii-core'); ?>
            </p>
        </div>

        <div class="yardlii-setting-row" style="margin-bottom: 15px;">
            <label class="yardlii-toggle">
                <input type="checkbox" name="yardlii_wpuf_card_layout" value="1" <?php checked($card_enabled, true); ?> />
                <span class="yardlii-toggle-slider"></span>
            </label>
            <div style="display:inline-block; vertical-align:top; margin-left: 10px;">
                <strong>Card-Style Layout</strong>
                <p class="description" style="margin-top: 2px;">
                    Groups form fields into modern "Cards" based on Section Breaks.
                </p>
            </div>
        </div>

        <div class="yardlii-setting-row" style="margin-bottom: 15px;">
            <label class="yardlii-toggle">
                <input type="checkbox" name="yardlii_wpuf_modern_uploader" value="1" <?php checked($uploader_enabled, true); ?> />
                <span class="yardlii-toggle-slider"></span>
            </label>
            <div style="display:inline-block; vertical-align:top; margin-left: 10px;">
                <strong>Modern Uploader Skin</strong>
                <p class="description" style="margin-top: 2px;">
                    Transforms the standard upload button into a drag-and-drop "Dropzone".
                </p>
            </div>
        </div>

        <div class="yardlii-setting-row">
            <label class="yardlii-toggle">
                <input type="checkbox" name="yardlii_enable_wpuf_dropdown" value="1" <?php checked($dropdown_enabled, true); ?> />
                <span class="yardlii-toggle-slider"></span>
            </label>
            <div style="display:inline-block; vertical-align:top; margin-left: 10px;">
                <strong>Enhanced Taxonomy Dropdown</strong>
                <p class="description" style="margin-top: 2px;">
                    Replaces standard Category select fields with the YARDLII interactive menu.
                </p>
            </div>
        </div>

      </td>
    </tr>

    <tr><td colspan="2"><hr style="border: 0; border-top: 1px solid #ddd;"></td></tr>

    <tr valign="top">
      <th scope="row">
        <h3>🧠 Listing Logic</h3>
        <p class="description">Backend data handling.</p>
      </th>
      <td>
        <div class="yardlii-setting-row">
            <label class="yardlii-toggle">
                <input type="checkbox" name="yardlii_enable_featured_listings" value="1" <?php checked($featured_enabled, true); ?> />
                <span class="yardlii-toggle-slider"></span>
            </label>
            <div style="display:inline-block; vertical-align:top; margin-left: 10px;">
                <strong>Enable Featured Listing Logic</strong>
                <p class="description" style="margin-top: 2px;">
                    Synchronizes "Featured" status between ACF, WPUF, and WordPress Sticky posts.<br>
                    Enables the <code>[yardlii_featured_badge]</code> shortcode and Admin Filters.
                </p>
            </div>
        </div>
      </td>
    </tr>

  </table>

  <?php submit_button('Save WPUF Settings'); ?>
</form>