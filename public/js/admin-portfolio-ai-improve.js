(function () {
  'use strict';

  var JOB_DELAY_MS = 2000;

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

  function isAiImprovedFile(file) {
    if (!file || !file.name) return false;
    return file.name.toLowerCase().indexOf('-ai.webp') !== -1;
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

  function setFileInputFiles(input, files) {
    if (!input) return;
    var dt = new DataTransfer();
    files.forEach(function (file) {
      dt.items.add(file);
    });
    input.files = dt.files;
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function appendFilesToInput(input, newFiles) {
    if (!input || !newFiles.length) return;
    var existing = [];
    if (input.files) {
      Array.prototype.forEach.call(input.files, function (file) {
        existing.push(file);
      });
    }
    setFileInputFiles(input, existing.concat(newFiles));
  }

  function googleJobLabel(payload, index) {
    try {
      var data = JSON.parse(payload);
      if (data && data.filename) return data.filename;
    } catch (ignore) {}
    return 'Google photo ' + (index + 1);
  }

  function delay(ms) {
    return new Promise(function (resolve) {
      setTimeout(resolve, ms);
    });
  }

  function init(config) {
    var root = document.getElementById('portfolio-ai-improve');
    if (!root || root.getAttribute('data-portfolio-ai-init') === '1') return;
    root.setAttribute('data-portfolio-ai-init', '1');

    var fileInput = document.getElementById(config.fileInputId || 'photos');
    var googleRoot = document.querySelector('.admin-google-photos');
    var applyBtn = document.getElementById('portfolio-ai-apply-btn');
    var loadingEl = document.getElementById('portfolio-ai-loading');
    var loadingTextEl = document.getElementById('portfolio-ai-loading-text');
    var statusEl = document.getElementById('portfolio-ai-status');
    var maxTotal = parseInt(config.maxTotal || '15', 10);
    var csrfToken = config.csrfToken || '';
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
    }

    function improvableUploadCount() {
      if (!fileInput.files) return 0;
      var count = 0;
      Array.prototype.forEach.call(fileInput.files, function (file) {
        if (!isAiImprovedFile(file)) count += 1;
      });
      return count;
    }

    function googlePayloads() {
      if (!googleRoot || !googleRoot.googlePhotosPicker) return [];
      return googleRoot.googlePhotosPicker.getCheckedPayloads();
    }

    function totalSourceCount() {
      var google = googlePayloads().length;
      if (google > 0) return google;
      return improvableUploadCount();
    }

    function buildJobs() {
      var jobs = [];
      var google = googlePayloads();

      if (google.length > 0) {
        google.forEach(function (payload, index) {
          jobs.push({
            label: googleJobLabel(payload, index),
            sourceFile: null,
            buildFormData: function () {
              var fd = new FormData();
              fd.append('google_picker_items[]', payload);
              return fd;
            },
          });
        });
        return jobs;
      }

      if (fileInput.files) {
        Array.prototype.forEach.call(fileInput.files, function (file, index) {
          if (isAiImprovedFile(file)) return;
          jobs.push({
            label: file.name || ('Upload ' + (index + 1)),
            sourceFile: file,
            buildFormData: function () {
              var fd = new FormData();
              fd.append('photos[]', file);
              return fd;
            },
          });
        });
      }

      return jobs;
    }

    applyBtn.addEventListener('click', async function () {
      var total = totalSourceCount();

      if (total < 1) {
        setStatus('Choose at least one photo to improve (uploads or checked Google Photos). Already-improved WebP files are skipped.', true);
        return;
      }
      if (total > maxTotal) {
        setStatus('Too many photos selected (max ' + maxTotal + ').', true);
        return;
      }

      var jobs = buildJobs();
      if (!jobs.length) {
        setStatus('No photos left to improve. Files ending in -ai.webp are already processed.', true);
        return;
      }

      var failures = [];
      var improvedFiles = [];
      var processedGoogle = googlePayloads().length > 0;

      setLoading(true, 'Editing photo 1 of ' + jobs.length + '…');
      setStatus('Processing ' + jobs.length + ' photo' + (jobs.length === 1 ? '' : 's') + ' one at a time…', false);

      for (var i = 0; i < jobs.length; i++) {
        var job = jobs[i];
        var progress = 'Editing photo ' + (i + 1) + ' of ' + jobs.length + '…';
        setLoading(true, progress);
        setStatus(progress, false);

        if (i > 0) {
          await delay(JOB_DELAY_MS);
        }

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

      if (improvedFiles.length || failures.length) {
        if (processedGoogle) {
          if (improvedFiles.length) {
            appendFilesToInput(fileInput, improvedFiles);
          }
          if (googleRoot && googleRoot.googlePhotosPicker && googleRoot.googlePhotosPicker.clearPickedItems) {
            googleRoot.googlePhotosPicker.clearPickedItems();
          }
        } else {
          var failedLabels = {};
          failures.forEach(function (f) {
            if (f && f.label) failedLabels[f.label] = true;
          });
          var keptOriginals = [];
          jobs.forEach(function (j) {
            if (j.sourceFile && failedLabels[j.label]) {
              keptOriginals.push(j.sourceFile);
            }
          });
          var alreadyImproved = [];
          if (fileInput.files) {
            Array.prototype.forEach.call(fileInput.files, function (file) {
              if (isAiImprovedFile(file)) alreadyImproved.push(file);
            });
          }
          setFileInputFiles(fileInput, alreadyImproved.concat(keptOriginals).concat(improvedFiles));
        }
      }

      var msg;
      if (improvedFiles.length === 0 && failures.length) {
        msg = failures.map(function (f) {
          return (f.label || 'Photo') + ': ' + (f.error || 'failed');
        }).join('; ');
        setStatus(msg, true);
      } else if (failures.length) {
        msg = improvedFiles.length + ' WebP photo' + (improvedFiles.length === 1 ? '' : 's') + ' ready. ';
        msg += 'Failed: ';
        msg += failures.map(function (f) {
          return (f.label || 'Photo') + ': ' + (f.error || 'failed');
        }).join('; ');
        msg += ' Click Apply again to retry failed photos only.';
        setStatus(msg, true);
      } else {
        msg = improvedFiles.length === 1
          ? '1 AI-improved WebP photo ready. Review previews, then Save project.'
          : improvedFiles.length + ' AI-improved WebP photos ready. Review previews, then Save project.';
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
