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

    if (!applyBtn) return;
    if (!fileInput && !document.querySelector('.js-portfolio-ai-existing')) return;

    bindExistingPhotoChecks();

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
      if (!fileInput || !fileInput.files) return 0;
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

    function existingSelections() {
      var nodes = document.querySelectorAll('#admin-photo-sortable .js-portfolio-ai-existing:checked');
      var cards = document.querySelectorAll('#admin-photo-sortable .admin-photo-sortable-item');
      var selected = [];
      Array.prototype.forEach.call(nodes, function (input) {
        var item = input.closest('.admin-photo-sortable-item');
        if (!item) return;
        var remove = item.querySelector('input[name="delete_photos[]"]');
        if (remove && remove.checked) return;
        var id = input.value;
        if (!id) return;
        var index = Array.prototype.indexOf.call(cards, item);
        selected.push({
          id: id,
          label: 'Saved photo ' + (index >= 0 ? index + 1 : id),
        });
      });
      return selected;
    }

    function removeOnlyCount() {
      var count = 0;
      var boxes = document.querySelectorAll('#admin-photo-sortable input[name="delete_photos[]"]:checked');
      Array.prototype.forEach.call(boxes, function (remove) {
        var item = remove.closest('.admin-photo-sortable-item');
        var improve = item && item.querySelector('.js-portfolio-ai-existing');
        if (!improve || !improve.checked) count += 1;
      });
      return count;
    }

    function additionCount() {
      var google = googlePayloads().length;
      if (google > 0) return google;
      return improvableUploadCount();
    }

    function totalSourceCount() {
      return existingSelections().length + additionCount();
    }

    function cardForPhotoId(photoId) {
      var cards = document.querySelectorAll('#admin-photo-sortable .admin-photo-sortable-item');
      var found = null;
      Array.prototype.forEach.call(cards, function (card) {
        if (card.getAttribute('data-photo-id') === String(photoId)) found = card;
      });
      return found;
    }

    function applyReplacement(item) {
      var card = cardForPhotoId(item.photo_id);
      if (!card) return;
      var img = card.querySelector('img');
      if (img && item.url) img.src = item.url;
      var box = card.querySelector('.js-portfolio-ai-existing');
      if (box) box.checked = false;
      card.classList.remove('is-ai-selected');
    }

    function buildJobs() {
      var jobs = [];

      existingSelections().forEach(function (photo) {
        jobs.push({
          label: photo.label,
          sourceFile: null,
          existingPhotoId: photo.id,
          buildFormData: function () {
            var fd = new FormData();
            fd.append('existing_photo_id', photo.id);
            return fd;
          },
        });
      });

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

      if (fileInput && fileInput.files) {
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
        if (removeOnlyCount() > 0) {
          setStatus('Remove deletes a photo when you save. Check Improve on the photo you want enhanced, or choose a new upload or Google Photo.', true);
        } else if (document.querySelector('.js-portfolio-ai-existing')) {
          setStatus('Check Improve on a saved photo, choose a local file, or check a Google Photo. Uploads already named -ai.webp are skipped.', true);
        } else {
          setStatus('Choose at least one photo to improve (uploads or checked Google Photos). Already-improved WebP files are skipped.', true);
        }
        return;
      }
      var draftSlots = 0;
      if (config.stashDrafts && window.portfolioDraftPhotos && window.portfolioDraftPhotos.count) {
        draftSlots = window.portfolioDraftPhotos.count();
      }
      var additions = additionCount();
      if (additions > 0 && additions + draftSlots > maxTotal) {
        setStatus('Too many photos (max ' + maxTotal + ' including saved photos).', true);
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
          var fd = job.buildFormData();
          if (config.stashDrafts) {
            fd.append('stash_drafts', '1');
          }
          var body = await fetchJsonPost(aiImproveUrl, fd, csrfToken);
          (body.files || []).forEach(function (item) {
            if (!item) return;
            if (item.replaced && item.photo_id) {
              applyReplacement(item);
              improvedFiles.push({ replaced: true, photo_id: item.photo_id });
              return;
            }
            if (config.stashDrafts && item.draft_path) {
              if (window.portfolioDraftPhotos && window.portfolioDraftPhotos.append) {
                window.portfolioDraftPhotos.append(item);
              }
              improvedFiles.push({ draft: true, path: item.draft_path });
              return;
            }
            if (!item.content_base64) return;
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

      var replacedCount = improvedFiles.filter(function (f) {
        return f && f.replaced;
      }).length;
      var draftCount = improvedFiles.filter(function (f) {
        return f && f.draft;
      }).length;
      var newUploadFiles = improvedFiles.filter(function (f) {
        return f && !f.replaced && !f.draft;
      });
      var readyCount = config.stashDrafts ? draftCount : newUploadFiles.length;

      if (improvedFiles.length || failures.length) {
        var stashedDrafts = config.stashDrafts && improvedFiles.some(function (f) {
          return f && f.draft;
        });

        if (stashedDrafts) {
          if (window.portfolioDraftPhotos && window.portfolioDraftPhotos.clearFileInput) {
            window.portfolioDraftPhotos.clearFileInput();
          } else {
            setFileInputFiles(fileInput, []);
          }
          if (processedGoogle && googleRoot && googleRoot.googlePhotosPicker && googleRoot.googlePhotosPicker.clearPickedItems) {
            googleRoot.googlePhotosPicker.clearPickedItems();
          }
        } else if (processedGoogle) {
          if (newUploadFiles.length && fileInput) {
            appendFilesToInput(fileInput, newUploadFiles);
          }
          if (googleRoot && googleRoot.googlePhotosPicker && googleRoot.googlePhotosPicker.clearPickedItems) {
            googleRoot.googlePhotosPicker.clearPickedItems();
          }
        } else if (fileInput && (newUploadFiles.length || jobs.some(function (j) { return j.sourceFile; }))) {
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
          setFileInputFiles(fileInput, alreadyImproved.concat(keptOriginals).concat(newUploadFiles));
        }
      }

      var msg;
      if (improvedFiles.length === 0 && failures.length) {
        msg = failures.map(function (f) {
          return (f.label || 'Photo') + ': ' + (f.error || 'failed');
        }).join('; ');
        setStatus(msg, true);
      } else if (failures.length) {
        msg = '';
        if (replacedCount) {
          msg += replacedCount + ' saved photo' + (replacedCount === 1 ? '' : 's') + ' replaced. ';
        }
        if (readyCount) {
          msg += readyCount + ' WebP photo' + (readyCount === 1 ? '' : 's') + ' ready. ';
        }
        msg += 'Failed: ';
        msg += failures.map(function (f) {
          return (f.label || 'Photo') + ': ' + (f.error || 'failed');
        }).join('; ');
        msg += ' Click Apply again to retry failed photos only.';
        setStatus(msg, true);
      } else {
        var parts = [];
        if (replacedCount === 1) {
          parts.push('1 saved photo was replaced with the AI-improved version.');
        } else if (replacedCount > 1) {
          parts.push(replacedCount + ' saved photos were replaced with AI-improved versions.');
        }
        var ready = readyCount;
        if (ready > 0) {
          var saveHint = config.saveHint || 'Save project';
          if (config.stashDrafts) {
            parts.push(ready === 1
              ? '1 AI-improved photo saved. Finish the form, then ' + saveHint + '.'
              : ready + ' AI-improved photos saved. Finish the form, then ' + saveHint + '.');
          } else {
            parts.push(ready === 1
              ? '1 AI-improved WebP photo ready. Review previews, then ' + saveHint + '.'
              : ready + ' AI-improved WebP photos ready. Review previews, then ' + saveHint + '.');
          }
        }
        setStatus(parts.join(' '), false);
      }

      setLoading(false, 'Editing with Gemini…');
    });
  }

  function bindExistingPhotoChecks() {
    var list = document.getElementById('admin-photo-sortable');
    if (!list || list.getAttribute('data-ai-improve-bound') === '1') return;
    list.setAttribute('data-ai-improve-bound', '1');
    list.addEventListener('change', function (event) {
      var target = event.target;
      if (!target || !target.closest) return;
      var item = target.closest('.admin-photo-sortable-item');
      if (!item) return;
      var improve = item.querySelector('.js-portfolio-ai-existing');
      var remove = item.querySelector('input[name="delete_photos[]"]');
      if (improve && target === improve && improve.checked && remove) {
        remove.checked = false;
      }
      if (remove && target === remove && remove.checked && improve) {
        improve.checked = false;
      }
      if (improve) {
        item.classList.toggle('is-ai-selected', !!improve.checked);
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var boot = window.portfolioAiImproveConfig;
    if (boot) init(boot);
  });
})();
