<?php
// Inside render_page(), right after <div class="wrap">
if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
    echo '<div class="notice notice-success is-dismissible"><p><strong>Settings saved successfully.</strong></p></div>';
}

// At bottom of render_page(), before closing </div> tag
echo '<footer class="yardlii-admin-footer">';
echo 'YARDLII Core Functions v' . esc_html(YARDLII_CORE_VERSION);
echo ' — Last updated: ' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format')));
echo '</footer>';
?>