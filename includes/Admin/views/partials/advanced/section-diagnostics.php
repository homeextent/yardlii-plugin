<?php
/**
 * YARDLII: Advanced -> Diagnostics Panel
 *
 * This partial now contains four sections:
 * 1. Environment & Dependencies Check
 * 2. Feature Flag Status
 * 3. Role Control & Badges Diagnostics
 * 4. Trust & Verification Diagnostics
 */
defined('ABSPATH') || exit;

// --- 1. Environment & Dependencies Check ---

// Helper function for this template
if (!function_exists('yardlii_diag_check')) {
    /**
     * Renders a single diagnostic line.
     * @param string $name      The label for the check (e.g., "ACF Pro")
     * @param bool   $is_ok     The result of the check
     * @param string $ok_text   Text to show if OK
     * @param string $fail_text Text to show if failed
     */
    function yardlii_diag_check(string $name, bool $is_ok, string $ok_text, string $fail_text): void {
        $icon = $is_ok ? '✅' : '❌';
        $text = $is_ok ? esc_html($ok_text) : '<strong>' . esc_html($fail_text) . '</strong>';
        $class = $is_ok ? 'status-ok' : 'status-fail';
        
        echo "<li class='{$class}'><strong>{$icon} {$name}:</strong> {$text}</li>";
    }
}

