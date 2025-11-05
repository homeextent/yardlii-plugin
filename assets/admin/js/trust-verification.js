/* =========================================================
 * Yardlii — Trust & Verification (Admin)
 * Cleaned, labeled, and minimally de-duplicated.
 * =======================================================*/
(function ($) {
  'use strict';

  /* =========================
   * 0) STATE
   * =======================*/
  let tvLastFocusedBtn = null;

  /* =========================
   * 1) HELPERS
   * =======================*/
  function tvPanel()   { return $('#yardlii-tab-trust-verification'); }
  function isOnPanel() { return tvPanel().length > 0; }

  /* =========================
   * 2) INNER SUBTABS
   * =======================*/
  function initSubtabs() {
    const $panel    = tvPanel();
    const $tabs     = $panel.find('.yardlii-inner-tablist .yardlii-inner-tab');
    const $sections = $panel.find('.yardlii-inner-tabcontent');
    if (!$tabs.length || !$sections.length) return;

    const KEY_TV = 'yardlii_active_tv_section';

    function activate(id) {
      const $tab = $tabs.filter('[data-tvsection="' + id + '"]');
      const $sec = $sections.filter('[data-tvsection="' + id + '"]');
      const fallbackId = String($tabs.first().data('tvsection'));
      const useId = ($tab.length && $sec.length) ? id : fallbackId;

      $tabs.attr('aria-selected', 'false').removeClass('active');
      $tabs.filter('[data-tvsection="' + useId + '"]').attr('aria-selected', 'true').addClass('active');

      $sections.addClass('hidden');
      $sections.filter('[data-tvsection="' + useId + '"]').removeClass('hidden');
    }

    function fromUrl() {
      try { return (new URL(window.location.href)).searchParams.get('tvsection') || ''; }
      catch (e) { return ''; }
    }

    // Initial selection
    (function () {
      let id = fromUrl().trim();
      if (!id) id = (sessionStorage.getItem(KEY_TV) || '').trim();
      if (!id) id = String($tabs.first().data('tvsection') || 'configuration');
      activate(id);
    })();

    // Mouse
    $tabs.on('click', function () {
      const id = String($(this).data('tvsection') || '').trim();
      if (!id) return;
      sessionStorage.setItem(KEY_TV, id);
      activate(id);
      // Keep URL in sync
      try {
        const u = new URL(window.location.href);
        u.searchParams.set('tvsection', id);
        history.replaceState({}, '', u.toString());
      } catch (e) {}
    });

    // Keyboard
    $tabs.on('keydown', function (e) {
      const idx = $tabs.index(this);
      if (e.key === 'ArrowRight') { e.preventDefault(); $tabs.eq((idx + 1) % $tabs.length).focus().click(); }
      else if (e.key === 'ArrowLeft') { e.preventDefault(); $tabs.eq((idx - 1 + $tabs.length) % $tabs.length).focus().click(); }
    });
  }

  /* =========================
   * 3) REPEATER + WYSIWYG
   * =======================*/

  // 3.1 Get next config index
  function nextConfigIndex() {
    const $rows = $('#yardlii-tv-config-rows .yardlii-tv-config-item');
    if (!$rows.length) return 0;
    let max = -1;
    $rows.each(function () {
      const n = parseInt($(this).data('index'), 10);
      if (!isNaN(n) && n > max) max = n;
    });
    return max + 1;
  }

  // 3.2 Field set that we read/clone
  const TV_FIELDS = [
    'form_id', 'approved_role', 'rejected_role',
    'approve_subject', 'approve_body', 'reject_subject', 'reject_body'
  ];

  // 3.3 Read WYSIWYG HTML if TinyMCE present, else textarea value
  function getEditorHtml($ta) {
    const id = $ta.attr('id');
    if (window.tinymce && id) {
      const ed = tinymce.get(id);
      if (ed) return ed.getContent() || '';
    }
    return $ta.val() || '';
  }

  // 3.4 Read row values
  function readRowValues($row) {
    const vals = {};
    TV_FIELDS.forEach(k => {
      if (k.endsWith('_body')) {
        vals[k] = ($row.find('textarea[name$="[' + k + ']"]').val() || '').toString();
      } else if (k.endsWith('_role')) {
        vals[k] = ($row.find('select[name$="[' + k + ']"]').val() || '').toString();
      } else {
        vals[k] = ($row.find('input[name$="[' + k + ']"]').val() || '').toString();
      }
    });
    return vals;
  }

  // 3.5 Apply row values
  function applyRowValues($row, vals) {
    TV_FIELDS.forEach(k => {
      if (k.endsWith('_body')) {
        $row.find('textarea[name$="[' + k + ']"]').val(vals[k] || '');
      } else if (k.endsWith('_role')) {
        $row.find('select[name$="[' + k + ']"]').val(vals[k] || '');
      } else {
        $row.find('input[name$="[' + k + ']"]').val(vals[k] || '');
      }
    });
    updateConfigSummary($row);
  }

  // 3.6 Update row summary line
  function updateConfigSummary($row) {
    const formId   = ($row.find('input[name$="[form_id]"]').val() || '').trim() || 'New form';
    const approved = $row.find('select[name$="[approved_role]"] option:selected').text() || '—';
    const rejected = $row.find('select[name$="[rejected_role]"] option:selected').text() || '—';
    $row.find('[data-summary="form"]').text(formId);
    $row.find('[data-summary="approved"]').text(approved);
    $row.find('[data-summary="rejected"]').text(rejected);
  }

  // 3.7 Lazy-init WYSIWYG per opened row
  function initWysiwyg($scope) {
    const $areas = $scope.find('textarea.yardlii-tv-wysiwyg');
    if (!$areas.length) return;

    function tryInit() {
      if (window.wp && wp.editor && typeof wp.editor.initialize === 'function') {
        $areas.each(function () {
          const $ta = $(this);
          if (!$ta.attr('id')) $ta.attr('id', 'tv_wysiwyg_' + Math.random().toString(36).slice(2));
          const id = $ta.attr('id');
          if ($ta.data('tvWysiwyg')) return;
          try {
            wp.editor.initialize(id, { tinymce: true, quicktags: true, mediaButtons: false });
            $ta.data('tvWysiwyg', true);
          } catch (e) { if (console && console.debug) console.debug('WYSIWYG init failed', e); }
        });
        return true;
      }
      return false;
    }

    if (!tryInit()) setTimeout(tryInit, 150);
  }

  // 3.8 Bind row interactions + repeater
  function bindConfigRowInteractions($row) {
    $row.on('input change',
      'input[name$="[form_id]"], select[name$="[approved_role]"], select[name$="[rejected_role]"]',
      function () { updateConfigSummary($row); }
    );
    updateConfigSummary($row);

    $row.on('toggle', function () { if (this.open) initWysiwyg($row); });
    if ($row.prop('open')) initWysiwyg($row);
  }

  function initConfigRepeater() {
    const $rowsWrap = $('#yardlii-tv-config-rows');
    const $template = $('#yardlii-tv-config-template');
    if (!$rowsWrap.length || !$template.length) return;

    // Existing rows
    $rowsWrap.find('.yardlii-tv-config-item').each(function () {
      bindConfigRowInteractions($(this));
    });

    // Add
    $('#yardlii-tv-add-config').on('click', function () {
      const idx  = nextConfigIndex();
      const html = $template.html().replace(/__i__/g, String(idx));
      const $node = $(html);
      $rowsWrap.append($node);
      $node.attr('open', true);
      bindConfigRowInteractions($node);
      try { $node[0].scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) {}
    });

    // Remove
    $rowsWrap.on('click', '[data-action="tv-remove-config-row"]', function () {
      const $row = $(this).closest('.yardlii-tv-config-item');
      if (!$row.length) return;
      if ($rowsWrap.find('.yardlii-tv-config-item').length <= 1) {
        if (!window.confirm('Remove the only configuration row?')) return;
      }
      $row.remove();
    });

    // Duplicate
    $rowsWrap.on('click', '[data-action="tv-duplicate-config-row"]', function () {
      const $src = $(this).closest('.yardlii-tv-config-item');
      if (!$src.length) return;

      const vals = readRowValues($src);
      const idx  = nextConfigIndex();
      const html = $template.html().replace(/__i__/g, String(idx));
      const $node = $(html);

      $rowsWrap.append($node);
      bindConfigRowInteractions($node);
      applyRowValues($node, vals);
      $node.attr('open', true);
      initWysiwyg($node);
      try { $node[0].scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) {}
    });
  }

  /* =========================
   * 4) DRAG/SORT + NAME RE-NUMBER
   * =======================*/
  function renumberConfigNames() {
    $('#yardlii-tv-config-rows .yardlii-tv-config-item').each(function (i) {
      const $row = $(this).attr('data-index', i);
      $row.find('[name^="yardlii_tv_form_configs["]').each(function () {
        // yardlii_tv_form_configs[12][field] -> yardlii_tv_form_configs[i][field]
        this.name = this.name.replace(/^(yardlii_tv_form_configs\[\s*)\d+(\s*\])/, '$1' + i + '$2');
      });
    });
  }

  function initConfigSort() {
    const $wrap = $('#yardlii-tv-config-rows');
    if (!$wrap.length) return;

    if (!$.fn.sortable) {
      console.warn('[TV] jQuery UI Sortable not available — ensure it is enqueued.');
      return;
    }

    // Prevent toggling <summary> when grabbing the handle
    $(document).on('mousedown', '.tv-drag-handle', function (e) { e.preventDefault(); });

    $wrap.sortable({
      items: '.yardlii-tv-config-item',
      handle: '.tv-drag-handle',
      axis: 'y',
      placeholder: 'tv-sort-placeholder',
      forcePlaceholderSize: true,
      tolerance: 'pointer',
      distance: 4,
      cancel: 'input,textarea,select,button,a,.wp-editor-wrap',
      start: function (e, ui) { ui.placeholder.height(ui.item.outerHeight()); },
      stop:  function () { renumberConfigNames(); }
    });

    $('#yardlii-tv-form-configs').on('submit', function () { renumberConfigNames(); });
  }

  /* =========================
   * 5) PREVIEW OVERLAY (CHROME)
   * =======================*/
  function openPreview(html) {
    const $overlay = $('#yardlii-tv-preview-overlay');
    if (!$overlay.length) return;

    tvLastFocusedBtn = document.activeElement || null;
    $('#yardlii-tv-preview-body').html(html || '<p>—</p>');
    $overlay.addClass('is-open').attr('aria-hidden', 'false').show();

    const $title = $('#yardlii-tv-preview-title');
    if ($title.length) {
      if (!$title.attr('tabindex')) $title.attr('tabindex', '-1');
      $title.trigger('focus');
    }
  }

  function closePreview() {
    const $overlay = $('#yardlii-tv-preview-overlay');
    $overlay.removeClass('is-open').attr('aria-hidden', 'true').hide();
    if (tvLastFocusedBtn && typeof tvLastFocusedBtn.focus === 'function') {
      try { tvLastFocusedBtn.focus(); } catch (e) {}
    }
    tvLastFocusedBtn = null;
  }

  function initPreviewChrome() {
    // Close with “×”
    $(document)
      .off('click.tvPreviewClose', '[data-action="tv-preview-close"]')
      .on('click.tvPreviewClose',  '[data-action="tv-preview-close"]', closePreview);

    // Click outside
    $(document)
      .off('click.tvPreviewBackdrop', '#yardlii-tv-preview-overlay')
      .on('click.tvPreviewBackdrop',  '#yardlii-tv-preview-overlay', function (e) {
        if (e.target && e.target.id === 'yardlii-tv-preview-overlay') closePreview();
      });

    // Escape key
    $(document)
      .off('keydown.tvPreview')
      .on('keydown.tvPreview', function (e) {
        if (e.key === 'Escape' || e.key === 'Esc') {
          const $overlay = $('#yardlii-tv-preview-overlay');
          if ($overlay.hasClass('is-open') || $overlay.is(':visible')) {
            e.preventDefault();
            closePreview();
          }
        }
      });
  }

  /* =========================
   * 6) PER-ROW PREVIEW + SEND TEST
   * =======================*/

  // 6.1 Prepare payload (reads unsaved edits)
  function getRowPreviewPayload($row) {
    const formId = String($row.find('input[name$="[form_id]"]').val() || '').trim();
    const type   = String($row.find('input[name$="[preview_type]"]:checked').val() || '').trim();
    const userId = String($row.find('input[name$="[preview_user]"]').val() || '').trim();

    const subjField = (type === 'reject') ? 'reject_subject' : 'approve_subject';
    const bodyField = (type === 'reject') ? 'reject_body'    : 'approve_body';

    const $subj = $row.find('input[name$="[' + subjField + ']"]');
    const $body = $row.find('textarea[name$="[' + bodyField + ']"]');

    return {
      form_id:   formId,
      email_type: type,
      user_id:   userId,
      subject:   ($subj.val() || '').toString(),
      body:      getEditorHtml($body)
    };
  }

  // 6.2 Overlay preview per row
  function initRowPreview() {
    $(document).on('click', '[data-action="tv-row-preview-email"]', function () {
      const $row = $(this).closest('.yardlii-tv-config-item');
      if (!$row.length) return;

      const payload = getRowPreviewPayload($row);
      if (!payload.form_id || !payload.email_type) {
        alert('Please enter a Form ID and choose a preview type.');
        return;
      }

      const ajaxUrl = (window.YardliiTV && YardliiTV.ajax) ? YardliiTV.ajax : (window.ajaxurl || '/wp-admin/admin-ajax.php');
      const nonce   = (window.YardliiTV && YardliiTV.noncePreview) ? YardliiTV.noncePreview : '';

      openPreview('<p>Loading preview…</p>');
      $.post(ajaxUrl, Object.assign({ action: 'yardlii_tv_preview_email', _ajax_nonce: nonce }, payload))
        .done(function (resp) {
          if (resp && resp.success && resp.data && resp.data.html) {
            openPreview(resp.data.html);
          } else {
            openPreview('<p>Preview failed. Please check your configuration.</p>');
          }
        })
        .fail(function (xhr) {
          console.error('[TV] Row preview XHR failed', xhr.status, xhr.responseText);
          openPreview('<p>Preview request error.</p>');
        });
    });
  }

  // 6.3 Send test email
  function validateEmail(s) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(s || '').trim()); }

  function rowBanner($row, type, msg) {
    const cls = (type === 'success') ? 'yardlii-banner--success'
             : (type === 'info')    ? 'yardlii-banner--info'
             : 'yardlii-banner--error';
    const $box = $('<div class="yardlii-banner ' + cls + '"><p>' + msg + '</p></div>');
    $row.find('.yardlii-section-content').prepend($box);
    setTimeout(() => { try { $box.fadeOut(200, () => $box.remove()); } catch (e) {} }, 4200);
  }

  function initRowSendTest() {
    $(document).on('click', '[data-action="tv-row-send-test"]', function () {
      const $btn = $(this);
      const $row = $btn.closest('.yardlii-tv-config-item');
      if (!$row.length) return;

      const payload = getRowPreviewPayload($row);
      if (!payload.form_id || !payload.email_type) {
        alert('Please enter a Form ID and choose a preview type.');
        return;
      }

      // Recipient
      let to = ($row.find('input[name$="[preview_to]"]').val() || '').trim();
      if (!to) to = ($row.find('input[name$="[preview_to]"]').attr('placeholder') || '').trim();
      if (!validateEmail(to)) { rowBanner($row, 'error', 'Please enter a valid recipient email.'); return; }

      const ajaxUrl = (window.YardliiTV && YardliiTV.ajax) ? YardliiTV.ajax : (window.ajaxurl || '/wp-admin/admin-ajax.php');
      const nonce   = (window.YardliiTV && YardliiTV.nonceSend) ? YardliiTV.nonceSend : '';
      if (!nonce && console && console.warn) console.warn('[TV] Missing YardliiTV.nonceSend');

      $btn.prop('disabled', true).text('Sending…');

      $.post(ajaxUrl, Object.assign({ action: 'yardlii_tv_send_test_email', _ajax_nonce: nonce, to }, payload))
        .done(function (resp) {
          if (resp && resp.success) {
            rowBanner($row, 'success', (resp.data && resp.data.message) ? resp.data.message : 'Test email sent.');
          } else {
            const m = (resp && resp.data && resp.data.message) ? resp.data.message : 'Sending failed.';
            rowBanner($row, 'error', m);
          }
        })
        .fail(function (xhr) {
          rowBanner($row, 'error', 'XHR error: ' + (xhr.status || '') + ' ' + (xhr.statusText || ''));
        })
        .always(function () {
          $btn.prop('disabled', false).text('Send test email');
        });
    });
  }

  /* =========================
   * 7) DIAGNOSTICS (REST TEST)
   * =======================*/
  function initDiagnostics() {
    const $panel = tvPanel();
    if (!$panel.length) return;

    $(document).on('click', '#yardlii-tv-api-test', function (e) {
      e.preventDefault();
      const uid = String($('#yardlii-tv-api-user').val() || '').trim();
      if (!uid) { alert('Enter a User ID'); return; }

      const root = (window.YardliiTV && YardliiTV.restRoot) ? YardliiTV.restRoot : '/wp-json/';
      const url  = root.replace(/\/?$/, '/') + 'yardlii/v1/verification-status/' + encodeURIComponent(uid);
      const headers = {};
      if (window.YardliiTV && YardliiTV.restNonce) headers['X-WP-Nonce'] = YardliiTV.restNonce;

      fetch(url, { credentials: 'same-origin', headers })
        .then(r => r.json())
        .then(data => { $('#yardlii-tv-api-output').text(JSON.stringify(data, null, 2)); })
        .catch(err => { $('#yardlii-tv-api-output').text(String(err)); });
    });
  }

  /* =========================
   * 8) INLINE BANNERS & URL CLEANUP
   * =======================*/
  function initBanners() {
    const $panel = tvPanel();
    if (!$panel.length) return;

    $(document).on('click', '[data-dismiss="yardlii-banner"]', function () {
      const $box = $(this).closest('.yardlii-banner');
      if ($box.length) $box.remove();
    });

    const hasBanner = $panel.find('.yardlii-banner, .notice').length > 0;
    if (hasBanner) {
      const url = new URL(window.location.href);
      ['tv_notice', 'tv_reset', 'tv_seed', 'settings-updated'].forEach(k => url.searchParams.delete(k));
      history.replaceState({}, '', url.toString());
    }
  }

  /* =========================
   * 9) PREVIEW COPY (Overlay)
   * =======================*/
  function tvCollectHistoryText() {
    const $body  = $('#yardlii-tv-preview-body');
    const $items = $body.find('.tv-history-item');
    if (!$items.length) return ($body.text() || '').trim();

    const lines = [];
    $items.each(function () {
      const $it  = $(this);
      const head = $it.find('.tv-history-head').text().replace(/\s+/g, ' ').trim();
      if (head) lines.push(head);
      $it.find('.tv-history-line').each(function () {
        const l = $(this).text().replace(/\s+/g, ' ').trim();
        if (l) lines.push(l);
      });
    });
    return lines.join('\n');
  }

  function initPreviewCopy() {
    $(document).on('click', '[data-action="tv-preview-copy"]', async function () {
      const $btn = $(this);
      const original = $btn.text();
      const txt = tvCollectHistoryText();

      if (!txt) {
        $btn.text('Nothing to copy').prop('disabled', true);
        setTimeout(() => { $btn.text(original).prop('disabled', false); }, 1200);
        return;
      }

      try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          await navigator.clipboard.writeText(txt);
        } else {
          const ta = document.createElement('textarea');
          ta.value = txt;
          ta.style.position = 'fixed';
          ta.style.top = '-1000px';
          document.body.appendChild(ta);
          ta.focus(); ta.select();
          document.execCommand('copy');
          document.body.removeChild(ta);
        }
        $btn.text('Copied!').prop('disabled', true);
        setTimeout(() => { $btn.text(original).prop('disabled', false); }, 1000);
      } catch (e) {
        $btn.text('Copy failed').prop('disabled', true);
        setTimeout(() => { $btn.text(original).prop('disabled', false); }, 1200);
      }
    });
  }

  /* =========================
   * 10) PROVIDERS HELP (uses shared preview overlay)
   * =======================*/
  function initProvidersHelp() {
    $(document).on('click', '[data-action="tv-show-providers-help"]', function (e) {
      e.preventDefault();
      const tpl = document.getElementById('tv-providers-help');
      if (!tpl) return;

      const html = tpl.innerHTML || '';
      const $overlay = $('#yardlii-tv-preview-overlay');

      $overlay.find('#yardlii-tv-preview-title').text('Providers — How this works');
      $overlay.find('#yardlii-tv-preview-body').html(html);

      // Support both class conventions and ensure visibility
      $overlay.attr('aria-hidden', 'false').addClass('open is-open').show();
    });
  }

  /* ============= 11) TOOLS: Send test for all forms ============= */
