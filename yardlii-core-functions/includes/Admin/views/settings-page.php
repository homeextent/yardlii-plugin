<?php
// Basic scaffold view. Replace with full Settings API forms as needed.
?>
<div class="wrap">
  <h1><?php echo esc_html__('YARDLII Core Settings', 'yardlii-core'); ?></h1>
  <p><?php echo esc_html__('This is the modular scaffold. Add your sections/forms here.', 'yardlii-core'); ?></p>

  <div class="card">
    <h2><?php echo esc_html__('Google Maps API', 'yardlii-core'); ?></h2>
    <p><?php echo esc_html__('Managed by the GoogleMapKey module. Configure via your preferred settings storage.', 'yardlii-core'); ?></p>
  </div>

  <div class="card">
    <h2><?php echo esc_html__('Featured Image Automation', 'yardlii-core'); ?></h2>
    <p><?php echo esc_html__('Managed by the FeaturedImage module. Ensure your ACF gallery field matches configuration.', 'yardlii-core'); ?></p>
  </div>

  <div class="card">
    <h2><?php echo esc_html__('Homepage Search', 'yardlii-core'); ?></h2>
    <p><?php echo esc_html__('Managed by the HomepageSearch module. Shortcodes are registered and ready.', 'yardlii-core'); ?></p>
  </div>

  <div class="card">
    <h2><?php echo esc_html__('ACF User Sync', 'yardlii-core'); ?></h2>
    <p><?php echo esc_html__('Managed by the ACFUserSync module. Add admin UI and AJAX endpoints here.', 'yardlii-core'); ?></p>
  </div>

  <div class="yardlii-tabpanel <?php echo $active_tab === 'wpuf' ? '' : 'hidden'; ?>" data-panel="wpuf">
  <?php include __DIR__ . '/views/partials/wpuf-customizations.php'; ?>
</div>

</div>
