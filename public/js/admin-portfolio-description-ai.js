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
      return 'The server timed out. Try again with fewer photos or a shorter brief.';
    }
    return 'HTTP ' + status;
  }

  function init(config) {
    var root = document.getElementById('portfolio-description-ai');
    if (!root || root.getAttribute('data-portfolio-desc-ai-init') === '1') return;
    root.setAttribute('data-portfolio-desc-ai-init', '1');

    var descriptionEl = document.getElementById(config.descriptionInputId || 'description');
    var fileInput = document.getElementById(config.fileInputId || 'photos');
    var googleRoot = null;
    document.querySelectorAll('.admin-google-photos').forEach(function (el) {
      if (!googleRoot && !el.closest('#admin-photo-edit-modal')) googleRoot = el;
    });
    var btn = document.getElementById('portfolio-desc-ai-btn');
    var loadingEl = document.getElementById('portfolio-desc-ai-loading');
    var statusEl = document.getElementById('portfolio-desc-ai-status');
    var historyMount = root.querySelector('[data-history-mount]');
    var csrfToken = config.csrfToken || '';
    var improveUrl = config.improveUrl || '';
    var storageKey = config.historyKey
      || ('portfolio-desc:' + (config.descriptionInputId || 'description'));

    if (!btn || !descriptionEl || !improveUrl) return;

    var history = null;
    if (historyMount && window.AdminAiTextHistory) {
      history = window.AdminAiTextHistory.create({
        storageKey: storageKey,
        mountEl: historyMount,
        title: 'Portfolio description versions',
        getText: function () { return descriptionEl.value; },
        setText: function (text) {
          descriptionEl.value = text;
          descriptionEl.focus();
        },
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

    function googlePayloads() {
      if (!googleRoot || !googleRoot.googlePhotosPicker) return [];
      return googleRoot.googlePhotosPicker.getCheckedPayloads();
    }

    btn.addEventListener('click', async function () {
      var brief = descriptionEl.value.trim();
      if (!brief) {
        setStatus('Enter a brief description first, then click Improve description with AI.', true);
        descriptionEl.focus();
        return;
      }

      if (history) {
        history.captureOriginalIfNeeded();
      }

      var formData = new FormData();
      formData.append('description', brief);

      if (fileInput && fileInput.files) {
        Array.prototype.forEach.call(fileInput.files, function (file) {
          formData.append('photos[]', file);
        });
      }

      googlePayloads().forEach(function (payload) {
        formData.append('google_picker_items[]', payload);
      });

      setLoading(true);
      setStatus('Analyzing your description and photos with Gemini…', false);

      try {
        var res = await fetch(improveUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken,
          },
          body: formData,
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

        var option1 = body.option_1 || body.description || '';
        var option2 = body.option_2 || option1;

        if (history) {
          if (option1) {
            history.addVersion(option1, option2 !== option1 ? 'Craftsmanship' : undefined);
            descriptionEl.value = option1;
          }
          if (option2 && option2 !== option1) {
            history.addVersion(option2, 'Technical');
          }
          descriptionEl.focus();
        } else {
          descriptionEl.value = option1;
          descriptionEl.focus();
        }

        var note = 'Description updated.';
        if (typeof body.image_count === 'number') {
          note += ' Used ' + body.image_count + ' photo' + (body.image_count === 1 ? '' : 's') + '.';
        }
        if (option2 && option2 !== option1) {
          note += ' Both options are in version history.';
        }
        note += ' Use the history icon to restore earlier versions.';
        setStatus(note, false);
      } catch (err) {
        setStatus(err.message || 'Could not improve description.', true);
      } finally {
        setLoading(false);
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var boot = window.portfolioDescriptionAiConfig;
    if (boot) init(boot);
  });
})();
