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
    var googleRoot = document.querySelector('.admin-google-photos');
    var btn = document.getElementById('portfolio-desc-ai-btn');
    var loadingEl = document.getElementById('portfolio-desc-ai-loading');
    var optionsEl = document.getElementById('portfolio-desc-ai-options');
    var statusEl = document.getElementById('portfolio-desc-ai-status');
    var csrfToken = config.csrfToken || '';
    var improveUrl = config.improveUrl || '';

    if (!btn || !descriptionEl || !improveUrl) return;

    var storedOptions = { option_1: '', option_2: '' };
    var activeOption = 1;

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

    function updateOptionButtons() {
      if (!optionsEl) return;
      optionsEl.querySelectorAll('[data-portfolio-desc-option]').forEach(function (button) {
        var num = parseInt(button.getAttribute('data-portfolio-desc-option') || '1', 10);
        button.classList.toggle('is-active', num === activeOption);
      });
    }

    function applyOption(num) {
      activeOption = num;
      var text = num === 2 ? storedOptions.option_2 : storedOptions.option_1;
      if (text) {
        descriptionEl.value = text;
        descriptionEl.focus();
      }
      updateOptionButtons();
    }

    if (optionsEl) {
      optionsEl.querySelectorAll('[data-portfolio-desc-option]').forEach(function (button) {
        button.addEventListener('click', function () {
          applyOption(parseInt(button.getAttribute('data-portfolio-desc-option') || '1', 10));
        });
      });
    }

    btn.addEventListener('click', async function () {
      var brief = descriptionEl.value.trim();
      if (!brief) {
        setStatus('Enter a brief description first, then click Improve description with AI.', true);
        descriptionEl.focus();
        return;
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
      if (optionsEl) optionsEl.hidden = true;

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

        storedOptions.option_1 = body.option_1 || body.description || '';
        storedOptions.option_2 = body.option_2 || storedOptions.option_1;
        activeOption = 1;
        applyOption(1);

        if (optionsEl && storedOptions.option_1 !== storedOptions.option_2) {
          optionsEl.hidden = false;
        }

        var note = 'Description updated (Option 1).';
        if (typeof body.image_count === 'number') {
          note += ' Used ' + body.image_count + ' photo' + (body.image_count === 1 ? '' : 's') + '.';
        }
        if (storedOptions.option_1 !== storedOptions.option_2) {
          note += ' Switch to Option 2 if you prefer the technical version.';
        }
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
