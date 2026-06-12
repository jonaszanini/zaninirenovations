(function () {
  'use strict';

  function errorFromBody(body, status) {
    if (body && body.error) return body.error;
    if (body && body.errors) {
      var keys = Object.keys(body.errors);
      if (keys.length && body.errors[keys[0]] && body.errors[keys[0]][0]) {
        return body.errors[keys[0]][0];
      }
    }
    if (body && body.message) return body.message;
    if (status === 504 || status === 502) {
      return 'The server timed out. Try again with a shorter brief.';
    }
    return 'HTTP ' + status;
  }

  function init(root) {
    if (!root || root.getAttribute('data-quotation-work-desc-ai-init') === '1') return;
    root.setAttribute('data-quotation-work-desc-ai-init', '1');

    var textareaId = root.getAttribute('data-textarea-id') || '';
    var improveUrl = root.getAttribute('data-improve-url') || '';
    var csrfToken = root.getAttribute('data-csrf') || '';
    var textarea = document.getElementById(textareaId);
    var btn = root.querySelector('.quotation-work-description-ai__btn');
    var loadingEl = root.querySelector('.quotation-work-description-ai__loading');
    var statusEl = root.querySelector('.quotation-work-description-ai__status');
    var historyMount = root.querySelector('[data-history-mount]');

    if (!btn || !textarea || !improveUrl) return;

    var history = null;
    if (historyMount && window.AdminAiTextHistory) {
      history = window.AdminAiTextHistory.create({
        storageKey: 'quotation-work-desc:' + textareaId,
        mountEl: historyMount,
        title: 'Work description versions',
        getText: function () { return textarea.value; },
        setText: function (text) { textarea.value = text; },
      });
    }

    function setStatus(message, isError) {
      if (!statusEl) return;
      statusEl.textContent = message || '';
      statusEl.classList.toggle('is-error', !!isError);
    }

    function setLoading(active) {
      if (loadingEl) loadingEl.hidden = !active;
      btn.disabled = !!active;
    }

    btn.addEventListener('click', async function () {
      var notes = textarea.value.trim();
      if (!notes) {
        setStatus('Enter a brief description first, then click Improve description with AI.', true);
        textarea.focus();
        return;
      }

      if (history) {
        history.captureOriginalIfNeeded();
      }

      setLoading(true);
      setStatus('Rewriting your description with Gemini…', false);

      try {
        var res = await fetch(improveUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken,
          },
          body: JSON.stringify({ contractor_notes: notes }),
        });

        var text = await res.text();
        var body = null;
        if (text) {
          try {
            body = JSON.parse(text);
          } catch (ignore) {
            throw new Error(errorFromBody(null, res.status));
          }
        }

        if (!res.ok) {
          throw new Error(errorFromBody(body, res.status));
        }

        var description = (body && body.description) ? String(body.description).trim() : '';
        if (!description) {
          throw new Error('Gemini did not return a description.');
        }

        if (history) {
          history.addVersion(description);
        }

        textarea.value = description;
        textarea.focus();
        setStatus('Description updated. Use the history icon to restore earlier versions.', false);
      } catch (err) {
        setStatus(err.message || 'Could not improve description.', true);
      } finally {
        setLoading(false);
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.quotation-work-description-ai').forEach(init);
  });
})();
