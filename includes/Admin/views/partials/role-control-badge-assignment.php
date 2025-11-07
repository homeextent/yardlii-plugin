<?php
use Yardlii\Core\Features\RoleControlBadgeAssignment as BA;
if (!defined('ABSPATH')) exit;

$enabled_opt = (bool) get_option(BA::ENABLE_OPTION, true);
$settings    = (array) get_option(BA::OPTION_KEY, []);
$map         = is_array($settings['map'] ?? null) ? $settings['map'] : [];
$meta_key    = !empty($settings['meta_key']) ? sanitize_key($settings['meta_key']) : BA::DEFAULT_META_KEY;
$fallback    = !empty($settings['fallback_field']) ? sanitize_key($settings['fallback_field']) : '';

// Ensure roles API is loaded
if (!function_exists('get_editable_roles')) {
    require_once ABSPATH . 'wp-admin/includes/user.php';
}
$roles = get_editable_roles(); // ['slug' => ['name'=>...], ...]

// Build rows array from current map so UI is stable across saves
$rows = [];
foreach ($map as $role => $field) {
    $rows[] = ['role' => $role, 'field' => $field];
}
// If no rows yet, start with one empty line for UX
if (empty($rows)) {
    $rows[] = ['role' => '', 'field' => ''];
}

// Collect all role slugs + ensure any mapped-but-missing roles appear
$role_slugs = array_keys($roles);
foreach (array_keys($map) as $mapped_role) {
    if (!in_array($mapped_role, $role_slugs, true)) {
        $role_slugs[] = $mapped_role; // keep selectable; mark as (missing) below
    }
}

// Helper: label for role option
function yl_role_label($slug, $roles) {
    $name = $roles[$slug]['name'] ?? null;
    return $name ? "{$name} ({$slug})" : "{$slug} — (missing role)";
}
?>

