<?php
use Yardlii\Core\Features\ACFUserSync;

$option_key = 'yardlii_acf_user_sync_settings_v2';
$settings = get_option($option_key, []);
$mappings = $settings['mappings'] ?? [];

// ✅ Dynamically pull available "Special Handling" options from the registry
$registered = (new \Yardlii\Core\Features\ACFUserSync())->get_registered_special_handlers();
$special_options = ['' => __('None', 'yardlii-core')] + array_map(fn($item) => $item['label'], $registered);
?>

<div class="form-config-block">
  <h2 class="config-header heading-user-sync">🔄 <?php esc_html_e('ACF User Sync', 'yardlii-core'); ?></h2>
  <div class="form-config-content">

    <p class="description">
      <?php esc_html_e('Define field fallback mappings. Use the Preview Tool to test field results on any post.', 'yardlii-core'); ?>
    </p>

    <form method="post" action="options.php" id="yardlii-user-sync-form">
      <?php settings_fields($option_key . '_group'); ?>

<!-- 🧩 Yardlii Help Panel -->
<details class="yardlii-help-box" style="margin:15px 0;">
  <summary style="
      cursor:pointer;
      font-weight:600;
      font-size:14px;
      color:#0A84FF;
      display:flex;
      align-items:center;
      gap:6px;">
    <span class="dashicons dashicons-lightbulb" style="color:#f1c40f;"></span>
    <?php esc_html_e('How to add new “Special Handling” types', 'yardlii-core'); ?>
  </summary>

  <div style="
      margin-top:10px;
      background:#f9fafb;
      border:1px solid #ccd0d4;
      border-radius:6px;
      padding:15px;
      font-size:13px;
      line-height:1.6;">
    <p style="margin-top:0;">
      <?php esc_html_e('All Special Handling options are defined inside:', 'yardlii-core'); ?>
      <code style="background:#fff; border:1px solid #ddd; padding:2px 5px; border-radius:3px;">includes/Features/acfusersync.php</code>
      <?php esc_html_e('in the method', 'yardlii-core'); ?>
      <code>get_registered_special_handlers()</code>.
    </p>

    <p style="margin:8px 0;">
      <?php esc_html_e('Each handler defines a label and a callback that returns a value for the post author.', 'yardlii-core'); ?>
    </p>

    <div style="background:#fff; border:1px solid #e2e4e7; border-radius:4px; padding:10px; overflow-x:auto;">
      <pre style="margin:0; font-size:12px;"><code>'user_avatar' => [
    'label'    => __('User Avatar URL', 'yardlii-core'),
    'callback' => function($author_id) {
        return get_avatar_url($author_id, ['size' => 96]);
    },
],</code></pre>
    </div>

    <p style="margin:10px 0 0 0;">
      <?php esc_html_e('After saving the file, reload this page — your new type will automatically appear in the “Special Handling” dropdown.', 'yardlii-core'); ?>
    </p>

    <p style="margin:6px 0 0 0; font-style:italic; color:#555;">
      <?php esc_html_e('Tip: You can define any number of custom handlers (e.g. user_role, user_display_name, user_avatar).', 'yardlii-core'); ?>
    </p>
  </div>
</details>
<!-- 🧩 End Yardlii Help Panel -->


      <table id="yardlii-mappings" class="widefat">
        <thead>
          <tr>
            <th><?php esc_html_e('Target Field Name', 'yardlii-core'); ?></th>
            <th><?php esc_html_e('Fallback', 'yardlii-core'); ?></th>
            <th><?php esc_html_e('Special Handling', 'yardlii-core'); ?></th>
            <th><?php esc_html_e('Actions', 'yardlii-core'); ?></th>
          </tr>
        </thead>
        <tbody id="yardlii-repeater-body">
          <?php if (!empty($mappings)) :
            foreach ($mappings as $index => $row) : ?>
              <tr class="yardlii-row">
                <td>
                  <input type="text"
                    name="<?php echo esc_attr($option_key); ?>[mappings][<?php echo esc_attr($index); ?>][field]"
                    value="<?php echo esc_attr($row['field'] ?? ''); ?>"
                    required
                    placeholder="e.g. author_bio" />
                </td>
                <td>
                  <input type="text"
                    name="<?php echo esc_attr($option_key); ?>[mappings][<?php echo esc_attr($index); ?>][fallback]"
                    value="<?php echo esc_attr($row['fallback'] ?? ''); ?>"
                    placeholder="Default text or image ID" />
                </td>
                <td>
                  <select name="<?php echo esc_attr($option_key); ?>[mappings][<?php echo esc_attr($index); ?>][special]">
                    <?php foreach ($special_options as $value => $label) : ?>
                      <option value="<?php echo esc_attr($value); ?>" <?php selected($row['special'] ?? '', $value); ?>>
                        <?php echo esc_html($label); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td>
                  <button type="button" class="button remove-row">
                    <?php esc_html_e('Remove', 'yardlii-core'); ?>
                  </button>
                </td>
              </tr>
          <?php endforeach;
          endif; ?>
        </tbody>

      
      </table>

      

      <p style="margin-top: 15px;">
        <button type="button" id="yardlii-add-row" class="button button-secondary">
          <?php esc_html_e('Add Row', 'yardlii-core'); ?>
        </button>
      </p>

      <?php
// Pull current saved format
$date_format_setting = $settings['date_display_format'] ?? 'month_year';
$date_format_options = [
  'full'       => __('Full date (e.g. March 12, 2023)', 'yardlii-core'),
  'month_year' => __('Month + Year (e.g. March 2023)', 'yardlii-core'),
  'year'       => __('Year only (e.g. 2023)', 'yardlii-core'),
];
?>
<p style="margin-top: 20px;">
  <label for="yardlii_date_display_format" style="font-weight:600;">
    <?php esc_html_e('Date Display Format', 'yardlii-core'); ?>:
  </label>
  <select name="<?php echo esc_attr($option_key); ?>[date_display_format]" id="yardlii_date_display_format">
    <?php foreach ($date_format_options as $val => $label) : ?>
      <option value="<?php echo esc_attr($val); ?>" <?php selected($date_format_setting, $val); ?>>
        <?php echo esc_html($label); ?>
      </option>
    <?php endforeach; ?>
  </select>
  <span class="description"><?php esc_html_e('Choose how to display the "Member Since" date for users.', 'yardlii-core'); ?></span>
</p>

      <?php submit_button(__('Save Settings', 'yardlii-core')); ?>
    </form>

    <hr>

    <h3><?php esc_html_e('Preview Tool', 'yardlii-core'); ?></h3>
    <div id="yardlii-preview-tool">
      <label><?php esc_html_e('Post ID:', 'yardlii-core'); ?></label>
      <input type="number" id="yardlii_preview_post_id" placeholder="<?php esc_attr_e('Enter Post ID', 'yardlii-core'); ?>" style="width:150px;">
      <label><?php esc_html_e('Field:', 'yardlii-core'); ?></label>
      <select id="yardlii_preview_field">
        <option value=""><?php esc_html_e('Select field...', 'yardlii-core'); ?></option>
        <?php foreach ($mappings as $row) :
          if (!empty($row['field'])) :
            printf('<option value="%s">%s</option>', esc_attr($row['field']), esc_html($row['field']));
          endif;
        endforeach; ?>
      </select>
      <button id="yardlii_preview_btn" class="button button-primary">
        <?php esc_html_e('Preview', 'yardlii-core'); ?>
      </button>
    </div>

    <div id="yardlii_preview_output"></div>
  </div>
</div>
