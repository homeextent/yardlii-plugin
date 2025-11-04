<?php
/**
 * Trust & Verification — Inner Panel
 * Included from SettingsPageTabs::render_page() when the TV tab is enabled.
 */




// Active subtab: prefer ?tvsection=..., fallback to 'configuration'
$tv_valid   = ['configuration', 'requests', 'tools', 'diagnostics']; // emails removed
$tv_current = isset($_GET['tvsection']) ? sanitize_key($_GET['tvsection']) : 'configuration';
if (!in_array($tv_current, $tv_valid, true)) { $tv_current = 'configuration'; }

// Helper: build tab button (with optional icon)
$btn = function (string $id, string $text, string $icon = '') use ($tv_current): string {
  $is   = ($tv_current === $id);
  $icon = $icon ? '<span class="tv-icon" aria-hidden="true">' . $icon . '</span>' : '';
  $html = $icon . '<span class="tv-text">' . esc_html($text) . '</span>';

  return sprintf(
    '<button type="button" class="yardlii-inner-tab%s" data-tvsection="%s" aria-selected="%s">%s</button>',
    $is ? ' active' : '',
    esc_attr($id),
    $is ? 'true' : 'false',
    $html
  );
};



// Helper: section class switcher
$secClass = function (string $id) use ($tv_current): string {
  return 'yardlii-inner-tabcontent' . ($tv_current === $id ? '' : ' hidden');
};
?>
<?php
$providers_doc_file = plugin_dir_path(YARDLII_CORE_FILE)
    . 'includes/Features/TrustVerification/Providers/README.txt';

$providers_doc_html = '';
if (is_readable($providers_doc_file)) {
    // Escape as text and keep formatting
    $providers_doc_html = '<pre class="yardlii-tv-doc">'
        . esc_html(file_get_contents($providers_doc_file))
        . '</pre>';
}
?>


<?php if ($providers_doc_html): ?>
  <template id="tv-providers-help"><?php echo $providers_doc_html; ?></template>
<?php endif; ?>


<div class="yardlii-inner-tabs">
  <nav class="yardlii-inner-tablist yardlii-tv-subtabs"
     aria-label="<?php esc_attr_e('Trust & Verification', 'yardlii-core'); ?>">

  <?php
    echo $btn('requests',      __('Requests', 'yardlii-core'),      '📥');
    echo $btn('configuration', __('Configuration', 'yardlii-core'), '⚙️');
    echo $btn('tools',         __('Tools', 'yardlii-core'),         '🛠️');
    echo $btn('diagnostics',   __('Diagnostics', 'yardlii-core'),   '🔍');
  ?>

  <div class="yardlii-tv-help">
    <button type="button"
            class="button-link"
            data-action="tv-show-providers-help"
            title="<?php echo esc_attr__('How Providers work', 'yardlii-core'); ?>"
            aria-label="<?php echo esc_attr__('How Providers work', 'yardlii-core'); ?>">
      ❔ <?php esc_html_e('Providers help', 'yardlii-core'); ?>
    </button>
  </div>
</nav>


  <section class="<?php echo esc_attr($secClass('configuration')); ?>" data-tvsection="configuration">
    <?php require __DIR__ . '/section-configuration.php'; ?>
  </section>

  <section class="<?php echo esc_attr($secClass('requests')); ?>" data-tvsection="requests">
    <?php require __DIR__ . '/section-requests.php'; ?>
  </section>

  <section class="<?php echo esc_attr($secClass('tools')); ?>" data-tvsection="tools">
    <?php require __DIR__ . '/section-tools.php'; ?>
  </section>

  <section class="<?php echo esc_attr($secClass('diagnostics')); ?>" data-tvsection="diagnostics">
    <?php require __DIR__ . '/section-diagnostics.php'; ?>
  </section>
</div>

<!-- Shared Email Preview Modal (present once per TV panel) -->
<div id="yardlii-tv-preview-overlay"
     class="yardlii-tv-preview-overlay"
     role="dialog"
     aria-modal="true"
     aria-labelledby="yardlii-tv-preview-title"
     aria-hidden="true">
  <div class="yardlii-tv-preview">
    <div class="yardlii-tv-preview-head">
      <h2 id="yardlii-tv-preview-title"><?php esc_html_e('Email Preview', 'yardlii-core'); ?></h2>
      <div class="tv-head-actions">
        <button type="button"
                class="button button-secondary"
                data-action="tv-preview-copy">
          <?php esc_html_e('Copy log', 'yardlii-core'); ?>
        </button>
        <button type="button"
                class="button-link"
                data-action="tv-preview-close"
                aria-label="<?php esc_attr_e('Close preview', 'yardlii-core'); ?>">×</button>
      </div>
    </div>
    <div id="yardlii-tv-preview-body"></div>
  </div>
</div>

