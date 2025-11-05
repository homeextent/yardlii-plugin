<?php
/**
 * Yardlii Admin Settings → General Tab → WPUF Customisations
 * -----------------------------------------------------------
 * Adds a toggle for enabling/disabling the WPUF Enhanced Dropdown feature.
 *
 * @since 1.0.0
 */
?>

<h2>🔍 WPUF Customisations</h2>
<p class="description">
  Configure front-end listing form behavior for WP User Frontend integrations.
</p>

<form method="post" action="options.php" class="yardlii-settings-form">
  <?php
    settings_fields('yardlii_general_group');
    $dropdown_enabled = (bool) get_option('yardlii_enable_wpuf_dropdown', true);
  ?>

  <table class="form-table yardlii-table">
    <tr valign="top">
      <th scope="row">
        <label for="yardlii_enable_wpuf_dropdown">
          Enable Enhanced Dropdown
        </label>
      </th>
      <td>
        <label class="yardlii-toggle">
          <input type="checkbox" name="yardlii_enable_wpuf_dropdown" value="1" <?php checked($dropdown_enabled, true); ?> />
          <span class="yardlii-toggle-slider"></span>
        </label>
        <p class="description">
          When enabled, replaces the default WPUF taxonomy select field (Group) with the
          custom Yardlii Enhanced Dropdown interface on the <strong>Submit a Post</strong> form.
        </p>
      </td>
    </tr>
  </table>

  <?php submit_button('Save WPUF Settings'); ?>
</form>
