/**
 * YARDLII: Trust & Verification Admin (Refactored)
 *
 * This file contains the refactored ES6 class-based logic for:
 * 1. The Trust & Verification admin panel (tabs, forms, previews, etc.).
 * 2. The global "History" modal.
 *
 * It relies on 'jquery', 'jquery-ui-sortable', and the 'YardliiTV' global object
 * localized from AdminPage.php.
 */
(function ($) {
  'use strict';

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
      const newPath = urlObject.pathname + urlObject.search;
      document.querySelectorAll('input[name="_wp_http_referer"]').forEach(input => {
        input.value = newPath;
      });
    } catch (e) {
      // Fails in test suites or old browsers
    }
  }

  /**
   * Manages all logic for the main Trust & Verification admin panel.
   */
  class TrustVerificationAdminPanel {
    constructor($, tvData) {
      this.$ = $;
      this.tvData = tvData || {};
      this.$panel = this.$('#yardlii-tab-trust-verification');
      this.tvLastFocusedBtn = null;

      // Per-row editor fields
      this.TV_FIELDS = [
        'form_id', 'approved_role', 'rejected_role',
        'approve_subject', 'approve_body', 'reject_subject', 'reject_body'
      ];
    }

    /**
     * Initialize all event listeners and UI components.
     */
    init() {
      if (!this.$panel.length) return;

      this.bindSubtabs();
      this.bindConfigRepeater();
      this.bindConfigSort();
      this.bindPreviewChrome();
      this.bindRowPreview();
      this.bindRowSendTest();
      this.bindDiagnostics();
      this.bindBanners();
      this.bindProvidersHelp();
      this.bindToolsSendAllTests();
    }

    // --- 2) INNER SUBTABS ---

    bindSubtabs() {
      const $tabs = this.$panel.find('.yardlii-inner-tablist .yardlii-inner-tab');
      const $sections = this.$panel.find('.yardlii-inner-tabcontent');
      if (!$tabs.length || !$sections.length) return;

      const KEY_TV = 'yardlii_active_tv_section';

      const activate = (id) => {
        const useId = String(id || 'configuration').trim();
        $tabs.removeClass('active').attr('aria-selected', 'false');
        $tabs.filter('[data-tvsection="' + useId + '"]').addClass('active').attr('aria-selected', 'true');
        $sections.addClass('hidden');
        $sections.filter('[data-tvsection="' + useId + '"]').removeClass('hidden');
      };

      const fromUrl = () => {
        try {
          return (new URL(window.location.href)).searchParams.get('tvsection') || '';
        } catch (e) { return ''; }
      };

      // Initial selection
      let id = fromUrl().trim();
      if (!id) id = (sessionStorage.getItem(KEY_TV) || '').trim();
      if (!id) id = String($tabs.first().data('tvsection') || 'configuration');
      activate(id);

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
          updateUrlAndReferrers(u);
        } catch (e) {}
      });

      // Keyboard
      $tabs.on('keydown', (e) => {
        const idx = $tabs.index(e.currentTarget);
        if (e.key === 'ArrowRight') {
          e.preventDefault();
          $tabs.eq((idx + 1) % $tabs.length).focus().click();
        } else if (e.key === 'ArrowLeft') {
          e.preventDefault();
          $tabs.eq((idx - 1 + $tabs.length) % $tabs.length).focus().click();
        }
      });
    }

    // --- 3) CONFIG REPEATER ---

    _getEditorHtml($ta) {
      const id = $ta.attr('id');
      if (window.tinymce && id) {
        const ed = window.tinymce.get(id);
        if (ed) return ed.getContent() || '';
      }
      return $ta.val() || '';
    }

    _readRowValues($row) {
      const vals = {};
      this.TV_FIELDS.forEach(k => {
        if (k.endsWith('_body')) {
          vals[k] = ($row.find('textarea[name$="[' + k + ']"]').val() || '').toString();
        } else if (k.endsWith('_role')) {
          vals[k] = ($row.find('select[name$="[' + k + ']"] option:selected').val() || '').toString();
        } else {
          vals[k] = ($row.find('input[name$="[' + k + ']"]').val() || '').toString();
        }
      });
      return vals;
    }

    _applyRowValues($row, vals) {
      this.TV_FIELDS.forEach(k => {
        if (k.endsWith('_body')) {
          $row.find('textarea[name$="[' + k + ']"]').val(vals[k] || '');
        } else if (k.endsWith('_role')) {
          $row.find('select[name$="[' + k + ']"]').val(vals[k] || '');
        } else {
          $row.find('input[name$="[' + k + ']"]').val(vals[k] || '');
        }
      });
      this._updateConfigSummary($row);
    }

    _updateConfigSummary($row) {
      const formId = ($row.find('input[name$="[form_id]"]').val() || '').trim() || 'New form';
      const approved = $row.find('select[name$="[approved_role]"] option:selected').text() || '—';
      const rejected = $row.find('select[name$="[rejected_role]"] option:selected').text() || '—';
      $row.find('[data-summary="form"]').text(formId);
      $row.find('[data-summary="approved"]').text(approved);
      $row.find('[data-summary="rejected"]').text(rejected);
    }

    _initWysiwyg($scope) {
      const $areas = $scope.find('.yardlii-tv-editor');
      if (!$areas.length || !window.wp || !window.wp.editor) return;

      const tryInit = () => {
        if (window.wp.editor.initialize) {
          $areas.each(function () {
            const $ta = $(this);
            if (!$ta.is(':visible') || $ta.data('tv-init')) return;
            window.wp.editor.initialize($ta.attr('id'), {
              tinymce: { wpautop: true, setup: (ed) => { ed.on('change', () => ed.save()); } },
              quicktags: true
            });
            $ta.data('tv-init', true);
          });
          return true;
        }
        return false;
      };
      if (!tryInit()) setTimeout(tryInit, 150);
    }

    bindConfigRowInteractions($row) {
      $row.on('input change', 'input[name$="[form_id]"], select[name$="[approved_role]"], select[name$="[rejected_role]"]', () => this._updateConfigSummary($row));
      this._updateConfigSummary($row);
      $row.on('toggle', (e) => { if (e.currentTarget.open) this._initWysiwyg($row); });
      if ($row.prop('open')) this._initWysiwyg($row);
    }

    bindConfigRepeater() {
      const $rowsWrap = this.$panel.find('#yardlii-tv-config-rows');
      const $template = this.$panel.find('#yardlii-tv-config-template');
      if (!$rowsWrap.length || !$template.length) return;

      // Bind existing rows
      $rowsWrap.find('.yardlii-tv-config-item').each((i, el) => {
        this.bindConfigRowInteractions(this.$(el));
      });

      // Add
      this.$panel.find('#yardlii-tv-add-config').on('click', () => {
        let max = -1;
        $rowsWrap.find('.yardlii-tv-config-item').each(function () {
          const n = parseInt($(this).data('index'), 10);
          if (!isNaN(n) && n > max) max = n;
        });
        const idx = max + 1;

        const html = $template.html().replace(/__i__/g, String(idx));
        const $node = this.$(html);
        $rowsWrap.append($node);
        $node.attr('open', true);
        this.bindConfigRowInteractions($node);
        this._initWysiwyg($node);
        try { $node[0].scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) {}
      });

      // Remove
      $rowsWrap.on('click', '[data-action="tv-remove-config-row"]', (e) => {
        const $row = this.$(e.currentTarget).closest('.yardlii-tv-config-item');
        if ($rowsWrap.find('.yardlii-tv-config-item').length <= 1) {
          if (!window.confirm('Remove the only configuration row?')) return;
        }
        $row.remove();
      });

      // Duplicate
      $rowsWrap.on('click', '[data-action="tv-duplicate-config-row"]', (e) => {
        const $src = this.$(e.currentTarget).closest('.yardlii-tv-config-item');
        if (!$src.length) return;
        
        const vals = this._readRowValues($src);
        
        let max = -1;
        $rowsWrap.find('.yardlii-tv-config-item').each(function () {
          const n = parseInt($(this).data('index'), 10);
          if (!isNaN(n) && n > max) max = n;
        });
        const idx = max + 1;
        
        const html = $template.html().replace(/__i__/g, String(idx));
        const $node = this.$(html);
        $rowsWrap.append($node);
        
        this.bindConfigRowInteractions($node);
        this._applyRowValues($node, vals);
        $node.attr('open', true);
        this._initWysiwyg($node);
        try { $node[0].scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) {}
      });
    }

    // --- 4) DRAG/SORT ---

    _renumberConfigNames() {
      this.$panel.find('#yardlii-tv-config-rows .yardlii-tv-config-item').each((i, el) => {
        const $row = this.$(el).attr('data-index', i);
        $row.find('[name^="yardlii_tv_form_configs["]').each(function () {
          this.name = this.name.replace(/\[\d+\]/, '[' + i + ']');
          if (this.id) this.id = this.id.replace(/__i__/, String(i));
        });
      });
    }

    bindConfigSort() {
      const $wrap = this.$panel.find('#yardlii-tv-config-rows');
      if (!$wrap.length || !this.$.fn.sortable) return;

      // Prevent toggling <summary> when grabbing the handle
      this.$panel.on('mousedown', '.tv-drag-handle', (e) => e.preventDefault());

      $wrap.sortable({
        items: '.yardlii-tv-config-item',
        handle: '.tv-drag-handle',
        axis: 'y',
        placeholder: 'tv-sort-placeholder',
        forcePlaceholderSize: true,
        tolerance: 'pointer',
        distance: 4,
        cancel: 'input,textarea,select,button,a,.wp-editor-wrap',
        start: (e, ui) => { ui.placeholder.height(ui.item.outerHeight()); },
        stop: () => { this._renumberConfigNames(); }
      });

      this.$panel.find('#yardlii-tv-form-configs').on('submit', () => this._renumberConfigNames());
    }

    // --- 5) PREVIEW OVERLAY (CHROME) ---

    _openPreview(html) {
      const $overlay = this.$panel.find('#yardlii-tv-preview-overlay');
      if (!$overlay.length) return;
      this.tvLastFocusedBtn = document.activeElement || null;
      $overlay.find('#yardlii-tv-preview-body').html(html || '<p>—</p>');
      $overlay.addClass('is-open').attr('aria-hidden', 'false').show();
      $overlay.find('[data-action="tv-preview-close"]').first().focus();
    }

    _closePreview() {
      const $overlay = this.$panel.find('#yardlii-tv-preview-overlay');
      $overlay.removeClass('is-open').attr('aria-hidden', 'true').hide();
      if (this.tvLastFocusedBtn) {
        try { this.tvLastFocusedBtn.focus(); } catch (e) {}
      }
      this.tvLastFocusedBtn = null;
    }

    bindPreviewChrome() {
      // Close with “×”
      this.$panel.on('click.tvPreviewClose', '[data-action="tv-preview-close"]', () => this._closePreview());

      // Click outside
      this.$panel.on('click.tvPreviewBackdrop', '#yardlii-tv-preview-overlay', (e) => {
        if (e.target && e.target.id === 'yardlii-tv-preview-overlay') this._closePreview();
      });

      // Escape key
      this.$(document).on('keydown.tvPreview', (e) => {
        if (e.key === 'Escape' || e.key === 'Esc') {
          const $overlay = this.$panel.find('#yardlii-tv-preview-overlay');
          if ($overlay.hasClass('is-open') || $overlay.is(':visible')) {
            e.preventDefault();
            this._closePreview();
          }
        }
      });
    }

    // --- 6) PER-ROW PREVIEW + SEND TEST ---

    _getRowPreviewPayload($row) {
      const formId = ($row.find('input[name$="[form_id]"]').val() || '').trim();
      const type = ($row.find('select[name$="[preview_type]"]').val() || '').trim();
      const userId = ($row.find('input[name$="[preview_user]"]').val() || '0').trim();

      const subjField = (type === 'approve') ? 'approve_subject' : 'reject_subject';
      const bodyField = (type === 'approve') ? 'approve_body' : 'reject_body';

      const $subj = $row.find('input[name$="[' + subjField + ']"]');
      const $body = $row.find('textarea[name$="[' + bodyField + ']"]');

      return {
        form_id: formId,
        email_type: type,
        user_id: userId,
        subject: ($subj.val() || '').toString(),
        body: this._getEditorHtml($body)
      };
    }

    bindRowPreview() {
      this.$panel.on('click', '[data-action="tv-row-preview-email"]', (e) => {
        const $row = this.$(e.currentTarget).closest('.yardlii-tv-config-item');
        if (!$row.length) return;
        const payload = this._getRowPreviewPayload($row);

        if (!payload.form_id || !payload.email_type) {
          alert('Please enter a Form ID and choose a preview type.');
          return;
        }

        const ajaxUrl = this.tvData.ajax || '/wp-admin/admin-ajax.php';
        const nonce = this.tvData.noncePreview || '';

        this._openPreview('<p>Loading preview…</p>');

        this.$.post(ajaxUrl, Object.assign({
          action: 'yardlii_tv_preview_email',
          _ajax_nonce: nonce
        }, payload))
        .done(resp => this._openPreview(resp.data.html || 'Error: Invalid response.'))
        .fail(() => this._openPreview('Error: AJAX request failed.'));
      });
    }

    _rowBanner($row, type, msg) {
      const cls = (type === 'success') ? 'yardlii-banner--success'
                : (type === 'info') ? 'yardlii-banner--info'
                : 'yardlii-banner--error';
      const $box = this.$('<div class="yardlii-banner ' + cls + '"><p>' + msg + '</p></div>');
      $row.find('.yardlii-section-content').prepend($box);
      setTimeout(() => { try { $box.fadeOut(200, () => $box.remove()); } catch (e) {} }, 4200);
    }

    bindRowSendTest() {
      this.$panel.on('click', '[data-action="tv-row-send-test"]', (e) => {
        const $btn = this.$(e.currentTarget);
        const $row = $btn.closest('.yardlii-tv-config-item');
        if (!$row.length) return;

        const payload = this._getRowPreviewPayload($row);
        if (!payload.form_id || !payload.email_type) {
          alert('Please enter a Form ID and choose a preview type.');
          return;
        }

        let to = ($row.find('input[name$="[preview_to]"]').val() || '').trim();
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(to || '').trim())) {
          alert('Please enter a valid recipient email address.');
          return;
        }

        $btn.prop('disabled', true).text('Sending...');

        const ajaxUrl = this.tvData.ajax || '/wp-admin/admin-ajax.php';
        const nonce = this.tvData.nonceSend || '';

        this.$.post(ajaxUrl, Object.assign({
          action: 'yardlii_tv_send_test_email',
          _ajax_nonce: nonce,
          to: to
        }, payload))
        .done(resp => {
          if (resp.success) {
            this._rowBanner($row, 'success', resp.data.message || 'Test email sent.');
          } else {
            const m = (resp && resp.data && resp.data.message) ? resp.data.message : 'Sending failed.';
            this._rowBanner($row, 'error', m);
          }
        })
        .fail(xhr => {
          this._rowBanner($row, 'error', 'XHR error: ' + (xhr.status || '') + ' ' + (xhr.statusText || ''));
        })
        .always(() => {
          $btn.prop('disabled', false).text('Send test email');
        });
      });
    }

    // --- 7) DIAGNOSTICS (REST TEST) ---

    bindDiagnostics() {
      this.$panel.on('click', '#yardlii-tv-api-test', (e) => {
        e.preventDefault();
        const uid = String(this.$panel.find('#yardlii-tv-api-user').val() || '').trim();
        if (!uid) { alert('Enter a User ID'); return; }

        const root = this.tvData.restRoot || '/wp-json/';
        const url = root.replace(/\/?$/, '/') + 'yardlii/v1/verification-status/' + encodeURIComponent(uid);
        const headers = { 'X-WP-Nonce': this.tvData.restNonce || '' };
        const $output = this.$panel.find('#yardlii-tv-api-output');

        $output.text('Testing...');

        fetch(url, { headers: headers, credentials: 'same-origin' })
          .then(r => r.json())
          .then(data => $output.text(JSON.stringify(data, null, 2)))
          .catch(err => $output.text('Fetch error: ' + String(err)));
      });
    }

    // --- 8) BANNERS ---

    bindBanners() {
      this.$panel.on('click', '[data-dismiss="yardlii-banner"]', function () {
        $(this).closest('.yardlii-banner').remove();
      });

      const hasBanner = this.$panel.find('.yardlii-banner, .notice').length > 0;
      if (hasBanner) {
        try {
          const url = new URL(window.location.href);
          ['tv_notice', 'tv_reset', 'tv_seed', 'settings-updated'].forEach(k => url.searchParams.delete(k));
          history.replaceState({}, '', url.toString());
        } catch(e) {}
      }
    }

    // --- 10) PROVIDERS HELP ---

    bindProvidersHelp() {
      this.$panel.on('click', '[data-action="tv-show-providers-help"]', (e) => {
        e.preventDefault();
        const $template = this.$panel.find('#tv-providers-help');
        if (!$template.length) return;
        this._openPreview($template.html());
      });
    }

    // --- 11) TOOLS: Send test for all forms ---

    bindToolsSendAllTests() {
      if (!this.$panel.find('#tv-send-all-tests').length) return;

      this.$panel.on('click.tvSendAll', '#tv-send-all-tests', (e) => {
        const $btn = this.$(e.currentTarget);
        const to = String(this.$panel.find('#tv-testall-to').val() || '').trim()
                 || String(this.$panel.find('#tv-testall-to').attr('placeholder') || '').trim();
        const type = String(this.$panel.find('input[name="tv-testall-type"]:checked').val() || 'approve').trim();

        if (!to || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(to)) {
          alert('Please enter a valid recipient email.');
          return;
        }

        const ajaxUrl = this.tvData.ajax || '/wp-admin/admin-ajax.php';
        const nonce = this.tvData.nonceSend || '';
        const $box = this.$panel.find('#tv-testall-results');

        $btn.prop('disabled', true).text('Sending...');
        $box.empty().removeClass('yardlii-banner--error yardlii-banner--success').hide();

        this.$.post(ajaxUrl, {
          action: 'yardlii_tv_send_all_tests',
          _ajax_nonce: nonce,
          to: to,
          type: type
        })
        .done(resp => {
          const m = resp.data.message || 'Request complete.';
          $box.text(m).addClass(resp.success ? 'yardlii-banner--success' : 'yardlii-banner--error').show();
        })
        .fail(() => {
          $box.text('AJAX error failed.').addClass('yardlii-banner--error').show();
        })
        .always(() => {
          $btn.prop('disabled', false).text('Send test for all forms');
        });
      });
    }

  } // END CLASS TrustVerificationAdminPanel


  /**
   * Manages the "History" modal, which is appended to document.body
   * and listens for clicks globally.
   */
  class TrustVerificationHistoryModal {
    constructor($, tvData) {
      this.$ = $;
      this.tvData = tvData || {};
      this.$modal = null;
      this.tvLastFocusedBtn = null;
    }

    init() {
      // Listen globally for a click on a history trigger
      this.$(document).on('click', 'a[data-action="tv-row-history"]', (e) => {
        e.preventDefault();
        this.tvLastFocusedBtn = e.currentTarget;
        const $a = this.$(e.currentTarget);
        const postId = parseInt($a.data('post') || '0', 10);
        const nonce = $a.data('nonce') || '';

        if (!postId || !nonce) return;

        this._ensureModal();
        this.$modal.find('.yardlii-modal__body').html('<div class="tv-history tv-history--loading"><p>Loading…</p></div>');
        this.$modal.addClass('is-open');
        this.$modal.find('.yardlii-modal__title').text('History for Request #' + postId);
        this._fetchHistory(postId, nonce);
      });
    }

    _ensureModal() {
      if (this.$modal) return;

      const modalHtml = [
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

      this.$modal = this.$('<div id="yardlii-tv-history-modal" class="yardlii-modal"></div>');
      this.$modal.html(modalHtml);
      this.$('body').append(this.$modal);

      // Bind close handlers
      this.$modal.on('click', (e) => {
        if (e.target && e.target.getAttribute('data-close') === '1') {
          this._close();
        }
      });
      
      this.$(document).on('keydown.tvModal', (e) => {
        if (e.key === 'Escape' || e.key === 'Esc') {
          if (this.$modal.hasClass('is-open')) {
            this._close();
          }
        }
      });

      // Bind copy handler
      this.$modal.find('[data-copy="1"]').on('click', (e) => this._handleCopy(e));
    }

    _close() {
      if (!this.$modal) return;
      this.$modal.removeClass('is-open');
      if (this.tvLastFocusedBtn) {
        try { this.tvLastFocusedBtn.focus(); } catch(e) {}
        this.tvLastFocusedBtn = null;
      }
    }

    _fetchHistory(postId, nonce) {
      const $body = this.$modal.find('.yardlii-modal__body');
      const ajaxUrl = this.tvData.ajax || '/wp-admin/admin-ajax.php';

      this.$.post(ajaxUrl, {
        action: 'yardlii_tv_history_load',
        request_id: postId,
        _ajax_nonce: nonce
      })
      .done(json => {
        if (!json || !json.success) {
          $body.html('<div class="notice notice-error"><p>' + (json.data.message || 'Failed to load history.') + '</p></div>');
        } else {
          $body.html(json.data.html || '<p>No history found.</p>');
        }
      })
      .fail(() => {
        $body.html('<div class="notice notice-error"><p>XHR error while loading history.</p></div>');
      });
    }

    _collectHistoryText() {
      const $body = this.$modal.find('.yardlii-modal__body');
      const $items = $body.find('.tv-history-item');
      if (!$items.length) return ($body.text() || '').trim();
      const lines = [];
      $items.each(function () {
        const $it = $(this);
        const head = $it.find('.tv-history-head').text().replace(/\s+/g, ' ').trim();
        if (head) lines.push(head);
        $it.find('.tv-history-line').each(function () {
          lines.push('  ' + $(this).text().replace(/\s+/g, ' ').trim());
        });
      });
      return lines.join('\n');
    }

    async _handleCopy(e) {
      const $btn = this.$(e.currentTarget);
      const original = $btn.text();
      const txt = this._collectHistoryText();

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
      } catch (err) {
        $btn.text('Copy failed').prop('disabled', true);
        setTimeout(() => { $btn.text(original).prop('disabled', false); }, 1200);
      }
    }

  } // END CLASS TrustVerificationHistoryModal


  // --- INITIALIZATION ---

  $(function () {
    // Init the main panel logic if we are on the page
    if ($('#yardlii-tab-trust-verification').length) {
      const tvPanel = new TrustVerificationAdminPanel($, window.YardliiTV);
      tvPanel.init();
    }

    // The history modal listener is global, so it *always* inits
    const tvModal = new TrustVerificationHistoryModal($, window.YardliiTV);
    tvModal.init();
  });

})(jQuery);