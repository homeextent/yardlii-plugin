

jQuery(document).ready(function ($) {

  /**
   * Helper to update the URL AND all form referrers.
   * @param {URL} urlObject The URL object to set.
   */
  function updateUrlAndReferrers(urlObject) {
    try {
      const newUrlString = urlObject.toString();
      // 1. Update the browser URL bar
      history.replaceState({}, '', newUrlString);

      // 2. Update all _wp_http_referer fields
      // The referer value must be just the path, e.g., /wp-admin/admin.php?page=...
      const newPath = urlObject.pathname + urlObject.search;
      document.querySelectorAll('input[name="_wp_http_referer"]').forEach(input => {
        input.value = newPath;
      });
    } catch (e) {
      // Fails in test suites or old browsers
    }
  }

  /* === YARDLII: User Sync Dynamic Rows & Preview === */

  // Add new row
  $('#yardlii-add-row').on('click', function (e) {
    e.preventDefault();

    const index = $('#yardlii-repeater-body .yardlii-row').length;

    let specialOptions = '';
    if (typeof YARDLII_ADMIN !== 'undefined' && YARDLII_ADMIN.specialOptions) {
      Object.entries(YARDLII_ADMIN.specialOptions).forEach(([val, label]) => {
        specialOptions += `<option value="${val}">${label}</option>`;
      });
    } else {
      specialOptions = `
        <option value="">None</option>
        <option value="usermeta">User Meta</option>
        <option value="user_data">User Data</option>
        <option value="user_registered">User Registered (Member Since)</option>`;
    }

    const newRow = `
      <tr class="yardlii-row">
        <td>
          <input type="text"
            name="yardlii_acf_user_sync_settings_v2[mappings][${index}][field]"
            required
            placeholder="e.g. author_bio" />
        </td>
        <td>
          <input type="text"
            name="yardlii_acf_user_sync_settings_v2[mappings][${index}][fallback]"
            placeholder="Fallback value" />
        </td>
        <td>
          <select name="yardlii_acf_user_sync_settings_v2[mappings][${index}][special]">
            ${specialOptions}
          </select>
        </td>
        <td><button type="button" class="button remove-row">Remove</button></td>
      </tr>`;

    $('#yardlii-repeater-body').append($(newRow).hide().fadeIn(150));
  });

  // Remove row
  $(document).on('click', '.remove-row', function (e) {
    e.preventDefault();
    $(this).closest('tr').fadeOut(150, function () {
      $(this).remove();
    });
  });

  // Preview AJAX
  $('#yardlii_preview_btn').on('click', function (e) {
    e.preventDefault();

    const postId = parseInt($('#yardlii_preview_post_id').val(), 10);
    const field = $('#yardlii_preview_field').val();
    const output = $('#yardlii_preview_output');
    output.show().html('Loading...');

    if (!postId || !field) {
      output.html('<span style="color:red;">Post ID and Field required.</span>');
      return;
    }

    $.post(YARDLII_ADMIN.ajaxurl || ajaxurl, {
      action: 'yardlii_preview_field',
      nonce: YARDLII_ADMIN.nonce,
      post_id: postId,
      field: field,
    }, function (response) {
      if (response.success) {
        const data = response.data;
        const value = typeof data.value === 'object'
          ? JSON.stringify(data.value, null, 2)
          : data.value || '(Empty)';

        output.html(`
          <div><strong>Source:</strong> ${data.source}</div>
          <pre style="background:#fff;border:1px solid #ddd;padding:10px;">${value}</pre>
        `);
      } else {
        output.html(
          `<span style="color:red;">Error: ${response.data.reason || 'Unknown error'}</span>`
        );
      }
    }, 'json');
  });


  /* === YARDLII: Location Search toggle === */
  const yardliiAdmin = {
    initLocationToggle: function () {
      const $toggle = $('#yardlii_enable_location_search');
      const $section = $('#yardlii_location_settings_section');
      if (!$toggle.length || !$section.length) return;

      const updateState = () => {
        const isDisabled = !$toggle.is(':checked');
        const $inputs = $section.find('input, select, textarea');
        $inputs.prop('disabled', isDisabled);
        $section.toggleClass('yardlii-disabled', isDisabled);
      };

      $toggle.off('change.yardlii').on('change.yardlii', updateState);
      updateState();
    },
  };

  yardliiAdmin.initLocationToggle();
  $(document).on('click', '.yardlii-tab, .yardlii-section > summary', function () {
    setTimeout(yardliiAdmin.initLocationToggle, 100);
  });


   

/* === YARDLII: Role Control subtabs (Submit Access / Custom User Roles) === */
function initRoleControlSubtabs() {
const panel = document.querySelector('#yardlii-tab-role-control');
  if (!panel) return;

  // If the panel is locked (master OFF), do not bind handlers
  if (panel.getAttribute('aria-disabled') === 'true' || panel.querySelector('.yardlii-locked')) {
    return;
  }

  
  const wrap = document.querySelector('#yardlii-tab-role-control .yardlii-role-subtabs');
  if (!wrap) return;

  const buttons = wrap.querySelectorAll('.yardlii-tab[data-rsection]');
  const panels  = document.querySelectorAll('#yardlii-tab-role-control .yardlii-section[data-rsection]');

  function activate(id) {
  buttons.forEach(btn => {
    const on = btn.dataset.rsection === id;
    btn.classList.toggle('active', on);
    btn.setAttribute('aria-selected', on ? 'true' : 'false');
    btn.tabIndex = on ? 0 : -1;
  });

  panels.forEach(p => {
    const show = p.dataset.rsection === id;
    if ('open' in p) {
      p.open = !!show;
      if (show) { p.removeAttribute('hidden'); } else { p.setAttribute('hidden', 'hidden'); }
    } else {
      p.classList.toggle('hidden', !show);
      p.setAttribute('aria-hidden', show ? 'false' : 'true');
    }
  });

  try { localStorage.setItem('yardlii_active_rsection', id); } catch (e) {}

  // --- START: NEW CODE (Keeps URL in sync) ---
  try {
    const u = new URL(window.location.href);
    u.searchParams.set('tab', 'role-control'); // Force parent tab
    u.searchParams.set('rsection', id);
    updateUrlAndReferrers(u);
  } catch (e) {
    // Fails in test suites or old browsers
  }
  // --- END: NEW CODE ---
}

  // --- START: MODIFIED RESTORE LOGIC ---
const urlRSection = getUrlParam('rsection');
const localRSection = localStorage.getItem('yardlii_active_rsection');

const initialId =
    (urlRSection && [...buttons].some(b => b.dataset.rsection === urlRSection))
    ? urlRSection // Priority 1: Use 'rsection' from URL
    : (localRSection && [...buttons].some(b => b.dataset.rsection === localRSection))
      ? localRSection // Priority 2: Use 'localStorage'
      : wrap.querySelector('.yardlii-tab.active')?.dataset.rsection // Priority 3: HTML default
      || document.querySelector('#yardlii-tab-role-control .yardlii-section[open]')?.dataset.rsection
      || panels[0]?.dataset.rsection;
// --- END: MODIFIED RESTORE LOGIC ---

  if (initialId) activate(initialId);

  buttons.forEach(btn => {
    btn.addEventListener('click', () => activate(btn.dataset.rsection));
    btn.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
        e.preventDefault();
        const list = [...buttons];
        const idx  = list.indexOf(btn);
        const next = e.key === 'ArrowRight'
          ? list[(idx + 1) % list.length]
          : list[(idx - 1 + list.length) % list.length];
        next.focus();
        activate(next.dataset.rsection);
      }
    });
  });
}


