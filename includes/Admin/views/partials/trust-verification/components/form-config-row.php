<?php
/**
 * Single Per-Form Configuration Row (accordion item)
 *
 * @var int|string $index
 * @var array      $current
 * @var array      $roles_map
 */

$roles_map = is_array($roles_map ?? null) ? $roles_map : [];

// Helpers for summary text
$form_label     = trim((string)($current['form_id'] ?? '')) ?: __('New form', 'yardlii-core');
$approved_role  = (string)($current['approved_role'] ?? '');
$rejected_role  = (string)($current['rejected_role'] ?? '');
$approved_label = ($approved_role && isset($roles_map[$approved_role]))
  ? $roles_map[$approved_role] . " ($approved_role)" : '—';
$rejected_label = ($rejected_role && isset($roles_map[$rejected_role]))
  ? $roles_map[$rejected_role] . " ($rejected_role)" : '—';

// Default recipient for "Send test email"
$wp_user     = wp_get_current_user();
$default_to  = ($wp_user && !empty($wp_user->user_email)) ? $wp_user->user_email : get_option('admin_email');
?>
<details class="yardlii-section yardlii-tv-config-item" data-index="<?php echo esc_attr($index); ?>">
  <summary class="yardlii-tv-config-summary">
    <span class="tv-drag-handle" title="<?php esc_attr_e('Drag to reorder', 'yardlii-core'); ?>" aria-hidden="true">⋮⋮</span>
    <strong><?php esc_html_e('Form:', 'yardlii-core'); ?></strong>
    <span data-summary="form"><?php echo esc_html($form_label); ?></span>
    <span> • <strong><?php esc_html_e('Approved:', 'yardlii-core'); ?></strong>
      <span data-summary="approved"><?php echo esc_html($approved_label); ?></span>
    </span>
    <span> • <strong><?php esc_html_e('Rejected:', 'yardlii-core'); ?></strong>
      <span data-summary="rejected"><?php echo esc_html($rejected_label); ?></span>
    </span>
  </summary>

  <div class="yardlii-section-content">
    <div class="yardlii-tv-config-grid">
      <p>
        <label for="tv_form_id_<?php echo esc_attr($index); ?>">
          <strong><?php esc_html_e('Form ID', 'yardlii-core'); ?></strong>
        </label><br>
        <input type="text"
               id="tv_form_id_<?php echo esc_attr($index); ?>"
               name="yardlii_tv_form_configs[<?php echo esc_attr($index); ?>][form_id]"
               value="<?php echo esc_attr($current['form_id'] ?? ''); ?>"
               class="regular-text" />
      </p>

      <p>
        <label for="tv_approved_role_<?php echo esc_attr($index); ?>">
          <strong><?php esc_html_e('Approved Role', 'yardlii-core'); ?></strong>
        </label><br>
        <select id="tv_approved_role_<?php echo esc_attr($index); ?>"
                name="yardlii_tv_form_configs[<?php echo esc_attr($index); ?>][approved_role]">
          <option value=""><?php esc_html_e('— Select —', 'yardlii-core'); ?></option>
          <?php foreach ($roles_map as $slug => $label): ?>
            <option value="<?php echo esc_attr($slug); ?>" <?php selected(($current['approved_role'] ?? '') === $slug); ?>>
              <?php echo esc_html($label . " ($slug)"); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </p>

      <p>
        <label for="tv_rejected_role_<?php echo esc_attr($index); ?>">
          <strong><?php esc_html_e('Rejected Role', 'yardlii-core'); ?></strong>
        </label><br>
        <select id="tv_rejected_role_<?php echo esc_attr($index); ?>"
                name="yardlii_tv_form_configs[<?php echo esc_attr($index); ?>][rejected_role]">
          <option value=""><?php esc_html_e('— Select —', 'yardlii-core'); ?></option>
          <?php foreach ($roles_map as $slug => $label): ?>
            <option value="<?php echo esc_attr($slug); ?>" <?php selected(($current['rejected_role'] ?? '') === $slug); ?>>
              <?php echo esc_html($label . " ($slug)"); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </p>

      <p>
        <label for="tv_approve_subject_<?php echo esc_attr($index); ?>">
          <strong><?php esc_html_e('Approve Subject', 'yardlii-core'); ?></strong>
        </label><br>
        <input type="text"
               id="tv_approve_subject_<?php echo esc_attr($index); ?>"
               name="yardlii_tv_form_configs[<?php echo esc_attr($index); ?>][approve_subject]"
               value="<?php echo esc_attr($current['approve_subject'] ?? ''); ?>"
               class="regular-text" />
      </p>

      <p class="wide">
        <label for="tv_approve_body_<?php echo esc_attr($index); ?>">
          <strong><?php esc_html_e('Approve Body (HTML)', 'yardlii-core'); ?></strong>
        </label><br>
        <textarea
          id="tv_approve_body_<?php echo esc_attr($index); ?>"
          name="yardlii_tv_form_configs[<?php echo esc_attr($index); ?>][approve_body]"
          class="widefat yardlii-tv-editor"
          rows="8"><?php echo esc_textarea($current['approve_body'] ?? ''); ?></textarea>
        <small class="description">
          <?php esc_html_e('Placeholders: {display_name}, {user_email}, {user_login}, {form_id}, {request_id}, {site_title}, {site_url}', 'yardlii-core'); ?>
        </small>
      </p>

      <p>
        <label for="tv_reject_subject_<?php echo esc_attr($index); ?>">
          <strong><?php esc_html_e('Reject Subject', 'yardlii-core'); ?></strong>
        </label><br>
        <input type="text"
               id="tv_reject_subject_<?php echo esc_attr($index); ?>"
               name="yardlii_tv_form_configs[<?php echo esc_attr($index); ?>][reject_subject]"
               value="<?php echo esc_attr($current['reject_subject'] ?? ''); ?>"
               class="regular-text" />
      </p>

      <p class="wide">
        <label for="tv_reject_body_<?php echo esc_attr($index); ?>">
          <strong><?php esc_html_e('Reject Body (HTML)', 'yardlii-core'); ?></strong>
        </label><br>
        <textarea
          id="tv_reject_body_<?php echo esc_attr($index); ?>"
          name="yardlii_tv_form_configs[<?php echo esc_attr($index); ?>][reject_body]"
          class="widefat yardlii-tv-editor"
          rows="8"><?php echo esc_textarea($current['reject_body'] ?? ''); ?></textarea>
        <small class="description">
          <?php esc_html_e('Placeholders: {display_name}, {user_email}, {user_login}, {form_id}, {request_id}, {site_title}, {site_url}', 'yardlii-core'); ?>
        </small>
      </p>
    </div>

    <div class="tv-testbar" role="group" aria-label="<?php esc_attr_e('Test email', 'yardlii-core'); ?>">
      <label>
        <input type="radio"
               name="yardlii_tv_form_configs[<?php echo esc_attr($index); ?>][preview_type]"
               value="approve"
               checked>
        <?php esc_html_e('Approve email', 'yardlii-core'); ?>
      </label>

      <label>
        <input type="radio"
               name="yardlii_tv_form_configs[<?php echo esc_attr($index); ?>][preview_type]"
               value="reject">
        <?php esc_html_e('Reject email', 'yardlii-core'); ?>
      </label>

      <label>
        <?php esc_html_e('User ID', 'yardlii-core'); ?>
        <input type="number"
               min="1"
               class="small-text"
               name="yardlii_tv_form_configs[<?php echo esc_attr($index); ?>][preview_user]"
               placeholder="e.g. 175">
      </label>

      <label>
        <?php esc_html_e('Send to', 'yardlii-core'); ?>
        <input type="email"
               class="regular-text"
               name="yardlii_tv_form_configs[<?php echo esc_attr($index); ?>][preview_to]"
               placeholder="<?php echo esc_attr($default_to); ?>">
      </label>

      <button type="button" class="tv-btn" data-action="tv-row-preview-email">
        <?php esc_html_e('Preview test email', 'yardlii-core'); ?>
      </button>

      <button type="button" class="tv-btn tv-btn--primary" data-action="tv-row-send-test">
        <?php esc_html_e('Send test email', 'yardlii-core'); ?>
      </button>
    </div>

    <p>
      <button type="button" class="tv-btn" data-action="tv-duplicate-config-row">
        <?php esc_html_e('Duplicate this configuration', 'yardlii-core'); ?>
      </button>
      &nbsp;|&nbsp;
      <button type="button" class="tv-btn tv-btn--primary" data-action="tv-remove-config-row">
        <?php esc_html_e('Remove this configuration', 'yardlii-core'); ?>
      </button>
    </p>
  </div>
</details>
