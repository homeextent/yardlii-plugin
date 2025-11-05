<?php if (!defined('ABSPATH')) exit; ?>

<?php if (function_exists('settings_errors')) settings_errors('yardlii_role_control_group'); ?>

<?php
$enable_custom  = (bool) get_option('yardlii_enable_custom_roles', false);
$saved_roles    = (array) get_option('yardlii_custom_roles', []);

// Ensure get_editable_roles() is available
if (!function_exists('get_editable_roles')) {
    require_once ABSPATH . 'wp-admin/includes/user.php';
}
$editable_roles   = get_editable_roles();            // ['slug' => ['name' => ...], ...]
$all_role_choices = array_keys($editable_roles);     // used for the "Clone caps from" select
?>


<div class="form-config-block">
  <h2>👥 Custom User Roles</h2>
  <p class="description">Define custom roles. Removing a row and saving will also remove that role from WordPress. Users holding the role will be reassigned to <strong>Subscriber</strong>.</p>
   <?php
   // Display Settings API notices for this group (e.g., master off / feature off)
    if (function_exists('settings_errors')) {
     settings_errors('yardlii_role_control_group');
    }
    ?>
  <form method="post" action="options.php" class="yardlii-settings-form" id="yardlii-custom-roles-form">
  
    <?php settings_fields('yardlii_role_control_group'); ?>

    <table class="form-table" role="presentation">
      <tr>
        <th scope="row">Enable custom roles</th>
        <td>
          <label class="yardlii-toggle">
  <input type="hidden" name="yardlii_enable_custom_roles" value="0" />
  <input type="checkbox" name="yardlii_enable_custom_roles" value="1" <?php checked($enable_custom, true); ?> />
  <span class="yardlii-toggle-slider"></span>