<section class="yardlii-panel-section">
  <h3>🏷️ Badge Assignment</h3>
  <p class="description">
    Map user roles to ACF <em>Options</em> image fields. On role/profile/login changes we store the image <strong>ID</strong> in the user meta
    <code><?php echo esc_html($meta_key); ?></code>.
  </p>

  <form method="post" action="options.php" class="yardlii-form yl-badges-form">
    <?php settings_fields('yardlii_role_control_badges_group'); ?>

    <table class="form-table" role="presentation">
      <tr>
        <th scope="row">Enable Badge Assignment</th>
        <td>
          <label class="yardlii-toggle">
            <input type="hidden" name="<?php echo esc_attr(BA::ENABLE_OPTION); ?>" value="0" />
            <input type="checkbox" name="<?php echo esc_attr(BA::ENABLE_OPTION); ?>" value="1" <?php checked($enabled_opt, true); ?> />
            <span>On</span>
          </label>
        </td>
      </tr>
      <tr>
        <th scope="row">User meta key</th>
        <td>
          <input class="regular-text" type="text"
                 name="<?php echo esc_attr(BA::OPTION_KEY); ?>[meta_key]"
                 value="<?php echo esc_attr($meta_key); ?>" placeholder="user_badge" />
          <p class="description">Where the badge image ID is stored for each user.</p>
        </td>
      </tr>
      <tr>
        <th scope="row">Fallback ACF field (optional)</th>
        <td>
          <input class="regular-text" type="text"
                 name="<?php echo esc_attr(BA::OPTION_KEY); ?>[fallback_field]"
                 value="<?php echo esc_attr($fallback); ?>" placeholder="e.g. badge_default" />
          <p class="description">Used when no row matches a user’s role.</p>
        </td>
      </tr>

      <tr>
        <th scope="row">Role → ACF image field (Options)</th>
        <td>
          <div class="yl-rows-wrap" data-rows-root>
            <?php foreach ($rows as $index => $row): ?>
              <div class="yl-row" data-row>
                <select name="<?php echo esc_attr(BA::OPTION_KEY); ?>[rows][<?php echo (int) $index; ?>][role]" class="yl-role">
                  <option value=""><?php esc_html_e('— Select role —','yardlii-core'); ?></option>
                  <?php foreach ($role_slugs as $slug): ?>
                    <option value="<?php echo esc_attr($slug); ?>"
                      <?php selected($row['role'], $slug); ?>>
                      <?php echo esc_html(yl_role_label($slug, $roles)); ?>
                    </option>
                  <?php endforeach; ?>
                </select>

                <span class="yl-sep">→</span>

                <input type="text" class="regular-text yl-field"
                  name="<?php echo esc_attr(BA::OPTION_KEY); ?>[rows][<?php echo (int) $index; ?>][field]"
                  value="<?php echo esc_attr($row['field']); ?>"
                  placeholder="e.g. badge_<?php echo esc_attr($row['role'] ?: 'role'); ?>" />

                <button type="button" class="button-link-delete yl-remove" aria-label="<?php esc_attr_e('Remove row','yardlii-core'); ?>">&times;</button>
              </div>
            <?php endforeach; ?>
          </div>

          <p style="margin-top:8px;">
            <button type="button" class="button yl-add" data-add-row>+ Add mapping</button>
          </p>

          <p class="description" id="yl-dup-note" style="display:none;">
            Duplicate roles will be collapsed on save; only the first mapping for a role is kept.
          </p>

          <template id="yl-row-tpl">
            <div class="yl-row" data-row>
              <select class="yl-role" name="">
                <option value=""><?php esc_html_e('— Select role —','yardlii-core'); ?></option>
                <?php foreach ($role_slugs as $slug): ?>
                  <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html(yl_role_label($slug, $roles)); ?></option>
                <?php endforeach; ?>
              </select>
              <span class="yl-sep">→</span>
              <input type="text" class="regular-text yl-field" name="" placeholder="e.g. badge_role" />
              <button type="button" class="button-link-delete yl-remove" aria-label="<?php esc_attr_e('Remove row','yardlii-core'); ?>">&times;</button>
            </div>
          </template>

          <style>
            .yl-row { display:flex; align-items:center; gap:8px; margin:6px 0; }
            .yl-sep { opacity:0.6; }
            .yl-remove { color:#b32d2e; line-height:1; font-size:18px; }
            .yl-remove:hover { color:#8a2426; }
            .yl-rows-wrap select.yl-role { min-width: 240px; }
            .yl-rows-wrap input.yl-field { min-width: 260px; }
          </style>

          <script>
            (function(){
              const root   = document.querySelector('[data-rows-root]');
              const addBtn = document.querySelector('[data-add-row]');
              const tpl    = document.getElementById('yl-row-tpl');
              const group  = '<?php echo esc_js(BA::OPTION_KEY); ?>';
              const note   = document.getElementById('yl-dup-note');

              if (!root || !addBtn || !tpl) return;

              // Reindex all row inputs to keep names contiguous: yardlii_rc_badges[rows][i][role|field]
              function reindex(){
                const rows = root.querySelectorAll('[data-row]');
                rows.forEach((row, i) => {
                  const role  = row.querySelector('.yl-role');
                  const field = row.querySelector('.yl-field');
                  if (role)  role.name  = group + '[rows][' + i + '][role]';
                  if (field) field.name = group + '[rows][' + i + '][field]';
                });
              }

              // Add row
              addBtn.addEventListener('click', () => {
                const clone = document.importNode(tpl.content, true);
                root.appendChild(clone);
                reindex();
              });

              // Remove row
              root.addEventListener('click', (e) => {
                if (e.target && e.target.classList.contains('yl-remove')) {
                  const row = e.target.closest('[data-row]');
                  if (row) row.remove();
                  reindex();
                }
              });

              // Simple duplicate-role hint (server is source of truth)
              root.addEventListener('change', (e) => {
                if (!e.target.classList.contains('yl-role')) return;
                const selected = [...root.querySelectorAll('.yl-role')].map(s=>s.value).filter(Boolean);
                const hasDup = (new Set(selected)).size !== selected.length;
                if (note) note.style.display = hasDup ? 'block' : 'none';
              });
            })();
          </script>
        </td>
      </tr>
    </table>

    <?php submit_button(__('Save Settings', 'yardlii-core')); ?>
  </form>

  <?php if ( current_user_can('manage_options') ) : ?>
  <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="margin-top:12px;">
    <?php wp_nonce_field('yardlii_rc_badges_resync_all'); ?>
    <input type="hidden" name="action" value="yardlii_rc_badges_resync_all" />
    <?php submit_button( __('Resync All Users', 'yardlii-core'), 'secondary', 'submit', false ); ?>
  </form>

  <?php if ( isset($_GET['yl_rc_badges_resynced']) ) : ?>
    <?php if ( '1' === $_GET['yl_rc_badges_resynced'] ) : ?>
      <div class="notice notice-success" style="margin-top:8px;"><p><?php echo esc_html__('Badge resync complete.', 'yardlii-core'); ?></p></div>
    <?php else : ?>
      <div class="notice notice-warning" style="margin-top:8px;"><p><?php echo esc_html__('Resync skipped (feature disabled).', 'yardlii-core'); ?></p></div>
    <?php endif; ?>
  
  <?php elseif ( isset($_GET['yl_rc_badges_resync']) && 'queued' === $_GET['yl_rc_badges_resync'] ) : ?>
	<div class="notice notice-info is-dismissible" style="margin-top:8px;">
		<p><?php echo esc_html__('Resync scheduled. Badges will be updated for all users in the background.', 'yardlii-core'); ?></p>
	</div>
  <?php endif; ?>
  <?php endif; ?>

</section>
