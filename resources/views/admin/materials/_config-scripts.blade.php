<script>
  (function () {
    function getCsrfToken() {
      var el = document.querySelector('#mat-bulk-form input[name="_token"]');
      return el ? el.value : '';
    }

    function postJson(url, body, csrf) {
      return fetch(url, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(body),
        credentials: 'same-origin'
      }).then(function (res) {
        return res.json().then(function (data) {
          if (!res.ok) {
            var err = new Error(data.message || 'Request failed');
            err.data = data;
            err.status = res.status;
            throw err;
          }
          return data;
        });
      });
    }

    function postForm(url, formData, csrf) {
      return fetch(url, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData,
        credentials: 'same-origin'
      }).then(function (res) {
        return res.json().then(function (data) {
          if (!res.ok) {
            var err = new Error(data.message || 'Request failed');
            err.data = data;
            err.status = res.status;
            throw err;
          }
          return data;
        });
      });
    }

    var bulkForm = document.getElementById('mat-bulk-form');
    if (bulkForm) {
      var csrf = getCsrfToken();
      var progressEl = document.getElementById('mat-bulk-progress');
      var submitBtn = document.getElementById('mat-bulk-submit');
      var phaseLabel = document.getElementById('mat-bulk-phase-label');
      var counterEl = document.getElementById('mat-bulk-counter');
      var barEl = document.getElementById('mat-bulk-bar');
      var progressbarEl = document.getElementById('mat-bulk-progressbar');
      var messageEl = document.getElementById('mat-bulk-message');
      var logEl = document.getElementById('mat-bulk-log');
      var doneEl = document.getElementById('mat-bulk-done');
      var summaryEl = document.getElementById('mat-bulk-summary');
      var reloadBtn = document.getElementById('mat-bulk-reload');
      var statCreated = document.getElementById('mat-stat-created');
      var statUpdated = document.getElementById('mat-stat-updated');
      var statSyncOk = document.getElementById('mat-stat-sync-ok');
      var statSyncFailed = document.getElementById('mat-stat-sync-failed');

      function updateProgress(data) {
        var overallTotal = data.overall_total || 1;
        var overallCurrent = data.overall_current || 0;
        var pct = overallTotal> 0 ? Math.round((overallCurrent / overallTotal) * 100) : 0;

        if (phaseLabel) {
          phaseLabel.textContent = data.phase_label || 'Working…';
        }
        if (counterEl) {
          if (data.phase === 'import') {
            counterEl.textContent = 'Import ' + (data.current || 0) + ' / ' + (data.phase_total || 0);
          } else if (data.phase === 'sync') {
            counterEl.textContent = 'Sync ' + (data.current || 0) + ' / ' + (data.phase_total || 0);
          } else if (data.phase === 'done') {
            counterEl.textContent = 'Complete';
          } else {
            counterEl.textContent = (data.overall_current || 0) + ' / ' + (data.overall_total || 0);
          }
        }
        if (barEl) {
          barEl.style.width = pct + '%';
        }
        if (progressbarEl) {
          progressbarEl.setAttribute('aria-valuenow', String(pct));
        }
        if (messageEl) {
          messageEl.textContent = data.message || '';
        }
        if (data.stats) {
          if (statCreated) statCreated.textContent = String(data.stats.created || 0);
          if (statUpdated) statUpdated.textContent = String(data.stats.updated || 0);
          if (statSyncOk) statSyncOk.textContent = String(data.stats.sync_ok || 0);
          if (statSyncFailed) statSyncFailed.textContent = String(data.stats.sync_failed || 0);
        }
        if (logEl && Array.isArray(data.log)) {
          logEl.innerHTML = '';
          data.log.slice().reverse().forEach(function (line) {
            var li = document.createElement('li');
            li.textContent = line;
            logEl.appendChild(li);
          });
        }
      }

      function setRunning(running) {
        if (submitBtn) {
          submitBtn.disabled = running;
          submitBtn.textContent = running ? 'Importing…' : 'Import SKUs';
        }
      }

      function showError(msg) {
        if (messageEl) {
          messageEl.textContent = msg;
          messageEl.classList.add('mat-bulk-progress__message--error');
        }
        setRunning(false);
      }

      bulkForm.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!progressEl) return;

        var startUrl = bulkForm.getAttribute('data-start-url');
        var stepUrl = bulkForm.getAttribute('data-step-url');
        if (!startUrl || !stepUrl) return;

        progressEl.hidden = false;
        if (doneEl) doneEl.hidden = true;
        if (messageEl) messageEl.classList.remove('mat-bulk-progress__message--error');
        if (logEl) logEl.innerHTML = '';
        setRunning(true);

        var formData = new FormData(bulkForm);
        if (!formData.has('sync_prices')) {
          formData.append('sync_prices', '0');
        }

        postForm(startUrl, formData, csrf)
          .then(function (startData) {
            updateProgress(startData);
            if (startData.skip_note && messageEl) {
              messageEl.textContent = startData.message + ' ' + startData.skip_note;
            }

            function runStep(token) {
              return postJson(stepUrl, { token: token }, csrf).then(function (stepData) {
                updateProgress(stepData);
                if (stepData.done) {
                  setRunning(false);
                  if (doneEl) doneEl.hidden = false;
                  if (summaryEl) summaryEl.textContent = stepData.summary || stepData.message || 'Done.';
                  return stepData;
                }
                return runStep(token);
              });
            }

            return runStep(startData.token);
          })
          .catch(function (err) {
            showError(err.message || 'Import failed.');
          });
      });

      if (reloadBtn) {
        reloadBtn.addEventListener('click', function () {
          window.location.reload();
        });
      }
    }

    document.querySelectorAll('.mat-sync-commands__copy').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var targetId = btn.getAttribute('data-copy-target');
        var codeEl = targetId ? document.getElementById(targetId) : null;
        var text = codeEl ? codeEl.textContent.trim() : '';
        if (!text) return;

        function showCopied() {
          btn.classList.add('is-copied');
          var icon = btn.querySelector('i');
          var prevClass = icon ? icon.className : '';
          if (icon) icon.className = 'fa fa-check';
          window.setTimeout(function () {
            btn.classList.remove('is-copied');
            if (icon) icon.className = prevClass;
          }, 1600);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(showCopied).catch(function () {
            window.prompt('Copy command:', text);
          });
        } else {
          window.prompt('Copy command:', text);
        }
      });
    });
  })();
</script>
