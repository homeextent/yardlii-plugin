<?php
/**
 * YARDLII: Advanced -> Diagnostics Panel
 *
 * This partial now contains two sections:
 * 1. Environment & Dependencies Check (NEW)
 * 2. Trust & Verification Diagnostics (Existing)
 */
defined('ABSPATH') || exit;

// --- 1. NEW: Environment & Dependencies Check ---

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

<?php // --- End New Section --- ?>


<div class="form-config-block">
  <h2><?php esc_html_e('Plugin-Wide Diagnostics', 'yardlii-core'); ?></h2>
  <p class="description">This section is a placeholder for future plugin-wide diagnostic tools.</p>
</div>

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

  <script>
  document.addEventListener('click', function(e){
    if (e.target && e.target.id === 'yardlii-tv-api-test') {
      var uid = document.getElementById('yardlii-tv-api-user').value;
      if (!uid) return;
      var endpoint = (window.wpApiSettings && wpApiSettings.root ? wpApiSettings.root : '/wp-json/') + 'yardlii/v1/verification-status/' + uid;
      
      // Use wpApiSettings nonce if available (from trust-verification.js)
      var headers = { 'X-WP-Nonce': (window.YardliiTV && YardliiTV.restNonce) ? YardliiTV.restNonce : '' };
      
      fetch(endpoint, { 
        credentials: 'same-origin',
        headers: headers
      }).then(r => r.json()).then(function(data){
        document.getElementById('yardlii-tv-api-output').textContent = JSON.stringify(data, null, 2);
      }).catch(function(err){
        document.getElementById('yardlii-tv-api-output').textContent = String(err);
      });
    }
  });
  </script>

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