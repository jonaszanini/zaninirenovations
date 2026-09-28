/**
 * Client email message panel (admin.partials.quotation_client_email_message).
 * TinyMCE starts the first time the panel is opened; "Improve with AI" refines
 * the text through /admin/quotations/client-email-message/ai.
 */
(function () {
  'use strict';

  var TINYMCE_SRC = 'https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js';
  var tinymceLoading = null;

  function loadTinymce() {
    if (window.tinymce) return Promise.resolve(window.tinymce);
    if (tinymceLoading) return tinymceLoading;
    tinymceLoading = new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      s.src = TINYMCE_SRC;
      s.referrerPolicy = 'origin';
      s.onload = function () { resolve(window.tinymce); };
      s.onerror = function () { reject(new Error('Could not load the text editor.')); };
      document.head.appendChild(s);
    });
    return tinymceLoading;
  }

  function looksLikeHtml(text) {
    return /<\/?[a-z][\s\S]*>/i.test(String(text || ''));
  }

  function escapeHtml(text) {
    return String(text || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function errorFromBody(body, status) {
    if (body && body.error) return body.error;
    if (body && body.errors) {
      var k = Object.keys(body.errors)[0];
      if (k && body.errors[k][0]) return body.errors[k][0];
    }
    if (body && body.message && typeof body.message === 'string' && status >= 400) return body.message;
    return 'HTTP ' + status;
  }

  function init(root) {
    if (root.getAttribute('data-client-email-init') === '1') return;
    root.setAttribute('data-client-email-init', '1');

    var textarea = document.getElementById(root.getAttribute('data-textarea-id') || '');
    var formId = root.getAttribute('data-form-id') || '';
    var improveUrl = root.getAttribute('data-improve-url') || '';
    var csrf = root.getAttribute('data-csrf') || '';
    var aiBtn = root.querySelector('[data-client-email-ai]');
    var loadingEl = root.querySelector('[data-client-email-loading]');
    var statusEl = root.querySelector('[data-client-email-status]');
    var historyMount = root.querySelector('[data-history-mount]');
    if (!textarea) return;

    var editor = null;
    var editorStarting = null;

    function getHtml() {
      return editor ? String(editor.getContent() || '').trim() : String(textarea.value || '').trim();
    }

    function getPlain() {
      if (editor) return String(editor.getContent({ format: 'text' }) || '').trim();
      return String(textarea.value || '').replace(/<[^>]+>/g, ' ').trim();
    }

    function setHtml(html) {
      if (editor) {
        editor.setContent(html);
        editor.save();
      } else {
        textarea.value = html;
      }
    }

    function startEditor() {
      if (editor || editorStarting) return editorStarting || Promise.resolve(editor);
      var isDark = document.documentElement.classList.contains('admin-dark');
      editorStarting = loadTinymce().then(function (tinymce) {
        if (!looksLikeHtml(textarea.value) && String(textarea.value || '').trim() !== '') {
          textarea.value = String(textarea.value).split(/\n{2,}/).map(function (b) {
            return '<p>' + escapeHtml(b).replace(/\n/g, '<br>') + '</p>';
          }).join('');
        }
        return tinymce.init({
          target: textarea,
          skin: isDark ? 'oxide-dark' : 'oxide',
          content_css: isDark ? 'dark' : 'default',
          menubar: false,
          statusbar: false,
          plugins: 'autolink lists link paste',
          toolbar: 'bold italic underline | bullist numlist | link | removeformat',
          toolbar_mode: 'wrap',
          height: 220,
          branding: false,
          promotion: false,
          content_style: isDark
            ? 'body { font-family: Arial, Helvetica, sans-serif; font-size: 11pt; color: #e2e8f0; background: #0f172a; line-height: 1.5; padding: 8px; } p { margin: 0 0 0.6em; } a { color: #38bdf8; }'
            : 'body { font-family: Arial, Helvetica, sans-serif; font-size: 11pt; color: #1f2937; line-height: 1.5; padding: 8px; } p { margin: 0 0 0.6em; }',
          setup: function (ed) {
            ed.on('change keyup undo redo SetContent', function () { ed.save(); });
          },
        }).then(function (editors) {
          editor = editors && editors[0] ? editors[0] : null;
          return editor;
        });
      }).catch(function (err) {
        setStatus(err.message || 'Could not load the text editor. You can still type plain text.', true);
        editorStarting = null;
        return null;
      });
      return editorStarting;
    }

    if (root.open) startEditor();
    root.addEventListener('toggle', function () {
      if (root.open) startEditor();
    });

    if (formId) {
      var form = document.getElementById(formId);
      if (form) {
        form.addEventListener('submit', function () {
          if (editor) editor.save();
        }, true);
      }
    }

    var history = null;
    if (historyMount && window.AdminAiTextHistory) {
      history = window.AdminAiTextHistory.create({
        storageKey: root.getAttribute('data-history-key') || ('quotation-client-email:' + textarea.id),
        mountEl: historyMount,
        title: 'Client email message versions',
        getText: getHtml,
        setText: setHtml,
      });
      if (getPlain() === '') history.clear();
    }

    function setStatus(message, isError) {
      if (!statusEl) return;
      statusEl.textContent = message || '';
      statusEl.classList.toggle('is-error', !!isError);
    }

    function setLoading(active) {
      if (loadingEl) loadingEl.hidden = !active;
      if (aiBtn) aiBtn.disabled = !!active;
    }

    if (aiBtn && improveUrl) {
      aiBtn.addEventListener('click', function () {
        if (getPlain() === '') {
          setStatus('Write a message first, then click Improve with AI.', true);
          if (editor) editor.focus(); else textarea.focus();
          return;
        }
        if (history) history.captureOriginalIfNeeded();
        setLoading(true);
        setStatus('Refining your message…', false);

        fetch(improveUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf,
          },
          body: JSON.stringify({ message: getHtml() }),
        }).then(function (res) {
          return res.text().then(function (text) {
            var body = null;
            try { body = text ? JSON.parse(text) : null; } catch (e) { body = null; }
            if (!res.ok) throw new Error(errorFromBody(body, res.status));
            var html = body && typeof body.message === 'string' ? body.message.trim() : '';
            if (!html) throw new Error('Gemini did not return any text.');
            return html;
          });
        }).then(function (html) {
          setHtml(html);
          if (history) history.addVersion(html);
          setStatus('Message refined. Use the history icon to restore an earlier version.', false);
        }).catch(function (err) {
          setStatus(err.message || 'Could not refine the message.', true);
        }).finally(function () {
          setLoading(false);
        });
      });
    }
  }

  function boot() {
    document.querySelectorAll('[data-client-email-message]').forEach(init);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
