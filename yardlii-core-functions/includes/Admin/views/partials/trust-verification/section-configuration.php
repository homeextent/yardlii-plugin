<?php
use Yardlii\Core\Features\TrustVerification\Settings\FormConfigs;
use Yardlii\Core\Features\TrustVerification\Settings\GlobalSettings;

defined('ABSPATH') || exit;

/** ---------------------------------------
 *  Gather options & role data
 * ------------------------------------- */
$wp_roles        = wp_roles();
$roles_map       = is_object($wp_roles) ? ($wp_roles->role_names ?? []) : [];
$all_roles       = is_object($wp_roles) ? ($wp_roles->roles ?? [])      : [];

$admin_emails    = (string) get_option(GlobalSettings::OPT_EMAILS, '');
$override_roles  = (array)  get_option(GlobalSettings::OPT_VERIFIED_ROLES, []);
$override_first  = $override_roles[0] ?? '';

$configs         = (array)  get_option(FormConfigs::OPT_KEY, []);
$base_components = __DIR__ . '/components';

// Common referer back to TV → Configuration (used by both forms)
$tv_ref = add_query_arg(
    [
        'page'      => 'yardlii-core-settings',
        'tab'       => 'trust-verification',
        'tvsection' => 'configuration',
    ],
    admin_url('admin.php')
);
?>

<!-- =========================
     Global Settings
========================== -->
<details class="yardlii-section" id="yardlii-tv-global">
  <summary><?php esc_html_e('Global Settings', 'yardlii-core'); ?></summary>
  <div class="yardlii-section-content">
    <form method="post" action="options.php" class="yardlii-tv-form">
      <?php settings_fields(GlobalSettings::OPT_GROUP); ?>
      <input type="hidden" name="_wp_http_referer" value="<?php echo esc_url($tv_ref); ?>" />

      <table class="form-table" role="presentation">
        <tr>
          <th scope="row">
            <label for="yardlii_tv_admin_emails"><?php esc_html_e('Admin Notification Emails', 'yardlii-core'); ?></label>
          </th>
          <td>
            <input
              type="text"
              id="yardlii_tv_admin_emails"
              name="<?php echo esc_attr(GlobalSettings::OPT_EMAILS); ?>"
              value="<?php echo esc_attr($admin_emails); ?>"
              class="regular-text"
              placeholder="admin@example.com, manager@example.com"
              aria-describedby="yardlii_tv_admin_emails_help"
            />
            <p id="yardlii_tv_admin_emails_help" class="description">
              <?php esc_html_e('Comma-separated list. New requests send an alert here.', 'yardlii-core'); ?>
            </p>
          </td>
        </tr>

        <tr>
          <th scope="row">
            <label for="yardlii_tv_verified_roles"><?php esc_html_e('Verified Roles (override)', 'yardlii-core'); ?></label>
          </th>
          <td>
            <select
              id="yardlii_tv_verified_roles"
              name="<?php echo esc_attr(GlobalSettings::OPT_VERIFIED_ROLES); ?>[]"
            >
              <option value="">
                <?php esc_html_e('— Use per-form Approved roles (no override) —', 'yardlii-core'); ?>
              </option>
              <?php foreach ($all_roles as $slug => $role) : ?>
                <option value="<?php echo esc_attr($slug); ?>" <?php selected($override_first, $slug); ?>>
                  <?php echo esc_html(sprintf('%s (%s)', $role['name'], $slug)); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <p class="description">
              <?php esc_html_e('Optional. If empty, the REST “is_verified” uses Approved Roles from Per-Form Configs.', 'yardlii-core'); ?>
            </p>
          </td>
        </tr>
      </table>

      <p>
        <?php
        submit_button(
          __('Save Global Settings', 'yardlii-core'),
          'secondary',
          null,
          false,
          'class="tv-btn tv-btn--primary"'
        );
        ?>
      </p>
    </form>
  </div>
</details>

<!-- =========================
     Per-Form Configuration
========================== -->
<details class="yardlii-section" open>
  <summary><?php esc_html_e('Per-Form Configuration', 'yardlii-core'); ?></summary>

  <div class="yardlii-section-content">