function initToolsSendAllTests(){
  const $ = jQuery;

  // If the Tools section isn't on screen, nothing to wire
  if (!document.getElementById('tv-send-all-tests')) return;

  $(document)
    .off('click.tvSendAll', '#tv-send-all-tests')
    .on('click.tvSendAll',  '#tv-send-all-tests', function(){
      const $btn = $(this);

      const to   = String($('#tv-testall-to').val() || '').trim()
                || String($('#tv-testall-to').attr('placeholder') || '').trim();
      const type = String(($('input[name="tv-testall-type"]:checked').val() || 'approve')).trim();

      if (!to || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(to)) {
        alert('Please enter a valid recipient email.');
        return;
      }

      const ajaxUrl = (window.YardliiTV && YardliiTV.ajax) ? YardliiTV.ajax : (window.ajaxurl || '/wp-admin/admin-ajax.php');
      const nonce   = (window.YardliiTV && YardliiTV.nonceSend) ? YardliiTV.nonceSend : '';

      $btn.prop('disabled', true).text('Sending…');

      $.post(ajaxUrl, { action:'yardlii_tv_send_all_tests', _ajax_nonce: nonce, to, type })
        .done(function(resp){
          if (resp && resp.success) {
            alert((resp.data && resp.data.message) ? resp.data.message : 'All test emails sent.');
          } else {
            const m = (resp && resp.data && resp.data.message) ? resp.data.message : 'Sending failed.';
            alert(m);
          }
        })
        .fail(function(xhr){
          alert('XHR error: ' + (xhr.status || '') + ' ' + (xhr.statusText || ''));
        })
        .always(function(){
          $btn.prop('disabled', false).text('Send test for all forms');
        });
    });
}


  /* =========================
   * 12) BOOT
   * =======================*/
  $(function () {
    if (!isOnPanel()) return;

    initSubtabs();
    initConfigRepeater();
    initConfigSort();
    initPreviewChrome();
    initRowPreview();
    initRowSendTest();
    initDiagnostics();
    initBanners();
    initPreviewCopy();     // ensure the “Copy log” button works in overlay
    initProvidersHelp();   // providers README overlay
    initToolsSendAllTests(); 
  });

})(jQuery);


