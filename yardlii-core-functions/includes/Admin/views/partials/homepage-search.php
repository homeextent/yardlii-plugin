<?php
// Get taxonomies for the Listings CPT
$listings_taxonomies = get_object_taxonomies('listings', 'objects');
$taxonomy_options = [];
if (!empty($listings_taxonomies)) {
    foreach ($listings_taxonomies as $tax) {
        $taxonomy_options[$tax->name] = $tax->label;
    }
}

// Get FacetWP facets dynamically
$facet_options = [];
if (function_exists('FWP')) {
    $facet_objects = FWP()->helper->get_facets();
    if (is_array($facet_objects)) {
        foreach ($facet_objects as $facet) {
            if (!empty($facet['name'])) {
                $label = !empty($facet['label']) ? $facet['label'] : $facet['name'];
                $facet_options[$facet['name']] = $label;
            }
        }
    }
}

// Read saved options
$settings = [
    'primary_taxonomy'   => get_option('yardlii_primary_taxonomy', ''),
    'primary_label'      => get_option('yardlii_primary_label', 'All Listings'),
    'primary_facet'      => get_option('yardlii_primary_facet', ''),
    'secondary_taxonomy' => get_option('yardlii_secondary_taxonomy', ''),
    'secondary_label'    => get_option('yardlii_secondary_label', 'All Options'),
    'secondary_facet'    => get_option('yardlii_secondary_facet', ''),
    
];
?>
<div class="form-config-block">
  <h2>🔍 Homepage Search</h2>
  <form method="post" action="options.php">
    <?php settings_fields('yardlii_search_group'); ?>

    


    <h3>Primary Dropdown (Required)</h3>
    <table class="form-table" role="presentation">
      <tr>
        <th scope="row"><label for="yardlii_primary_taxonomy">Primary Taxonomy Slug</label></th>
        <td>
          <select name="yardlii_primary_taxonomy" id="yardlii_primary_taxonomy" required>
            <option value="">Select a taxonomy</option>
            <?php foreach ($taxonomy_options as $slug => $label): ?>
              <option value="<?php echo esc_attr($slug); ?>" <?php selected($settings['primary_taxonomy'], $slug); ?>>
                <?php echo esc_html($label); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </td>
      </tr>
      <tr>
        <th scope="row"><label for="yardlii_primary_label">Default Option Text</label></th>
        <td>
          <input type="text" name="yardlii_primary_label" id="yardlii_primary_label" value="<?php echo esc_attr($settings['primary_label']); ?>" class="regular-text" />
        </td>
      </tr>
      <tr>
        <th scope="row"><label for="yardlii_primary_facet">Filter / Facet Name</label></th>
        <td>
          <?php if (!empty($facet_options)) : ?>
          <select name="yardlii_primary_facet" id="yardlii_primary_facet">
            <option value="">— Use taxonomy dropdown —</option>
            <?php foreach ($facet_options as $fname => $flabel): ?>
              <option value="<?php echo esc_attr($fname); ?>" <?php selected($settings['primary_facet'], $fname); ?>>
                <?php echo esc_html($flabel); ?> (<?php echo esc_html($fname); ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <?php else: ?>
            <em>FacetWP not detected. Save will still work, but facets list is unavailable.</em>
          <?php endif; ?>
        </td>
      </tr>
    </table>

    <hr style="margin:25px 0;">

    <h3>Secondary Dropdown (Optional)</h3>
    <table class="form-table" role="presentation">
      <tr>
        <th scope="row"><label for="yardlii_secondary_taxonomy">Secondary Taxonomy Slug</label></th>
        <td>
          <select name="yardlii_secondary_taxonomy" id="yardlii_secondary_taxonomy">
            <option value="">None</option>
            <?php foreach ($taxonomy_options as $slug => $label): ?>
              <option value="<?php echo esc_attr($slug); ?>" <?php selected($settings['secondary_taxonomy'], $slug); ?>>
                <?php echo esc_html($label); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </td>
      </tr>
      <tr>
        <th scope="row"><label for="yardlii_secondary_label">Default Option Text</label></th>
        <td>
          <input type="text" name="yardlii_secondary_label" id="yardlii_secondary_label" value="<?php echo esc_attr($settings['secondary_label']); ?>" class="regular-text" />
        </td>
      </tr>
      <tr>
        <th scope="row"><label for="yardlii_secondary_facet">Filter / Facet Name</label></th>
        <td>
          <?php if (!empty($facet_options)) : ?>
          <select name="yardlii_secondary_facet" id="yardlii_secondary_facet">
            <option value="">— Use taxonomy dropdown —</option>
            <?php foreach ($facet_options as $fname => $flabel): ?>
              <option value="<?php echo esc_attr($fname); ?>" <?php selected($settings['secondary_facet'], $fname); ?>>
                <?php echo esc_html($flabel); ?> (<?php echo esc_html($fname); ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <?php else: ?>
            <em>FacetWP not detected. Save will still work, but facets list is unavailable.</em>
          <?php endif; ?>
        </td>
      </tr>
      
    </table>

    <hr style="margin: 15px 0;">

<?php
// === INSERTED: Enable Location Search toggle BEFORE the Location section ===
?>
    <table class="form-table" role="presentation">
      <tr valign="top">
        <th scope="row"><label for="yardlii_enable_location_search">Enable Location Search</label></th>
        <td>
          <label>
            <input type="checkbox"
                   name="yardlii_enable_location_search"
                   id="yardlii_enable_location_search"
                   value="1"
                   <?php checked( (bool) get_option('yardlii_enable_location_search', false), true ); ?> />
            Enable Location (Proximity) search field on homepage
          </label>
          <p class="description">Uncheck to hide the Location input and radius slider from the homepage search bar.</p>
        </td>
      </tr>
    </table>

<h3>🌍 Location Search (Proximity)</h3>
<div id="yardlii_location_settings_section">
<table class="form-table" role="presentation">
  <tr valign="top">
    <th scope="row"><label for="yardlii_location_label">Label</label></th>
    <td>
      <input type="text" id="yardlii_location_label" name="yardlii_location_label" value="<?php echo esc_attr(get_option('yardlii_location_label', 'Enter a location')); ?>" class="regular-text">
    </td>
  </tr>
  <tr valign="top">
    <th scope="row"><label for="yardlii_location_facet">FacetWP Facet Name</label></th>
    <td>
      <?php
      $facets = function_exists('FWP') ? FWP()->helper->get_facets() : [];
      $saved_facet = get_option('yardlii_location_facet', '');
      ?>
      <select name="yardlii_location_facet" id="yardlii_location_facet">
        <option value="">— Select FacetWP Proximity Facet —</option>
        <?php foreach ($facets as $facet) : ?>
          <option value="<?php echo esc_attr($facet['name']); ?>" <?php selected($saved_facet, $facet['name']); ?>>
            <?php echo esc_html($facet['label'] ?? $facet['name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p class="description">Choose the <strong>Proximity facet</strong> you’ve created in FacetWP (e.g., “location”, “proximity_search”).</p>
    </td>
  </tr>
</table>
</div>

    
<hr style="margin: 15px 0;">

<h3>🧠 Debug Tools</h3>
<p class="description">
    Enable this option to display loaded taxonomy terms (with hierarchical-only filtering and cached data) directly on the frontend for logged-in administrators only.
</p>

<label style="display:flex;align-items:center;gap:6px;margin-top:6px;">
    <?php $debug_enabled = (bool) get_option('yardlii_homepage_search_debug', false); ?>
    <input type="checkbox" name="yardlii_homepage_search_debug" value="1" <?php checked($debug_enabled, true); ?>>
    <span>Enable Homepage Search Term Debug Mode</span>
</label>
    <?php submit_button('Save Search Settings'); ?>
  </form>
</div>
