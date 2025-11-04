<h2><?php esc_html_e('Diagnostics & API', 'yardlii-core'); ?></h2>

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
    fetch(endpoint, { credentials: 'same-origin' }).then(r => r.json()).then(function(data){
      document.getElementById('yardlii-tv-api-output').textContent = JSON.stringify(data, null, 2);
    }).catch(function(err){
      document.getElementById('yardlii-tv-api-output').textContent = String(err);
    });
  }
});
</script>

<?php
use Yardlii\Core\Features\TrustVerification\Requests\CPT;

echo '<h3>Debug: CPT & Statuses</h3><ul style="margin-top:.5rem;">';
echo '<li>CPT exists: <code>' . esc_html(CPT::POST_TYPE) . '</code> = ' . ( post_type_exists(CPT::POST_TYPE) ? 'yes' : 'NO' ) . '</li>';
foreach (['vp_pending','vp_approved','vp_rejected'] as $st) {
  $obj = get_post_status_object($st);
  echo '<li>Status <code>' . esc_html($st) . '</code>: ' . ($obj ? 'registered' : 'NOT registered') . '</li>';
}
echo '</ul>';

$debug = new WP_Query([
  'post_type'      => CPT::POST_TYPE,
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
?>

