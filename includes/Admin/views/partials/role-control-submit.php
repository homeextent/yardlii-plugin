<?php if (!defined('ABSPATH')) exit; ?>
<?php if (function_exists('settings_errors')) settings_errors('yardlii_role_control_group'); ?>

<?php
/**
 * Role Control – top level tab UI
 */
$enabled        = (bool) get_option('yardlii_enable_role_control_submit', false);
$allowed        = (array) get_option('yardlii_role_control_allowed_roles', []);
$action         = (string) get_option('yardlii_role_control_denied_action', 'message');
$message        = (string) get_option('yardlii_role_control_denied_message', 'You do not have permission to access this page.');
$target_page    = (string) get_option('yardlii_role_control_target_page', 'submit-a-post');

$editable_roles = get_editable_roles(); // [ 'role_slug' => ['name' => ...], ... ]
?>

<?php
$enable_custom  = (bool) get_option('yardlii_enable_custom_roles', true);
$saved_roles    = (array) get_option('yardlii_custom_roles', []);
$editable_roles = get_editable_roles();
$all_role_choices = array_keys($editable_roles); // for base clone list
?>

<div class="form-config-block">
  <h2>🛡️ Submit Access</h2>
  <p class="description">Restrict who can access the front-end <strong>Submit a Post</strong> page by role.</p>

  <form method="post" action="options.php" class="yardlii-settings-form">
    <?php settings_fields('yardlii_role_control_group'); ?>

    <table class="form-table" role="presentation">
      <tr>
        <th scope="row">Enable restriction</th>
        <td>
          <label class="yardlii-toggle">
            <input type="checkbox" name="yardlii_enable_role_control_submit" value="1" <?php checked((bool) get_option('yardlii_enable_role_control_submit', false)); ?> />
            <span class="yardlii-toggle-slider"></span>
          </label>
          <p class="description">Only selected roles will be allowed to access the target page.</p>
        </td>
      </tr>

      <tr>
        <th scope="row">Target page (slug)</th>
        <td>
          <input type="text" class="regular-text" name="yardlii_role_control_target_page" value="<?php echo esc_attr((string) get_option('yardlii_role_control_target_page', 'submit-a-post')); ?>" />
          <p class="description">Default: <code>submit-a-post</code></p>
        </td>
      </tr>

      <tr>
        <th scope="row">Allowed roles</th>
        <td>
          <?php $editable = get_editable_roles(); $allowed = (array) get_option('yardlii_role_control_allowed_roles', []); ?>
          <select name="yardlii_role_control_allowed_roles[]" multiple size="6" style="min-width:280px;">
            <?php foreach ($editable as $slug => $data): ?>
              <option value="<?php echo esc_attr($slug); ?>" <?php selected(in_array($slug, $allowed, true)); ?>>
                <?php echo esc_html($data['name'] ?? $slug); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <p class="description">Leave empty to allow <em>any logged-in user</em>.</p>
        </td>
      </tr>

      <tr>
        <th scope="row">If access is denied</th>
        <td>
          <?php $action = (string) get_option('yardlii_role_control_denied_action', 'message'); ?>
          <fieldset>
            <label>
              <input type="radio" name="yardlii_role_control_denied_action" value="message" <?php checked($action, 'message'); ?> />
              Show message
            </label>
            &nbsp;&nbsp;
            <label>
              <input type="radio" name="yardlii_role_control_denied_action" value="redirect_login" <?php checked($action, 'redirect_login'); ?> />
              Redirect to login
            </label>
          </fieldset>
        </td>
      </tr>

      <tr>
        <th scope="row">Denied message</th>
        <td>
          <textarea name="yardlii_role_control_denied_message" rows="4" cols="60"><?php echo esc_textarea((string) get_option('yardlii_role_control_denied_message', 'You do not have permission to access this page.')); ?></textarea>
          <p class="description">Used only when “Show message” is selected.</p>
        </td>
      </tr>
    </table>

    <?php submit_button('Save Submit Access'); ?>
  </form>
</div>
