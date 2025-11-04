<?php
if ( isset($_GET['tv_notice']) ) {
    $map = [
        'approve'      => __('Request approved.', 'yardlii-core'),
        'reject'       => __('Request rejected.', 'yardlii-core'),
        'reopen'       => __('Request reopened (back to Pending).', 'yardlii-core'),
        'resend'       => __('Decision email resent.', 'yardlii-core'),
        'bulk_approve' => __('Selected requests approved.', 'yardlii-core'),
        'bulk_reject'  => __('Selected requests rejected.', 'yardlii-core'),
        'bulk_reopen'  => __('Selected requests reopened.', 'yardlii-core'),
        'bulk_resend'  => __('Decision emails resent for selected.', 'yardlii-core'),
    ];
    $notice = sanitize_key($_GET['tv_notice']);
    $msg    = $map[$notice] ?? '';

    if ($msg) {
        // Only show "(N items)" for bulk_* notices
        if (strpos($notice, 'bulk_') === 0) {
            $count = isset($_GET['tv_count']) ? (int) $_GET['tv_count'] : 0;
            if ($count > 0) {
                $msg .= ' ' . sprintf(_n('(%d item)', '(%d items)', $count, 'yardlii-core'), $count);
            }
        }

        printf(
          '<div class="yardlii-banner yardlii-banner--success yardlii-banner--dismiss">' .
          '<button type="button" class="yardlii-banner__close" data-dismiss="yardlii-banner" aria-label="%s">×</button>' .
          '<p>%s</p></div>',
          esc_attr__('Dismiss', 'yardlii-core'),
          esc_html($msg)
        );
    }
}
?>


<?php
use Yardlii\Core\Features\TrustVerification\Requests\ListTable;

$table = new ListTable();
$table->prepare_items();
?>

<div class="yardlii-tv-requests-header">
  <?php $table->display_views(); ?>
  <form method="get">
    <?php
    foreach (['page','tab'] as $kept) {
      if (isset($_GET[$kept])) {
        printf('<input type="hidden" name="%s" value="%s" />', esc_attr($kept), esc_attr($_GET[$kept]));
      }
    }
    $table->search_box(__('Search Requests', 'yardlii-core'), 'yardlii-tv-search');
    ?>
    <input type="hidden" name="tvsection" value="requests" />
  </form>
</div>

<form method="post" action="<?php echo esc_url( admin_url('admin.php?page=yardlii-core-settings') ); ?>">
  <input type="hidden" name="tab" value="trust-verification" />
  <input type="hidden" name="tvsection" value="requests" />

  <?php
    // IMPORTANT:
    // - Do NOT print your own nonce here.
    // - $table->display() outputs the correct core bulk nonce (_wpnonce with action "bulk-verification_requests")
    //   so your handler can use: check_admin_referer('bulk-' . $this->_args['plural']);
    $table->display();
  ?>
</form>


<p class="description">
  <?php esc_html_e('Tip: Use the bulk actions above the table to approve, reject, re-open, or resend emails for multiple requests at once.', 'yardlii-core'); ?>
</p>