</label>
          <p class="description">When disabled, roles you created remain in WordPress, but this plugin won’t try to sync them.</p>
        </td>
      </tr>

      <tr>
        <th scope="row">Roles</th>
        <td>
          <div id="yardlii-roles-repeater">
            <?php
            $i = 0;
            foreach ($saved_roles as $slug => $data):
              $label = $data['label'] ?? $slug;
              $base  = $data['base_role'] ?? '';
              $extra = $data['extra_caps'] ?? '';
            ?>
              <div class="role-row" data-index="<?php echo esc_attr($i); ?>" style="margin-bottom:12px;padding:12px;border:1px solid #e5e5e5;border-radius:8px;">
                <div style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
                  <div>
                    <label>Role Key (slug)</label><br>
                    <input type="text" name="yardlii_custom_roles[<?php echo esc_attr($i); ?>][slug]" value="<?php echo esc_attr($slug); ?>" class="regular-text" required />
                    <p class="description">Use lowercase letters, numbers, and dashes. Example: <code>property-manager</code></p>
                  </div>

                  <div>
                    <label>Display Name</label><br>
                    <input type="text" name="yardlii_custom_roles[<?php echo esc_attr($i); ?>][label]" value="<?php echo esc_attr($label); ?>" class="regular-text" required />
                  </div>

                  <div>
                    <label>Clone caps from</label><br>
                    <select name="yardlii_custom_roles[<?php echo esc_attr($i); ?>][base_role]">
                      <option value="">— None (start empty) —</option>
                      <?php foreach ($editable_roles as $rslug => $rdata): ?>
                        <option value="<?php echo esc_attr($rslug); ?>" <?php selected($base === $rslug); ?>>
                          <?php echo esc_html($rdata['name'] ?? $rslug); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div style="min-width:320px;">
                    <label>Extra caps (comma or space separated)</label><br>
                    <input type="text" name="yardlii_custom_roles[<?php echo esc_attr($i); ?>][extra_caps]" value="<?php echo esc_attr($extra); ?>" class="regular-text" placeholder="read, edit_posts, upload_files" />
                  </div>

                  <button type="button" class="button link-delete remove-role-row" aria-label="Remove role">Remove</button>
                </div>
              </div>
            <?php
              $i++;
            endforeach;
            ?>

            <!-- Empty state row if none saved -->
            <?php if ($i === 0): ?>
              <div class="role-row" data-index="0" style="margin-bottom:12px;padding:12px;border:1px solid #e5e5e5;border-radius:8px;">
                <div style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
                  <div>
                    <label>Role Key (slug)</label><br>
                    <input type="text" name="yardlii_custom_roles[0][slug]" value="" class="regular-text" required />
                  </div>
                  <div>
                    <label>Display Name</label><br>
                    <input type="text" name="yardlii_custom_roles[0][label]" value="" class="regular-text" required />
                  </div>
                  <div>
                    <label>Clone caps from</label><br>
                    <select name="yardlii_custom_roles[0][base_role]">
                      <option value="">— None (start empty) —</option>
                      <?php foreach ($editable_roles as $rslug => $rdata): ?>
                        <option value="<?php echo esc_attr($rslug); ?>">
                          <?php echo esc_html($rdata['name'] ?? $rslug); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div style="min-width:320px;">
                    <label>Extra caps (comma or space separated)</label><br>
                    <input type="text" name="yardlii_custom_roles[0][extra_caps]" value="" class="regular-text" placeholder="read, edit_posts, upload_files" />
                  </div>
                  <button type="button" class="button link-delete remove-role-row" aria-label="Remove role">Remove</button>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <p><button type="button" class="button" id="add-custom-role">+ Add another role</button></p>

          <p class="description">
            Reserved roles are protected and won’t be modified: <code>administrator</code>, <code>editor</code>, <code>author</code>, <code>contributor</code>, <code>subscriber</code>.
          </p>
        </td>
      </tr>
    </table>

    <?php submit_button('Save Custom Roles'); ?>
  </form>

  <template id="yardlii-role-template">
    <div class="role-row" data-index="__i__" style="margin-bottom:12px;padding:12px;border:1px solid #e5e5e5;border-radius:8px;">
      <div style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
        <div>
          <label>Role Key (slug)</label><br>
          <input type="text" name="yardlii_custom_roles[__i__][slug]" value="" class="regular-text" required />
        </div>
        <div>
          <label>Display Name</label><br>
          <input type="text" name="yardlii_custom_roles[__i__][label]" value="" class="regular-text" required />
        </div>
        <div>
          <label>Clone caps from</label><br>
          <select name="yardlii_custom_roles[__i__][base_role]">
            <option value="">— None (start empty) —</option>
            <?php foreach ($editable_roles as $rslug => $rdata): ?>
              <option value="<?php echo esc_attr($rslug); ?>"><?php echo esc_html($rdata['name'] ?? $rslug); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="min-width:320px;">
          <label>Extra caps (comma or space separated)</label><br>
          <input type="text" name="yardlii_custom_roles[__i__][extra_caps]" value="" class="regular-text" placeholder="read, edit_posts, upload_files" />
        </div>
        <button type="button" class="button link-delete remove-role-row" aria-label="Remove role">Remove</button>
      </div>
    </div>
  </template>

  <script>
  (function(){
    const repeater = document.getElementById('yardlii-roles-repeater');
    const addBtn   = document.getElementById('add-custom-role');
    const tpl      = document.getElementById('yardlii-role-template').innerHTML;

    function nextIndex() {
      const rows = repeater.querySelectorAll('.role-row');
      let max = -1;
      rows.forEach(row => { const i = parseInt(row.getAttribute('data-index'), 10); if (i > max) max = i; });
      return (max + 1) || 0;
    }

    addBtn?.addEventListener('click', function(){
      const i = nextIndex();
      const html = tpl.replace(/__i__/g, String(i));
      const div = document.createElement('div');
      div.innerHTML = html.trim();
      repeater.appendChild(div.firstChild);
    });

    repeater.addEventListener('click', function(e){
      if (e.target && e.target.classList.contains('remove-role-row')) {
        const row = e.target.closest('.role-row');
        if (row) row.remove();
      }
    });
  })();
  </script>
</div>