<?php
// --- Inline-render TV notices and keep them out of the global rail ---
if (function_exists('get_settings_errors')) {

    // TV groups we care about
    $tv_groups = [
        \Yardlii\Core\Features\TrustVerification\Settings\GlobalSettings::OPT_GROUP,
        \Yardlii\Core\Features\TrustVerification\Settings\FormConfigs::OPT_GROUP,
    ];

    // Allow our inline retrieval ONLY inside this block
    $GLOBALS['yardlii_tv_allow_inline_errors'] = true;

    // Fetch group-scoped errors (both groups)
    $errs = [];
    foreach ($tv_groups as $g) {
        $group_errs = (array) get_settings_errors($g);
        if ($group_errs) {
            $errs = array_merge($errs, $group_errs);
        }
    }

    // Stop allowing after we fetched
    unset($GLOBALS['yardlii_tv_allow_inline_errors']);

    // Render Yardlii banners
    foreach ($errs as $e) {
        $t   = (string) ($e['type'] ?? '');
        $cls = ($t === 'updated')
            ? 'yardlii-banner--success'
            : (in_array($t, ['notice-warning','warning','info'], true) ? 'yardlii-banner--info' : 'yardlii-banner--error');

        printf(
            '<div class="yardlii-banner %1$s yardlii-banner--dismiss">' .
              '<button type="button" class="yardlii-banner__close" data-dismiss="yardlii-banner" aria-label="%2$s">×</button>' .
              '<p>%3$s</p>' .
            '</div>',
            esc_attr($cls),
            esc_attr__('Dismiss', 'yardlii-core'),
            wp_kses_post($e['message'] ?? '')
        );
    }

    // Flush from pools so nothing re-prints in the global rail
    delete_transient('settings_errors');
    if (!empty($GLOBALS['wp_settings_errors']) && is_array($GLOBALS['wp_settings_errors'])) {
        $GLOBALS['wp_settings_errors'] = array_values(array_filter(
            $GLOBALS['wp_settings_errors'],
            static function ($e) use ($tv_groups) {
                $s = isset($e['setting']) ? (string) $e['setting'] : '';
                return !in_array($s, $tv_groups, true);
            }
        ));
    }
}
?>

    <form method="post" action="options.php" id="yardlii-tv-form-configs" class="yardlii-tv-form">
      <?php settings_fields(FormConfigs::OPT_GROUP); ?>
      <input type="hidden" name="_wp_http_referer" value="<?php echo esc_url($tv_ref); ?>" />

      <div id="yardlii-tv-config-rows">
        <?php
        if (!empty($configs)) {
          foreach ($configs as $i => $row) {
            $index   = (int) $i;
            $current = [
              'form_id'         => (string)($row['form_id'] ?? ''),
              'approved_role'   => (string)($row['approved_role'] ?? ''),
              'rejected_role'   => (string)($row['rejected_role'] ?? ''),
              'approve_subject' => (string)($row['approve_subject'] ?? ''),
              'approve_body'    => (string)($row['approve_body'] ?? ''),
              'reject_subject'  => (string)($row['reject_subject'] ?? ''),
              'reject_body'     => (string)($row['reject_body'] ?? ''),
            ];
            require $base_components . '/form-config-row.php';
          }
        } else {
          $index   = 0;
          $current = [
            'form_id'         => '',
            'approved_role'   => '',
            'rejected_role'   => '',
            'approve_subject' => '',
            'approve_body'    => '',
            'reject_subject'  => '',
            'reject_body'     => '',
          ];
          require $base_components . '/form-config-row.php';
        }
        ?>
      </div>

      <p>
        <button type="button" class="tv-btn" id="yardlii-tv-add-config">
          <?php esc_html_e('Add Form Configuration', 'yardlii-core'); ?>
        </button>
      </p>

      <p>
        <?php
        submit_button(
          __('Save Per-Form Configurations', 'yardlii-core'),
          'secondary',
          null,
          false,
          'class="tv-btn tv-btn--primary"'
        );
        ?>
      </p>

      <!-- Prototype row (used by JS; __i__ placeholder becomes index) -->
      <template id="yardlii-tv-config-template">
        <?php
          $index   = '__i__';
          $current = [
            'form_id'         => '',
            'approved_role'   => '',
            'rejected_role'   => '',
            'approve_subject' => '',
            'approve_body'    => '',
            'reject_subject'  => '',
            'reject_body'     => '',
          ];
          require $base_components . '/form-config-row.php';
        ?>
      </template>
    </form>
  </div>
</details>
