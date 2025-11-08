<?php
/**
 * YARDLII: Advanced -> Feature Flags & Debug
 *
 * This partial is included from SettingsPageTabs.php and
 * inherits the following variables from its scope:
 *
 * @var string $group_debug
 * @var string $group_flags
 * @var bool   $tv_flag_value
 * @var bool   $tv_flag_locked
 */
defined('ABSPATH') || exit;

$rc_flag_value  = (bool) get_option('yardlii_enable_role_control', false);
$rc_flag_locked = defined('YARDLII_ENABLE_ROLE_CONTROL');
if ($rc_flag_locked) {
    $rc_flag_value = (bool) constant('YARDLII_ENABLE_ROLE_CONTROL');
}

$acf_sync_value  = (bool) get_option('yardlii_enable_acf_user_sync', false);
$acf_sync_locked = defined('YARDLII_ENABLE_ACF_USER_SYNC');
if ($acf_sync_locked) {
    $acf_sync_value = (bool) constant('YARDLII_ENABLE_ACF_USER_SYNC');
}

?>
<div class="form-config-content">

  <?php
  if (function_exists('settings_errors')) {
      settings_errors($group_debug);
      settings_errors($group_flags);
  }
  ?>

  <div class="yardlii-card" style="margin-bottom: 2rem;">
    <h2 style="margin-top:0;"><?php esc_html_e('Debug Mode', 'yardlii-core'); ?></h2>
    <form method="post" action="options.php">
      <?php settings_fields($group_debug); ?>
      <label>
        <input type="hidden" name="yardlii_debug_mode" value="0" />
        <input
          type="checkbox"
          name="yardlii_debug_mode"
          value="1"
          <?php checked((bool) get_option('yardlii_debug_mode', false)); ?>
          <?php disabled(defined('YARDLII_DEBUG') && YARDLII_DEBUG); ?>
        />
        <?php esc_html_e('Enable Debug Mode (logging to debug.log)', 'yardlii-core'); ?>
      </label>
      <?php if (defined('YARDLII_DEBUG') && YARDLII_DEBUG) : ?>
        <em style="opacity:.8;margin-left:.5rem;"><?php esc_html_e('Locked by code', 'yardlii-core'); ?></em>
      <?php endif; ?>
      <?php submit_button(__('Save Debug Setting', 'yardlii-core')); ?>
    </form>
  </div>


  <div class="yardlii-card">
    <h2 style="display:flex;align-items:center;gap:.5rem;margin-top:0;">
      <?php esc_html_e('Feature Flags', 'yardlii-core'); ?>
      <span title="<?php echo esc_attr__('Toggles for optional modules. If a flag is locked by code, the UI is disabled.', 'yardlii-core'); ?>">ℹ️</span>
    </h2>

    <form method="post" action="options.php">
      <?php settings_fields($group_flags); ?>

      <div style="display:flex;align-items:center;gap:.5rem;margin:.5rem 0;">
        <input type="hidden" name="yardlii_enable_trust_verification" value="0" />
        <input
          type="checkbox"
          id="yardlii_enable_trust_verification"
          name="yardlii_enable_trust_verification"
          value="1"
          <?php checked($tv_flag_value); ?>
          <?php disabled($tv_flag_locked); ?>
        />
        <strong><?php esc_html_e('Trust & Verification', 'yardlii-core'); ?></strong>
        <?php if ($tv_flag_locked) : ?>
          <em style="opacity:.8;margin-left:.5rem;"><?php esc_html_e('Locked by code', 'yardlii-core'); ?></em>
        <?php endif; ?>
      </div>

      <div style="display:flex;align-items:center;gap:.5rem;margin:.5rem 0;">
        <input type="hidden" name="yardlii_enable_role_control" value="0" />
        <input
          type="checkbox"
          id="yardlii_enable_role_control"
          name="yardlii_enable_role_control"
          value="1"
          <?php checked($rc_flag_value); ?>
          <?php disabled($rc_flag_locked); ?>
        />
        <strong><?php esc_html_e('Role Control', 'yardlii-core'); ?></strong>
        <?php if ($rc_flag_locked) : ?>
          <em style="opacity:.8;margin-left:.5rem;"><?php esc_html_e('Locked by code', 'yardlii-core'); ?></em>
        <?php endif; ?>
      </div>

      
      <p style="margin-top:1rem;">
        <button class="button button-primary" type="submit">
          <?php esc_html_e('Save Feature Flags', 'yardlii-core'); ?>
        </button>
      </p>
    </form>
  </div>

</div>