/* === YARDLII: Persist active tabs across reloads === */
(() => {
  const KEY_MAIN   = 'yardlii_active_tab';
  const KEY_GEN    = 'yardlii_active_gsection';
  const KEY_MAP_IN = 'yardlii_active_map_inner';

  

  // 1) MAIN TABS (General / User Sync / Advanced)
  const mainNav = document.querySelector('nav.yardlii-tabs[data-scope="main"]');
  if (mainNav) {
    const mainBtns = mainNav.querySelectorAll('.yardlii-tab');
    const mainPanels = document.querySelectorAll('.yardlii-tabpanel');

    function activateMain(id) {
      mainBtns.forEach(b => {
        const on = b.dataset.tab === id;
        b.classList.toggle('active', on);
        b.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      mainPanels.forEach(p => p.classList.toggle('hidden', p.dataset.panel !== id));
      sessionStorage.setItem(KEY_MAIN, id);

      // --- START: NEW CODE (Keeps URL in sync) ---
      try {
        const u = new URL(window.location.href);
        u.searchParams.set('tab', id);
        // Clean up sub-section params from other tabs
        u.searchParams.delete('gsection');
        u.searchParams.delete('tvsection');
        u.searchParams.delete('advsection');
        u.searchParams.delete('rsection'); // <-- THIS IS THE FIX
        updateUrlAndReferrers(u);
      } catch (e) {
        // Fails in test suites or old browsers
    }
    // --- END: NEW CODE ---

    // --- START: ADD THIS ---
    // After activating the main tab, initialize sub-tabs IF we are on that tab.
    if (id === 'role-control') {
      initRoleControlSubtabs();
    }
    // --- END: ADD THIS ---
  }

    // --- START: MODIFIED RESTORE LOGIC ---
    
    // Check URL first, then session storage, then default
    const urlTab = getUrlParam('tab');
    const sessionTab = sessionStorage.getItem(KEY_MAIN);
    const firstActive = [...mainBtns].find(b => b.classList.contains('active')) || mainBtns[0];
    let initialTab = 'general'; // Default tab

    if (urlTab && [...mainBtns].some(b => b.dataset.tab === urlTab)) {
      // Priority 1: Use the 'tab' from the URL
      initialTab = urlTab;
    } else if (sessionTab && [...mainBtns].some(b => b.dataset.tab === sessionTab)) {
      // Priority 2: Use the last-clicked tab from session storage
      initialTab = sessionTab;
    } else if (firstActive) {
      // Priority 3: Use the tab that is already active (from HTML)
      initialTab = firstActive.dataset.tab;
    }
    
    if (initialTab) {
      activateMain(initialTab);
    }
    
    // --- END: MODIFIED RESTORE LOGIC ---

    // clicks
    mainBtns.forEach(b => b.addEventListener('click', () => activateMain(b.dataset.tab)));
  }

  // 2) GENERAL SUB-TABS (the four buttons above the <details> panels)
  const genNav = document.querySelector('#yardlii-tab-general .yardlii-general-subtabs');
  if (genNav) {
    const genBtns = genNav.querySelectorAll('.yardlii-tab');
    const genPanels = document.querySelectorAll('#yardlii-tab-general details.yardlii-section');

    function activateGen(id) {
      genBtns.forEach(b => {
        const on = b.dataset.gsection === id;
        b.classList.toggle('active', on);
        b.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      genPanels.forEach(p => {
        const show = p.dataset.gsection === id;
        if (show) { p.removeAttribute('hidden'); p.setAttribute('open','open'); }
        else { p.setAttribute('hidden','hidden'); p.removeAttribute('open'); }
      });
      sessionStorage.setItem(KEY_GEN, id);

      // --- START: NEW CODE (Keeps URL in sync) ---
      try {
        const u = new URL(window.location.href);
        u.searchParams.set('tab', 'general'); // Force parent tab
        u.searchParams.set('gsection', id);
        updateUrlAndReferrers(u);
      } catch (e) {
        // Fails in test suites or old browsers
      }
      // --- END: NEW CODE ---
    }
    
    // --- MODIFIED: Use new getUrlParam helper ---
    const urlGen = getUrlParam('gsection');
    const savedGen = sessionStorage.getItem(KEY_GEN);
    let initialGen = '';
    
    if (urlGen && [...genBtns].some(b => b.dataset.gsection === urlGen)) {
      initialGen = urlGen;
    } else if (savedGen && [...genBtns].some(b => b.dataset.gsection === savedGen)) {
      initialGen = savedGen;
    }

    if (initialGen) {
      activateGen(initialGen);
    }

    genBtns.forEach(b => b.addEventListener('click', () => activateGen(b.dataset.gsection)));
  }

  // 3) INNER TABS (inside Google Map Settings)
  document.querySelectorAll('.yardlii-inner-tabs').forEach((wrap) => {
    const tabs   = wrap.querySelectorAll('.yardlii-inner-tab');
    const panels = wrap.querySelectorAll('.yardlii-inner-tabcontent');

    function activateInner(id) {
      tabs.forEach(btn => {
        const on = btn.dataset.tab === id;
        btn.classList.toggle('active', on);
        btn.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      panels.forEach(p => {
        const show = p.dataset.panel === id;
        p.classList.toggle('hidden', !show);
        p.setAttribute('aria-hidden', show ? 'false' : 'true');
      });
      sessionStorage.setItem(KEY_MAP_IN, id);
    }

    // restore
    const savedInner = sessionStorage.getItem(KEY_MAP_IN);
    const firstActive = [...tabs].find(b => b.classList.contains('active')) || tabs[0];
    const initial = (savedInner && [...tabs].some(b => b.dataset.tab === savedInner))
      ? savedInner
      : firstActive?.dataset.tab;

    if (initial) activateInner(initial);

    tabs.forEach(btn => btn.addEventListener('click', () => activateInner(btn.dataset.tab)));
  });
})();

/**
 * YARDLII: Advanced Subtabs
 * Handles switching sections on the Advanced tab.
 */
(function ($) {
  'use strict';

  function initAdvancedSubtabs() {
    const $panel = $('#yardlii-tab-advanced');
    if (!$panel.length) return;

    const $tabs = $panel.find('.yardlii-advanced-subtabs .yardlii-tab');
    const $sections = $panel.find('details.yardlii-section[data-asection]');
    if (!$tabs.length || !$sections.length) return;

    const KEY_ADV = 'yardlii_active_adv_section';

    function activate(id) {
      if (!id) return;
      
      // Update tabs
      $tabs.removeClass('active').attr('aria-selected', 'false');
      $tabs.filter('[data-asection="' + id + '"]').addClass('active').attr('aria-selected', 'true');

      // Update <details> panels
      $sections.each(function() {
        const $sec = $(this);
        if ($sec.data('asection') === id) {
          $sec.attr('open', true).removeAttr('hidden');
        } else {
          $sec.removeAttr('open').attr('hidden', true);
        }
      });
      
      sessionStorage.setItem(KEY_ADV, id);
    }

    // Get active tab from URL or session storage
    function getActiveId() {
      try {
        const urlId = (new URL(window.location.href)).searchParams.get('advsection');
        if (urlId) return urlId;
      } catch (e) {}
      
      const storageId = sessionStorage.getItem(KEY_ADV);
      if (storageId) return storageId;

      return $tabs.first().data('asection') || 'flags';
    }

    // Initial activation
    activate(getActiveId());

    // Click handler
    $tabs.on('click', function (e) {
      e.preventDefault();
      const id = $(this).data('asection'); // <-- This line is corrected
      activate(id);

      // Keep URL in sync
      try {
        const u = new URL(window.location.href);
        u.searchParams.set('advsection', id);
        updateUrlAndReferrers(u);
      } catch (e) {}
    });
  }

  $(function () {
    // We must wait for the main "Advanced" tab to be clicked,
    // so we re-initialize every time the main tabs are swapped.
    // This assumes the main tab switcher (not shown here) is already working.
    $(document).on('click', '.yardlii-tabs[data-scope="main"] .yardlii-tab[data-tab="advanced"]', function() {
      // Use a small delay to ensure the panel is visible before init
      setTimeout(initAdvancedSubtabs, 10);
    });

    // Also run on page load, in case the "Advanced" tab is already active
    if ($('#yardlii-tab-advanced').is(':visible')) {
      initAdvancedSubtabs();
    }
  });

})(jQuery);







});
