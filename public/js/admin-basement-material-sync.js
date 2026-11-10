/**
 * Stepped basement material price sync (shared by basement config + shared prices toolbar).
 */
(function () {
  'use strict';

  function getCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function sleep(ms) {
    return new Promise(function (resolve) {
      setTimeout(resolve, ms);
    });
  }

  function postJson(url, body, csrf, attempt, maxRetries) {
    attempt = attempt || 0;
    maxRetries = maxRetries || 5;
    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrf,
      },
      body: JSON.stringify(body || {}),
    })
      .then(function (res) {
        return res.text().then(function (text) {
          var data = null;
          var trimmed = (text || '').trim();
          if (trimmed.charAt(0) === '{' || trimmed.charAt(0) === '[') {
            try {
              data = JSON.parse(trimmed);
            } catch (e) {
              data = null;
            }
          }
          if (!data) {
            var err = new Error(
              res.status === 503 || res.status === 502 || res.status === 429
                ? 'Server busy (HTTP ' + res.status + '). Retrying…'
                : 'Server returned a non-JSON response (HTTP ' + res.status + ').'
            );
            err.status = res.status;
            err.retryable = res.status === 503 || res.status === 502 || res.status === 429 || res.status === 408;
            throw err;
          }
          if (!res.ok) {
            var fail = new Error(data.message || 'Request failed (' + res.status + ')');
            fail.status = res.status;
            fail.retryable = res.status === 503 || res.status === 502 || res.status === 429;
            throw fail;
          }
          return data;
        });
      })
      .catch(function (err) {
        if (!err.status && err.name === 'TypeError') {
          err.retryable = true;
        }
        if (err.retryable && attempt < maxRetries) {
          return sleep(Math.min(30000, 1500 * Math.pow(2, attempt))).then(function () {
            return postJson(url, body, csrf, attempt + 1, maxRetries);
          });
        }
        throw err;
      });
  }

  function el(prefix, suffix) {
    return document.getElementById(prefix + suffix);
  }

  document.querySelectorAll('[data-bsm-material-sync]').forEach(function (syncBtn) {
    var prefix = syncBtn.getAttribute('data-progress-prefix') || 'bsm-sync';
    var stepDelayMs = parseInt(syncBtn.getAttribute('data-step-delay-ms') || '800', 10);
    var idleLabel = syncBtn.getAttribute('data-label-idle') || 'Update basement material prices';
    var runningLabel = syncBtn.getAttribute('data-label-running') || 'Syncing…';

    var progressEl = el(prefix, '-progress');
    var phaseLabel = el(prefix, '-phase-label');
    var counterEl = el(prefix, '-counter');
    var barEl = el(prefix, '-bar');
    var progressbarEl = el(prefix, '-progressbar');
    var messageEl = el(prefix, '-message');
    var logEl = el(prefix, '-log');
    var doneEl = el(prefix, '-done');
    var summaryEl = el(prefix, '-summary');
    var reloadBtn = el(prefix, '-reload');
    var updatedEl = el(prefix, '-updated');
    var unchangedEl = el(prefix, '-unchanged');
    var failedEl = el(prefix, '-failed');
    var skippedEl = el(prefix, '-skipped');
    var remainingEl = el(prefix, '-remaining');

    function updateProgress(data) {
      var total = data.phase_total || 1;
      var current = data.current || 0;
      var pct = total > 0 ? Math.round((current / total) * 100) : 0;

      if (phaseLabel) phaseLabel.textContent = data.phase_label || 'Syncing…';
      if (counterEl) {
        counterEl.textContent =
          data.phase === 'done'
            ? current + ' / ' + total + ' complete'
            : current + ' / ' + total + ' · ' + (data.remaining ?? total - current) + ' remaining';
      }
      if (barEl) barEl.style.width = pct + '%';
      if (progressbarEl) progressbarEl.setAttribute('aria-valuenow', String(pct));
      if (messageEl) {
        messageEl.textContent = data.message || '';
        messageEl.classList.remove('mat-bulk-progress__message--error');
      }
      if (data.stats) {
        if (updatedEl) updatedEl.textContent = String(data.stats.updated ?? data.stats.ok ?? 0);
        if (unchangedEl) unchangedEl.textContent = String(data.stats.unchanged ?? 0);
        if (failedEl) failedEl.textContent = String(data.stats.failed || 0);
        if (skippedEl) skippedEl.textContent = String(data.stats.skipped || 0);
        if (remainingEl) remainingEl.textContent = String(data.remaining ?? Math.max(0, total - current));
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
      syncBtn.disabled = running || syncBtn.getAttribute('data-empty') === '1';
      syncBtn.innerHTML = running ? '<i class="fa fa-refresh"></i> ' + runningLabel : '<i class="fa fa-refresh"></i> ' + idleLabel;
    }

    if (syncBtn.disabled) {
      syncBtn.setAttribute('data-empty', '1');
    }

    syncBtn.addEventListener('click', function () {
      var startUrl = syncBtn.getAttribute('data-start-url');
      var stepUrl = syncBtn.getAttribute('data-step-url');
      if (!startUrl || !stepUrl || !progressEl) return;

      var csrf = getCsrfToken();
      progressEl.hidden = false;
      if (doneEl) doneEl.hidden = true;
      if (logEl) logEl.innerHTML = '';
      setRunning(true);

      postJson(startUrl, {}, csrf)
        .then(function (startData) {
          updateProgress(startData);

          function runStep(token) {
            return postJson(stepUrl, { token: token }, csrf).then(function (stepData) {
              updateProgress(stepData);
              if (stepData.done) {
                setRunning(false);
                if (doneEl) doneEl.hidden = false;
                if (summaryEl) summaryEl.textContent = stepData.summary || stepData.message || 'Done.';
                return stepData;
              }
              return sleep(stepDelayMs).then(function () {
                return runStep(token);
              });
            });
          }

          return runStep(startData.token);
        })
        .catch(function (err) {
          setRunning(false);
          if (messageEl) {
            messageEl.textContent = err.message || 'Sync failed.';
            messageEl.classList.add('mat-bulk-progress__message--error');
          }
        });
    });

    if (reloadBtn) {
      reloadBtn.addEventListener('click', function () {
        window.location.reload();
      });
    }
  });
})();
