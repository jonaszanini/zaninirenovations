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
      return 'The server timed out while editing. Each photo is processed separately; try again or use fewer photos.';
    }
    return 'HTTP ' + status;
  }

  function fetchJsonPost(url, formData, csrfToken) {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken,
      },
      body: formData,
    }).then(function (res) {
      return res.text().then(function (text) {
        var body = null;
        if (text) {
          try {
            body = JSON.parse(text);
          } catch (ignore) {
            if (!res.ok) {
              throw new Error(errorFromBody(null, res.status));
            }
            throw new Error('Server returned an unexpected response (HTTP ' + res.status + ').');
          }
        }
        if (!res.ok) {
          throw new Error(errorFromBody(body, res.status));
        }
        return body;
      });
    });
  }

  function base64ToFile(base64, filename, mime) {
    var bin = atob(base64);
    var len = bin.length;
    var arr = new Uint8Array(len);
    for (var i = 0; i < len; i++) {
      arr[i] = bin.charCodeAt(i);
    }
    return new File([arr], filename, { type: mime || 'image/webp' });
  }

  function appendFilesToInput(input, newFiles) {
    if (!input || !newFiles.length) return;
    var dt = new DataTransfer();
    if (input.files) {
      Array.prototype.forEach.call(input.files, function (file) {
        dt.items.add(file);
      });
    }
    newFiles.forEach(function (file) {
      dt.items.add(file);
    });
    input.files = dt.files;
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function googleJobLabel(payload, index) {
    try {
      var data = JSON.parse(payload);
      if (data && data.filename) return data.filename;
    } catch (ignore) {}
    return 'Google photo ' + (index + 1);
  }

  function init(config) {
    var root = document.getElementById('portfolio-ai-improve');
    if (!root || root.getAttribute('data-portfolio-ai-init') === '1') return;
    root.setAttribute('data-portfolio-ai-init', '1');

    var fileInput = document.getElementById(config.fileInputId || 'photos');
    var googleRoot = document.querySelector('.admin-google-photos');
    var promptEl = document.getElementById('portfolio-ai-prompt');
    var applyBtn = document.getElementById('portfolio-ai-apply-btn');
    var improveBtn = document.getElementById('portfolio-ai-improve-prompt-btn');
    var loadingEl = document.getElementById('portfolio-ai-loading');
    var loadingTextEl = document.getElementById('portfolio-ai-loading-text');
    var statusEl = document.getElementById('portfolio-ai-status');
    var maxTotal = parseInt(config.maxTotal || '15', 10);
    var csrfToken = config.csrfToken || '';
    var improveUrl = config.improveUrl || '';
    var aiImproveUrl = config.aiImproveUrl || '';

    if (!applyBtn || !fileInput) return;

    function setStatus(message, isError) {
      if (!statusEl) return;
      statusEl.textContent = message || '';
      statusEl.classList.toggle('is-error', !!isError);
    }

    function setLoading(active, message) {
      if (loadingEl) loadingEl.hidden = !active;
      if (loadingTextEl && message) loadingTextEl.textContent = message;
      applyBtn.disabled = !!active;
      if (improveBtn) improveBtn.disabled = !!active;
    }

    function uploadCount() {
      return fileInput.files ? fileInput.files.length : 0;
    }

    function googleCheckedCount() {
      if (!googleRoot || !googleRoot.googlePhotosPicker) return 0;
      return googleRoot.googlePhotosPicker.getCheckedCount();
    }

    function googlePayloads() {
      if (!googleRoot || !googleRoot.googlePhotosPicker) return [];
      return googleRoot.googlePhotosPicker.getCheckedPayloads();
    }

    function totalSourceCount() {
      var google = googlePayloads().length;
      if (google > 0) return google;
      return uploadCount();
    }

    function buildJobs(prompt) {
      var jobs = [];
      var google = googlePayloads();

      if (google.length > 0) {
        google.forEach(function (payload, index) {
          jobs.push({
            label: googleJobLabel(payload, index),
            buildFormData: function () {
              var fd = new FormData();
              fd.append('prompt', prompt);
              fd.append('google_picker_items[]', payload);
              return fd;
            },
          });
        });
        return jobs;
      }

      if (fileInput.files) {
        Array.prototype.forEach.call(fileInput.files, function (file, index) {
          jobs.push({
            label: file.name || ('Upload ' + (index + 1)),
            buildFormData: function () {
              var fd = new FormData();
              fd.append('prompt', prompt);
              fd.append('photos[]', file);
              return fd;
            },
          });
        });
      }

      return jobs;
    }

    if (improveBtn && improveUrl) {
      improveBtn.addEventListener('click', async function () {
        var prompt = promptEl ? promptEl.value.trim() : '';
        if (!prompt) {
          setStatus('Enter edit instructions first, then click Improve prompt.', true);
          if (promptEl) promptEl.focus();
          return;
        }

        setLoading(true, 'Improving prompt with Gemini…');
        setStatus('', false);

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
            body: JSON.stringify({ prompt: prompt }),
          });
          var text = await res.text();
          var body = text ? JSON.parse(text) : null;
          if (!res.ok) {
            throw new Error(errorFromBody(body, res.status));
          }
          if (promptEl && body.prompt) {
            promptEl.value = body.prompt;
            promptEl.focus();
          }
          var note = 'Prompt improved. Review it, then click Apply AI improvements.';
          if (body.why_better) {
            var why = body.why_better;
            if (why.length > 240) why = why.slice(0, 239) + '…';
            note += ' ' + why;
          }
          setStatus(note, false);
        } catch (err) {
          setStatus(err.message || 'Could not improve prompt.', true);
        } finally {
          setLoading(false, 'Editing with Gemini…');
        }
      });
    }

    applyBtn.addEventListener('click', async function () {
      var prompt = promptEl ? promptEl.value.trim() : '';
      var total = totalSourceCount();

      if (total < 1) {
        setStatus('Choose at least one photo (upload or checked Google Photos).', true);
        return;
      }
      if (total > maxTotal) {
        setStatus('Too many photos selected (max ' + maxTotal + ').', true);
        return;
      }
      if (!prompt) {
        setStatus('Describe how the photos should be improved.', true);
        if (promptEl) promptEl.focus();
        return;
      }

      var jobs = buildJobs(prompt);
      var failures = [];
      var improvedFiles = [];
      var processedGoogle = googleCheckedCount() > 0;

      setLoading(true, 'Editing photo 1 of ' + jobs.length + '…');
      setStatus('Processing ' + jobs.length + ' photo' + (jobs.length === 1 ? '' : 's') + ' one at a time…', false);

      for (var i = 0; i < jobs.length; i++) {
        var job = jobs[i];
        var progress = 'Editing photo ' + (i + 1) + ' of ' + jobs.length + '…';
        setLoading(true, progress);
        setStatus(progress, false);

        try {
          var body = await fetchJsonPost(aiImproveUrl, job.buildFormData(), csrfToken);
          (body.files || []).forEach(function (item) {
            if (!item || !item.content_base64) return;
            improvedFiles.push(base64ToFile(
              item.content_base64,
              item.filename || 'photo-ai.webp',
              'image/webp'
            ));
          });
          (body.failures || []).forEach(function (f) {
            failures.push(f);
          });
        } catch (err) {
          failures.push({
            label: job.label,
            error: err && err.message ? err.message : 'AI improve failed.',
          });
        }
      }

      if (improvedFiles.length) {
        appendFilesToInput(fileInput, improvedFiles);
        if (processedGoogle && googleRoot && googleRoot.googlePhotosPicker && googleRoot.googlePhotosPicker.clearPickedItems) {
          googleRoot.googlePhotosPicker.clearPickedItems();
        }
      }

      var msg;
      if (improvedFiles.length === 0 && failures.length) {
        msg = failures.map(function (f) {
          return (f.label || 'Photo') + ': ' + (f.error || 'failed');
        }).join('; ');
        setStatus(msg, true);
      } else if (failures.length) {
        msg = improvedFiles.length + ' WebP photo' + (improvedFiles.length === 1 ? '' : 's') + ' added. ';
        msg += failures.map(function (f) {
          return (f.label || 'Photo') + ': ' + (f.error || 'failed');
        }).join('; ');
        setStatus(msg, true);
      } else {
        msg = improvedFiles.length === 1
          ? '1 AI-improved WebP photo added above. Review previews, then Save project.'
          : improvedFiles.length + ' AI-improved WebP photos added above. Review previews, then Save project.';
        setStatus(msg, false);
      }

      setLoading(false, 'Editing with Gemini…');
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var boot = window.portfolioAiImproveConfig;
    if (boot) init(boot);
  });
})();