/* =========================================================
 * History Modal (global to admin; not tied to TV panel state)
 * - Creates a single modal and loads request history via AJAX.
 * =======================================================*/
(function () {
  const MODAL_ID = 'yardlii-tv-history-modal';

  function ensureModal() {
    let m = document.getElementById(MODAL_ID);
    if (m) return m;

    m = document.createElement('div');
    m.id = MODAL_ID;
    m.className = 'yardlii-modal';
    m.innerHTML = [
      '<div class="yardlii-modal__overlay" data-close="1"></div>',
      '<div class="yardlii-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="tv-hdr">',
      '  <div class="yardlii-modal__head">',
      '    <h2 id="tv-hdr" class="yardlii-modal__title">History</h2>',
      '    <div class="yardlii-modal__tools">',
      '      <button type="button" class="button button-secondary" data-copy="1">Copy log</button>',
      '      <button type="button" class="button button-link-delete" aria-label="Close" data-close="1">×</button>',
      '    </div>',
      '  </div>',
      '  <div class="yardlii-modal__body"><div class="tv-history tv-history--loading"><p>Loading…</p></div></div>',
      '</div>'
    ].join('');
    document.body.appendChild(m);

    // Close handlers
    m.addEventListener('click', function (e) {
      if (e.target && (e.target.getAttribute('data-close') === '1')) {
        m.classList.remove('is-open');
      }
    });

    // Copy handler (modal-local copy)
    m.querySelector('[data-copy="1"]').addEventListener('click', function () {
      const body = m.querySelector('.yardlii-modal__body');
      if (!body) return;

      const temp = document.createElement('div');
      temp.innerHTML = body.innerHTML;
      const txt = temp.innerText.replace(/\s+\n/g, '\n').trim();

      navigator.clipboard.writeText(txt).then(() => {
        this.textContent = 'Copied!';
        setTimeout(() => (this.textContent = 'Copy log'), 1200);
      }).catch(() => {
        alert('Could not copy to clipboard.');
      });
    });

    return m;
  }

  // Row action → open modal + load history
  document.addEventListener('click', function (e) {
    const a = e.target.closest('a[data-action="tv-row-history"]');
    if (!a) return;

    e.preventDefault();

    const postId = parseInt(a.getAttribute('data-post') || '0', 10);
    const nonce  = a.getAttribute('data-nonce') || '';
    if (!postId || !nonce) return;

    const modal = ensureModal();
    const body  = modal.querySelector('.yardlii-modal__body');
    if (body) body.innerHTML = '<div class="tv-history tv-history--loading"><p>Loading…</p></div>';

    modal.classList.add('is-open');





    // AJAX: wp-admin/admin-ajax.php
    const payload = new URLSearchParams();
    payload.append('action', 'yardlii_tv_history_load');
    payload.append('request_id', String(postId));
    payload.append('_ajax_nonce', nonce);

    fetch(ajaxurl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: payload.toString()
    })
      .then(r => r.json())
      .then(json => {
        if (!json || !json.success) {
          const msg = (json && json.data && json.data.message) ? json.data.message : 'Error loading history.';
          body.innerHTML = '<div class="notice notice-error"><p>' + msg + '</p></div>';
          return;
        }
        body.innerHTML = json.data.html || '<p>No history.</p>';
      })
      .catch(() => {
        body.innerHTML = '<div class="notice notice-error"><p>XHR error while loading history.</p></div>';
      });
  });
})();

