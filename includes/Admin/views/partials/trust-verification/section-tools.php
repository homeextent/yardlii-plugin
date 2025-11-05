<?php
// CSV export
$nonce = wp_create_nonce('yardlii_tv_export_nonce');
$url   = add_query_arg([
  'action'   => 'yardlii_tv_export_csv',
  '_wpnonce' => $nonce,
], admin_url('admin-post.php'));

// Seed test request (nonce-protected)
$seed_nonce = wp_create_nonce('yardlii_tv_seed_nonce');
$seed_url   = add_query_arg([
  'action'   => 'yardlii_tv_seed_request',
  '_wpnonce' => $seed_nonce,
], admin_url('admin-post.php'));

// Reset test data (nonce-protected)
$reset_nonce = wp_create_nonce('yardlii_tv_reset_seed_nonce');
$reset_url   = add_query_arg([
  'action'   => 'yardlii_tv_reset_seed_user',
  '_wpnonce' => $reset_nonce,
], admin_url('admin-post.php'));

// Notices (seed) — Tools only
if (isset($_GET['tv_seed']) && $_GET['tv_seed'] === 'ok') {
  $uid   = isset($_GET['uid'])   ? (int) $_GET['uid'] : 0;
  $email = isset($_GET['email']) ? sanitize_email(rawurldecode((string) $_GET['email'])) : '';
  $who   = $email ? sprintf(' (email: %s)', esc_html($email)) : '';
  printf(
    '<div class="yardlii-banner yardlii-banner--success yardlii-banner--dismiss"><button type="button" class="yardlii-banner__close" aria-label="%s" data-dismiss="yardlii-banner">×</button><p>%s</p></div>',
    esc_attr__('Dismiss', 'yardlii-core'),
    esc_html( sprintf('Test verification request created for user ID: %d%s', $uid, $who) )
  );
}

// Notices (reset) — Tools only
if (isset($_GET['tv_reset'])) {
  if ($_GET['tv_reset'] === 'ok') {
    $u = isset($_GET['tv_users'])    ? (int) $_GET['tv_users']    : 0;
    $r = isset($_GET['tv_requests']) ? (int) $_GET['tv_requests'] : 0;
    printf(
      '<div class="yardlii-banner yardlii-banner--success yardlii-banner--dismiss"><button type="button" class="yardlii-banner__close" aria-label="%s" data-dismiss="yardlii-banner">×</button><p>%s</p></div>',
      esc_attr__('Dismiss', 'yardlii-core'),
      esc_html( sprintf('Reset complete. Deleted %d seed user(s) and %d related request(s).', $u, $r) )
    );
  } elseif ($_GET['tv_reset'] === 'none') {
    printf(
      '<div class="yardlii-banner yardlii-banner--info yardlii-banner--dismiss"><button type="button" class="yardlii-banner__close" aria-label="%s" data-dismiss="yardlii-banner">×</button><p>%s</p></div>',
      esc_attr__('Dismiss', 'yardlii-core'),
      esc_html__('No seed users found. Nothing to reset.', 'yardlii-core')
    );
  }
}

?>





<h2><?php esc_html_e('Tools', 'yardlii-core'); ?></h2>
<p>
  <a href="<?php echo esc_url($url); ?>" class="button button-secondary">
    <?php esc_html_e('Export All Requests (CSV)', 'yardlii-core'); ?>
  </a>
</p>
<p class="description">
  <?php esc_html_e('Exports all verification requests including status, dates, and processing info.', 'yardlii-core'); ?>
</p>

<hr style="margin:16px 0;" />

<h3><?php esc_html_e('QA Utilities', 'yardlii-core'); ?></h3>
<p>
  <a href="<?php echo esc_url($seed_url); ?>" class="button">
    <?php esc_html_e('Create Test Request (Pending)', 'yardlii-core'); ?>
  </a>
<!-- New: Reset test data -->
  <a href="<?php echo esc_url($reset_url); ?>"
     class="button"
     style="background:#d63638;border-color:#b32d2e;color:#fff;"
     onclick="return confirm('Delete the test user(s) and all their verification requests? This cannot be undone.');">
    <?php esc_html_e('Reset Test Data (delete seed user + requests)', 'yardlii-core'); ?>
  </a>
</p>
<p class="description">
  Deletes any user(s) flagged with <code>_yardlii_tv_seed_user = 1</code> and all <code>verification_request</code> posts whose <code>_vp_user_id</code> matches. Skips the current user and site admins.
</p>

<hr style="margin:16px 0;" />

<div class="yardlii-section">
  <div class="yardlii-section-head">
    <h3><?php esc_html_e('Send test for all forms', 'yardlii-core'); ?></h3>
  </div>
  <div class="yardlii-section-content">
    <p><?php esc_html_e('Sends one test email per configured form. Use for rapid end-to-end smoke tests.', 'yardlii-core'); ?></p>
    <p>
      <label>
        <strong><?php esc_html_e('Recipient', 'yardlii-core'); ?></strong><br>
        <input type="email" id="tv-testall-to" class="regular-text" placeholder="<?php echo esc_attr(get_bloginfo('admin_email')); ?>">
      </label>
    </p>
    <p>
      <label><input type="radio" name="tv-testall-type" value="approve" checked> <?php esc_html_e('Approve template', 'yardlii-core'); ?></label>
      &nbsp;&nbsp;
      <label><input type="radio" name="tv-testall-type" value="reject"> <?php esc_html_e('Reject template', 'yardlii-core'); ?></label>
      &nbsp;&nbsp;
      <label><input type="radio" name="tv-testall-type" value="both"> <?php esc_html_e('Both', 'yardlii-core'); ?></label>
    </p>
    <p>
      <button type="button" class="button button-primary" id="tv-send-all-tests">
        <?php esc_html_e('Send test for all forms', 'yardlii-core'); ?>
      </button>
    </p>
  </div>
</div>

