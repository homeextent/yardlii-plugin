<div class="form-config-block">
  <h2>⚙️ Map Display Options</h2>
  <form method="post" action="options.php" style="display:block;">
    <?php
      settings_fields('yardlii_google_map_group');
      $map_controls = get_option('yardlii_map_controls', []);
      if (!is_array($map_controls)) { $map_controls = []; }
      $checked = function($key) use ($map_controls) {
        return (isset($map_controls[$key]) && (int)$map_controls[$key] === 1) ? 'checked' : '';
      };
    ?>
    <div class="field-grid" style="display:grid;grid-template-columns:repeat(2,minmax(240px,1fr));gap:10px;align-items:center;">
      <label><input type="checkbox" name="yardlii_map_controls[zoomControl]" value="1" <?php echo $checked('zoomControl'); ?>> Zoom Control</label>
      <label><input type="checkbox" name="yardlii_map_controls[rotateControl]" value="1" <?php echo $checked('rotateControl'); ?>> Camera Control</label>
      <label><input type="checkbox" name="yardlii_map_controls[mapTypeControl]" value="1" <?php echo $checked('mapTypeControl'); ?>> Map Type Toggle</label>
      <label><input type="checkbox" name="yardlii_map_controls[streetViewControl]" value="1" <?php echo $checked('streetViewControl'); ?>> Street View</label>
      <label><input type="checkbox" name="yardlii_map_controls[fullscreenControl]" value="1" <?php echo $checked('fullscreenControl'); ?>> Fullscreen Button</label>
      <label><input type="checkbox" name="yardlii_map_controls[disableDefaultUI]" value="1" <?php echo $checked('disableDefaultUI'); ?>> Disable Default UI</label>
    </div>
    <?php submit_button('Save Map Display Options'); ?>
    <p class="description" style="margin-top:6px;">
      These settings control the visible UI elements on FacetWP-powered maps. “Camera Control” maps to Google’s <code>rotateControl</code>.
    </p>
  </form>
</div>
