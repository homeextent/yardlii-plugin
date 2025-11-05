<div class="form-config-block">
  <h2>🔑 Google Maps API Key</h2>
  <form method="post" action="options.php">
    <?php
      settings_fields('yardlii_google_map_group');
      $api_key = get_option('yardlii_google_map_key', '');
    ?>
    <label>Google Maps API Key:</label>
    <input type="text" name="yardlii_google_map_key" value="<?php echo esc_attr($api_key); ?>" placeholder="Enter Google Maps API key" class="regular-text">
    <?php submit_button('Save Google Maps Key'); ?>
  </form>

  <hr>
  <h3>🔍 Test & Diagnostics</h3>
  <p>Use this tool to verify that your saved Google Maps API key is valid and reachable from this site.</p>

  <button type="button" class="button button-secondary" id="yardlii-test-api-key">Test API Key</button>
  <div id="yardlii-test-result" style="margin-top:10px;font-weight:600;"></div>

  

  <script>
  document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('yardlii-test-api-key');
    const result = document.getElementById('yardlii-test-result');
    if (btn) {
      btn.addEventListener('click', function() {
        result.textContent = 'Testing API key...';
        result.style.color = '#555';
        fetch(ajaxurl, {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: new URLSearchParams({
            action: 'yardlii_test_google_map_key',
            _ajax_nonce: '<?php echo wp_create_nonce('yardlii_test_google_map_key'); ?>'
          })
        })
        .then(res => res.json())
        .then(data => {
          result.textContent = data.message || 'Unknown response.';
          result.style.color = data.success ? '#00a32a' : '#d63638';
        })
        .catch(() => {
          result.textContent = '❌ AJAX request failed.';
          result.style.color = '#d63638';
        });
      });
    }

  });
  </script>
</div>