// Ensure is_plugin_active() is available
if (!function_exists('is_plugin_active')) {
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

// Define our dependencies
$dependencies = [
    'acf_pro' => [
        'name' => 'ACF Pro',
        'active' => is_plugin_active('advanced-custom-fields-pro/acf.php'),
        'ok' => 'Active',
        'fail' => 'NOT FOUND. Required for most features.'
    ],
    'acf_options' => [
        'name' => 'ACF Options Page',
        'active' => function_exists('acf_add_options_page'),
        'ok' => 'Available',
        'fail' => 'NOT FOUND. Required for Badge Assignment.'
    ],
    'wpuf_pro' => [
        'name' => 'WPUF Pro',
        'active' => is_plugin_active('wp-user-frontend-pro/wpuf-pro.php') || is_plugin_active('wp-user-frontend/wpuf.php'), // Check for free or pro
        'ok' => 'Active',
        'fail' => 'NOT FOUND. Required for Featured Image & TV Providers.'
    ],
    'facetwp' => [
        'name' => 'FacetWP',
        'active' => is_plugin_active('facetwp/index.php') && function_exists('FWP'),
        'ok' => 'Active',
        'fail' => 'NOT FOUND. Required for Homepage Search.'
    ],
    'elementor_pro' => [
        'name' => 'Elementor Pro',
        'active' => is_plugin_active('elementor-pro/elementor-pro.php'),
        'ok' => 'Active',
        'fail' => 'NOT FOUND. (Required for TV Elementor Provider)'
    ],
    'action_scheduler' => [
        'name' => 'Action Scheduler',
        'active' => function_exists('as_enqueue_async_action'),
        'ok' => 'Loaded (via Composer)',
        'fail' => 'NOT FOUND. Required for Badge Resync.'
    ],
];
?>
<style>
    .yardlii-diag-list { list-style-type: none; margin: 0; padding: 0; }
    .yardlii-diag-list li { padding: 8px 0; border-bottom: 1px dashed #e0e0e0; font-size: 14px; }
    .yardlii-diag-list li:last-child { border-bottom: 0; }
    .yardlii-diag-list li.status-fail { color: #d63638; }
    .yardlii-diag-list li.status-ok { color: #00a32a; }
</style>

<div class="form-config-block">
  <h2>🌍 Environment & Dependencies Check</h2>
  <p class="description">A quick check to ensure all required plugins and components are active.</p>
  <ul class="yardlii-diag-list" style="margin-top: 15px;">
      <?php
      foreach ($dependencies as $check) {
          yardlii_diag_check($check['name'], $check['active'], $check['ok'], $check['fail']);
      }
      ?>
  </ul>
</div>

<?php // --- End Section 1 --- ?>


<?php
// --- 2. Feature Flag Status Check ---

// Helper function for this template
if (!function_exists('yardlii_diag_flag_status_row')) {
    /**
     * Renders a single row for the feature flag status table.
     *
     * @param string $label            The user-friendly name of the feature.
     * @param string $option_key       The wp_options key for the flag.
     * @param string $constant_name    The name of the constant that can override it.
     * @param bool   $master_depends   (Optional) If this feature depends on a master flag.
     * @param bool   $master_effective (Optional) The effective status of the master flag.
     */
    function yardlii_diag_flag_status_row(
        string $label,
        string $option_key,
        string $constant_name = '',
        bool $master_depends = false,
        bool $master_effective = true
    ): void {
        $option_val = (bool) get_option($option_key, false);
        $const_defined = ($constant_name !== '') && defined($constant_name);
        
        $option_status = $option_val ? '<code>true</code>' : '<code>false</code>';
        $const_status = '<em>Not Set</em>';
        $effective_status = $option_val;

        if ($const_defined) {
            $const_val = (bool) constant($constant_name);
            $const_status = 'Set to <code>' . ($const_val ? 'true' : 'false') . '</code>';
            $effective_status = $const_val; // Constant overrides option
        }

        // Check master dependency
        if ($master_depends && !$master_effective) {
            $effective_status = false; // Overridden by master
            $final_status = '<span style="color:#999;font-style:italic;">Disabled (Master Off)</span>';
        } else {
            $final_status = $effective_status
                ? '<span style="color:#00a32a;"><strong>Running</strong></span>'
                : '<span style="color:#d63638;"><strong>Disabled</strong></span>';
        }

        echo '<tr>';
        echo '<td>' . esc_html($label) . '</td>';
        echo '<td>' . $option_status . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<td>' . $const_status . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<td>' . $final_status . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</tr>';
    }
}

// Get the effective status of the Role Control master flag
$rc_master_effective = (bool) get_option('yardlii_enable_role_control', false);
if (defined('YARDLII_ENABLE_ROLE_CONTROL')) {
    $rc_master_effective = (bool) YARDLII_ENABLE_ROLE_CONTROL;
}

?>

<div class="form-config-block">
  <h2>🚦 Feature Flag Status</h2>
  <p class="description">Shows the runtime status of each module. A defined constant (e.g., in <code>wp-config.php</code>) will always override the database option.</p>
  <table class="wp-list-table widefat striped" style="margin-top: 15px;">
    <thead>
      <tr>
        <th scope="col"><?php esc_html_e('Feature Module', 'yardlii-core'); ?></th>
        <th scope="col"><?php esc_html_e('Option Status (DB)', 'yardlii-core'); ?></th>
        <th scope="col"><?php esc_html_e('Constant Override', 'yardlii-core'); ?></th>
        <th scope="col"><?php esc_html_e('Effective Status', 'yardlii-core'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php
      yardlii_diag_flag_status_row(
          'Trust & Verification',
          'yardlii_enable_trust_verification',
          'YARDLII_ENABLE_TRUST_VERIFICATION'
      );
      yardlii_diag_flag_status_row(
          'Role Control (Master)',
          'yardlii_enable_role_control',
          'YARDLII_ENABLE_ROLE_CONTROL'
      );
      yardlii_diag_flag_status_row(
          '— Role: Submit Access',
          'yardlii_enable_role_control_submit',
          '',
          true,
          $rc_master_effective
      );
      yardlii_diag_flag_status_row(
          '— Role: Custom User Roles',
          'yardlii_enable_custom_roles',
          '',
          true,
          $rc_master_effective
      );
      yardlii_diag_flag_status_row(
          '— Role: Badge Assignment',
          'yardlii_enable_badge_assignment',
          '',
          true,
          $rc_master_effective
      );
      yardlii_diag_flag_status_row(
          'Featured Listings Logic',
          'yardlii_enable_featured_listings',
          ''
      );
      yardlii_diag_flag_status_row(
          'WPUF: Enhanced Dropdown',
          'yardlii_enable_wpuf_dropdown',
          ''
      );
      yardlii_diag_flag_status_row(
          'WPUF: Card-Style Layout',
          'yardlii_wpuf_card_layout',
          ''
      );
      yardlii_diag_flag_status_row(
          'WPUF: Modern Uploader',
          'yardlii_wpuf_modern_uploader',
          ''
      );
      ?>
    </tbody>
  </table>
</div>

<?php // --- End Section 2 --- ?>


<?php
// --- 3. Role Control & Badges Diagnostics ---

$rc_diag = [
    'acf_options' => [
        'name' => 'ACF Options Page',
        'active' => function_exists('acf_add_options_page'),
        'ok' => 'Available',
        'fail' => 'NOT FOUND. Required for Badge Assignment.'
    ],
    'action_scheduler' => [
        'name' => 'Action Scheduler',
        'active' => function_exists('as_get_scheduled_actions'),
        'ok' => 'Loaded',
        'fail' => 'NOT FOUND. Required for "Resync All Users".'
    ],
];

$pending_actions = 0;
$failed_actions = 0;
if ($rc_diag['action_scheduler']['active']) {
    $pending_actions = (int) as_get_scheduled_actions([
        'hook' => 'yardlii_rc_resync_user_badge',
        'status' => \ActionScheduler_Store::STATUS_PENDING,
        'group' => 'yardlii-rc-badges',
    ], 'count');
    
    $failed_actions = (int) as_get_scheduled_actions([
        'hook' => 'yardlii_rc_resync_user_badge',
        'status' => \ActionScheduler_Store::STATUS_FAILED,
        'group' => 'yardlii-rc-badges',
    ], 'count');
}
?>
<div class="form-config-block">
  <h2>🛡️ Role Control & Badges Diagnostics</h2>
  <ul class="yardlii-diag-list" style="margin-top: 15px;">
      <?php
      foreach ($rc_diag as $check) {
          yardlii_diag_check($check['name'], $check['active'], $check['ok'], $check['fail']);
      }
      
      // Action Scheduler Queue Status
      if ($rc_diag['action_scheduler']['active']) {
          $fail_text = sprintf('<strong>%d Failed Actions</strong>. Check Action Scheduler logs.', $failed_actions);
          yardlii_diag_check(
              'Badge Resync Queue',
              ($failed_actions === 0),
              sprintf('%d Pending Actions', $pending_actions),
              $fail_text
          );
      }
      ?>
  </ul>

  <hr style="margin: 15px 0;">
  
  <h3>Test Badge Sync</h3>
  <p class="description">Run the <code>sync_user_badge()</code> function for a specific user.</p>
  <label>
      <span><?php esc_html_e('User ID', 'yardlii-core'); ?></span>
      <input type="number" id="yardlii-diag-badge-user" min="1" step="1" class="small-text" placeholder="e.g., 123" />
  </label>
  <button type="button" class="button" id="yardlii-diag-badge-test" style="margin-left:1rem;">
      <?php esc_html_e('Run Sync for User', 'yardlii-core'); ?>
  </button>
  <pre id="yardlii-diag-badge-output" style="margin-top:1rem;max-height:100px;overflow:auto;background:#f6f7f7;padding:10px;border:1px solid #dcdcde;display:none;"></pre>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Badge Sync Test
    var badgeBtn = document.getElementById('yardlii-diag-badge-test');
    var badgeOut = document.getElementById('yardlii-diag-badge-output');

    if (badgeBtn && badgeOut && window.YARDLII_ADMIN && window.YARDLII_ADMIN.nonce_badge_sync) {
        badgeBtn.addEventListener('click', function() {
            var uid = document.getElementById('yardlii-diag-badge-user').value;
            if (!uid) {
                badgeOut.textContent = 'Error: Please enter a User ID.';
                badgeOut.style.color = '#d63638';
                badgeOut.style.display = 'block';
                return;
            }

            badgeOut.textContent = 'Running sync...';
            badgeOut.style.color = '#555';
            badgeOut.style.display = 'block';

            fetch(YARDLII_ADMIN.ajaxurl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    'action': 'yardlii_diag_test_badge_sync',
                    'nonce': YARDLII_ADMIN.nonce_badge_sync,
                    'user_id': uid
                })
            })
            .then(res => res.json())
            .then(data => {
                var msg = data.message || (data.data ? data.data.message : 'Unknown response.');
                badgeOut.textContent = msg;
                badgeOut.style.color = data.success ? '#0073aa' : '#d63638';
            })
            .catch(err => {
                badgeOut.textContent = 'AJAX request failed: ' + err;
                badgeOut.style.color = '#d63638';
            });
        });
    }

    // TV API Test (from original file)
    var tvApiBtn = document.getElementById('yardlii-tv-api-test');
    var tvApiOut = document.getElementById('yardlii-tv-api-output');
    
    if (tvApiBtn && tvApiOut) {
        tvApiBtn.addEventListener('click', function() {
            var uid = document.getElementById('yardlii-tv-api-user').value;
            if (!uid) {
                tvApiOut.textContent = 'Error: Please enter a User ID.';
                tvApiOut.style.color = '#d63638';
                return;
            }
            tvApiOut.textContent = 'Testing API...'; // Clear previous
            
            var endpoint = (window.wpApiSettings && wpApiSettings.root ? wpApiSettings.root : '/wp-json/') + 'yardlii/v1/verification-status/' + uid;
            var headers = { 'X-WP-Nonce': (window.YardliiTV && YardliiTV.restNonce) ? YardliiTV.restNonce : '' };
            
            fetch(endpoint, { 
                credentials: 'same-origin',
                headers: headers
            }).then(r => r.json()).then(function(data){
                tvApiOut.textContent = JSON.stringify(data, null, 2);
            }).catch(function(err){
                tvApiOut.textContent = String(err);
            });
        });
    }
});
</script>

