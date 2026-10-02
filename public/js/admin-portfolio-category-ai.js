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
      return 'The server timed out. Try again with fewer photos.';
    }
    return 'HTTP ' + status;
  }

  function googlePayloads() {
    var googleRoot = document.querySelector('.admin-google-photos');
    if (!googleRoot || !googleRoot.googlePhotosPicker) return [];
    return googleRoot.googlePhotosPicker.getCheckedPayloads();
  }

  function buildTile(category) {
    var label = document.createElement('label');
    label.className = 'admin-checkbox admin-checkbox-tile';

    var input = document.createElement('input');
    input.type = 'checkbox';
    input.className = 'admin-checkbox__input';
    input.name = 'project_category_ids[]';
    input.value = String(category.id);
    input.checked = true;

    var mark = document.createElement('span');
    mark.className = 'admin-checkbox__mark';
    mark.setAttribute('aria-hidden', 'true');

    var text = document.createElement('span');
    text.className = 'admin-checkbox__text';
    text.textContent = category.name || '';

    label.appendChild(input);
    label.appendChild(mark);
    label.appendChild(text);
    return label;
  }

  function applySelection(categories) {
    var box = document.getElementById('portfolio-category-checkboxes');
    if (!box) return;

    var wanted = {};
    categories.forEach(function (category) {
      if (!category || category.id == null) return;
      wanted[String(category.id)] = category;
    });

    box.querySelectorAll('input[name="project_category_ids[]"]').forEach(function (input) {
      input.checked = !!wanted[input.value];
    });

    categories.forEach(function (category) {
      if (!category || category.id == null) return;
      if (box.querySelector('input[name="project_category_ids[]"][value="' + String(category.id) + '"]')) {
        return;
      }
      box.appendChild(buildTile(category));
    });
  }

  function statusMessage(categories, imageCount) {
    var checked = [];
    var created = [];
    categories.forEach(function (category) {
      var name = category && category.name ? category.name : '';
      if (!name) return;
      if (category.created) created.push(name);
      else checked.push(name);
    });

    var parts = [];
    if (checked.length) {
      parts.push('Checked ' + checked.join(', ') + '.');
    }
    if (created.length) {
      parts.push('Created and checked ' + created.join(', ') + '.');
    }
    if (imageCount === 0) {
      parts.push('No photos were attached.');
    } else if (typeof imageCount === 'number') {
      parts.push('Used ' + imageCount + ' photo' + (imageCount === 1 ? '' : 's') + '.');
    }
    return parts.join(' ');
  }

  function confirmWithoutDescription() {
    var message = 'With a description, the AI can do a better job. Send it anyway?';
    if (window.AdminDialog && typeof window.AdminDialog.confirm === 'function') {
      return window.AdminDialog.confirm(message, {
        title: 'No description',
        confirmLabel: 'Send anyway',
        cancelLabel: 'Cancel',
      });
    }
    return Promise.resolve(window.confirm(message));
  }

  function init(config) {
    var root = document.getElementById('portfolio-category-ai');
    if (!root || root.getAttribute('data-portfolio-category-ai-init') === '1') return;
    root.setAttribute('data-portfolio-category-ai-init', '1');

    var titleEl = document.getElementById(config.titleInputId || 'title');
    var descriptionEl = document.getElementById(config.descriptionInputId || 'description');
    var fileInput = document.getElementById(config.fileInputId || 'photos');
    var btn = document.getElementById('portfolio-category-ai-btn');
    var loadingEl = document.getElementById('portfolio-category-ai-loading');
    var statusEl = document.getElementById('portfolio-category-ai-status');
    var csrfToken = config.csrfToken || '';
    var categorizeUrl = config.categorizeUrl || '';

    if (!btn || !titleEl || !categorizeUrl) return;

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
      var title = titleEl.value.trim();
      if (!title) {
        setStatus('Enter a project title first, then try Auto categorize with AI.', true);
        titleEl.focus();
        return;
      }

      var description = descriptionEl ? descriptionEl.value.trim() : '';
      if (!description) {
        var proceed = await confirmWithoutDescription();
        if (!proceed) {
          setStatus('Add a description first if you want a more accurate result.', false);
          if (descriptionEl) descriptionEl.focus();
          return;
        }
      }

      var formData = new FormData();
      formData.append('title', title);
      formData.append('description', description);

      if (fileInput && fileInput.files) {
        Array.prototype.forEach.call(fileInput.files, function (file) {
          formData.append('photos[]', file);
        });
      }

      googlePayloads().forEach(function (payload) {
        formData.append('google_picker_items[]', payload);
      });

      document.querySelectorAll('input[name="draft_photos[]"]').forEach(function (input) {
        if (input.value) formData.append('draft_photos[]', input.value);
      });

      setLoading(true);
      setStatus('Categorizing from the title, description, and photos…', false);

      try {
        var res = await fetch(categorizeUrl, {
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

        var categories = Array.isArray(body.categories) ? body.categories : [];
        if (!categories.length) {
          throw new Error('The AI did not return any categories.');
        }

        applySelection(categories);
        setStatus(statusMessage(categories, body.image_count), false);
      } catch (err) {
        setStatus(err.message || 'Could not categorize this project.', true);
      } finally {
        setLoading(false);
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var config = window.portfolioCategoryAiConfig;
    if (config) init(config);
  });
})();
