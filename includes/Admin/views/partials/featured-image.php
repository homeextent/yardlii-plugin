<?php
defined('ABSPATH') || exit;
?>

<div class="form-config-block">
  <h2>🖼️ Featured Image Automation</h2>
  <form method="post" action="options.php">
    <?php
    settings_fields('yardlii_featured_image_group');

    // --- 1. MODIFICATION: Renamed variable and cast to (array) ---
    $saved_forms = (array) get_option('yardlii_listing_form_id', []);
    // --- End Modification ---

    $saved_field = get_option('yardlii_featured_image_field', '');

    // 🔍 Collect all ACF gallery fields site-wide
    $gallery_fields = [];
    if (function_exists('acf_get_field_groups')) {
        foreach (acf_get_field_groups() as $group) {
            $fields = acf_get_fields($group);
            if (!$fields) continue;
            foreach ($fields as $field) {
                if ($field['type'] === 'gallery') {
                    $gallery_fields[$field['name']] = $field['label'];
                }
            }
        }
    }

    // 🧭 Collect all possible forms
    $forms = [];

    // ✅ WP User Frontend forms (main one you use)
    $wpuf_forms = get_posts([
        'post_type'      => 'wpuf_forms',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    ]);
    foreach ($wpuf_forms as $form) {
        $forms[$form->ID] = $form->post_title . ' (WPUF)';
    }

    // (Optional) Other plugin types if present
    if (class_exists('FrmForm')) { // Formidable
        foreach (\FrmForm::getAll() as $form) {
            $forms[$form->id] = $form->name . ' (Formidable)';
        }
    }
    if (class_exists('WPForms\WPForms')) { // WPForms
        $query = new WP_Query(['post_type' => 'wpforms', 'posts_per_page' => -1]);
        foreach ($query->posts as $p) {
            $forms[$p->ID] = $p->post_title . ' (WPForms)';
        }
    }
    ?>

    <table class="form-table">
      <tr valign="top">
        <th scope="row"><?php esc_html_e('ACF Gallery Field', 'yardlii-core'); ?></th>
        <td>
          <select name="yardlii_featured_image_field" style="min-width:300px;">
            <option value=""><?php esc_html_e('Select ACF Gallery Field', 'yardlii-core'); ?></option>
            <?php foreach ($gallery_fields as $name => $label) : ?>
              <option value="<?php echo esc_attr($name); ?>" <?php selected($saved_field, $name); ?>>
                <?php echo esc_html($label . ' (' . $name . ')'); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (empty($gallery_fields)) : ?>
            <p class="description"><?php esc_html_e('No ACF gallery fields found — enter one manually below.', 'yardlii-core'); ?></p>
            <input type="text" name="yardlii_featured_image_field" value="<?php echo esc_attr($saved_field); ?>" class="regular-text" placeholder="gallery_field_name">
          <?php endif; ?>
        </td>
      </tr>


      <tr valign="top">
        <th scope="row"><?php esc_html_e('Listing Submission Form IDs', 'yardlii-core'); ?></th>
        <td>
          <?php // --- 2. MODIFICATION: Added '[]' to name, 'multiple', and 'style' --- ?>
          <select name="yardlii_listing_form_id[]" multiple style="min-width:300px; height: 150px;">
          <?php // --- End Modification --- ?>
            <option value=""><?php esc_html_e('Select form source(s) for listings', 'yardlii-core'); ?></option>
            <?php foreach ($forms as $id => $title) : ?>
              <?php // --- 3. MODIFICATION: Changed 'selected()' to 'selected(in_array())' --- ?>
              <option value="<?php echo esc_attr($id); ?>" <?php selected(in_array($id, $saved_forms, true)); ?>>
              <?php // --- End Modification --- ?>
                <?php echo esc_html($title); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <p class="description">
            <?php esc_html_e('Used to detect which form(s) create listings and pull images for featured image automation.', 'yardlii-core'); ?><br/>
            <?php esc_html_e('Hold Ctrl (or Cmd on Mac) to select multiple forms.', 'yardlii-core'); ?>
          </p>
        </td>
      </tr>

      <tr valign="top">
  <th scope="row"><?php esc_html_e('Enable Debug Notices', 'yardlii-core'); ?></th>
  <td>
    <?php $debug_enabled = get_option('yardlii_featured_image_debug', false); ?>
    <label>
      <input type="checkbox" name="yardlii_featured_image_debug" value="1" <?php checked($debug_enabled, 1); ?> />
      <?php esc_html_e('Show admin notices when featured image automation runs (recommended for testing only).', 'yardlii-core'); ?>
    </label>
  </td>
</tr>
<tr valign="top">
  <th scope="row"><?php esc_html_e('Test Automation', 'yardlii-core'); ?></th>
  <td>
    <input type="number" id="yardlii_test_post_id" placeholder="<?php esc_attr_e('Enter Listing Post ID', 'yardlii-core'); ?>" style="width:180px;">
    <button type="button" class="button button-secondary" id="yardlii_run_test"><?php esc_html_e('Run Test', 'yardlii-core'); ?></button>
    <button type="button" class="button" id="yardlii_reset_test"><?php esc_html_e('Reset Featured Image', 'yardlii-core'); ?></button>
    <p class="description"><?php esc_html_e('Simulate or reset featured image automation for an existing listing.', 'yardlii-core'); ?></p>
    <div id="yardlii_test_result" style="margin-top:8px;font-weight:600;"></div>
  </td>
</tr>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const runBtn   = document.getElementById('yardlii_run_test');
  const resetBtn = document.getElementById('yardlii_reset_test');
  const resultEl = document.getElementById('yardlii_test_result');

  function runAction(action) {
    const postId = document.getElementById('yardlii_test_post_id').value.trim();
    if (!postId) {
      resultEl.textContent = '⚠️ Please enter a valid Post ID.';
      resultEl.style.color = '#d63638';
      return;
    }
    resultEl.textContent = (action === 'yardlii_test_featured_image') ? 'Running test…' : 'Resetting featured image…';
    resultEl.style.color = '#555';

    fetch(ajaxurl, {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: new URLSearchParams({
        action,
        post_id: postId,
        _ajax_nonce: '<?php echo wp_create_nonce('yardlii_test_featured_image'); ?>'
      })
    })
    .then(res => res.json())
    .then(data => {
      resultEl.textContent = data.message || 'Unknown response.';
      resultEl.style.color = data.success ? '#0073aa' : '#d63638';
    })
    .catch(() => {
      resultEl.textContent = '❌ AJAX request failed.';
      resultEl.style.color = '#d63638';
    });
  }

  if (runBtn)   runBtn.addEventListener('click', () => runAction('yardlii_test_featured_image'));
  if (resetBtn) resetBtn.addEventListener('click', () => runAction('yardlii_reset_featured_image'));
});
</script>


    </table>

    <?php submit_button(__('Save Featured Image Settings', 'yardlii-core')); ?>
  </form>
</div>