<?php // --- End Section 3 --- ?>


<div class="form-config-block">
  <h2><?php esc_html_e('Trust & Verification: Diagnostics', 'yardlii-core'); ?></h2>

  <div class="yardlii-tv-diagnostics">
    <p class="description">
      <?php esc_html_e('Quickly test the REST endpoint to check a user’s verification status.', 'yardlii-core'); ?>
    </p>

    <label>
      <span><?php esc_html_e('User ID', 'yardlii-core'); ?></span>
      <input type="number" id="yardlii-tv-api-user" min="1" step="1" class="small-text" placeholder="e.g., 123" />
    </label>

    <button type="button" class="button" id="yardlii-tv-api-test" style="margin-left:1rem;">
      <?php esc_html_e('Test API', 'yardlii-core'); ?>
    </button>

    <pre id="yardlii-tv-api-output" style="margin-top:1rem;max-height:220px;overflow:auto;background:#f6f7f7;padding:10px;border:1px solid #dcdcde;"></pre>
  </div>

  <?php
  // This script tag is now moved inside the new DOMContentLoaded listener above
  ?>

  <?php
  if (class_exists('\Yardlii\Core\Features\TrustVerification\Requests\CPT')) {
      $cpt_slug = \Yardlii\Core\Features\TrustVerification\Requests\CPT::POST_TYPE;

      echo '<h3>Debug: CPT & Statuses</h3><ul style="margin-top:.5rem;">';
      echo '<li>CPT exists: <code>' . esc_html($cpt_slug) . '</code> = ' . ( post_type_exists($cpt_slug) ? 'yes' : 'NO' ) . '</li>';
      foreach (['vp_pending','vp_approved','vp_rejected'] as $st) {
          $obj = get_post_status_object($st);
          echo '<li>Status <code>' . esc_html($st) . '</code>: ' . ($obj ? 'registered' : 'NOT registered') . '</li>';
      }
      echo '</ul>';

      $debug = new WP_Query([
          'post_type'      => $cpt_slug,
          'post_status'    => 'any',
          'posts_per_page' => 5,
          'orderby'        => 'date',
          'order'          => 'DESC',
          'fields'         => 'ids',
      ]);

      echo '<h3>Last 5 verification_request posts (any status)</h3>';
      if ($debug->have_posts()) {
          echo '<ol>';
          foreach ($debug->posts as $pid) {
              $st = get_post_status($pid);
              $uid = (int) get_post_meta($pid, '_vp_user_id', true);
              $fid = (string) get_post_meta($pid, '_vp_form_id', true);
              echo '<li>#' . (int)$pid . ' — status: <code>' . esc_html($st) . '</code> — user_id: ' . (int)$uid . ' — form_id: ' . esc_html($fid) . '</li>';
          }
          echo '</ol>';
      } else {
          echo '<p>No posts found.</p>';
      }
  } else {
      echo '<p>Trust & Verification CPT class not found.</p>';
  }
  ?>
</div>