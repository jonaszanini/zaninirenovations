/**
 * Shared start/end date picker: FullCalendar modal of booked project days.
 *
 * Open with [data-schedule-date-picker]:
 *   data-start-input, data-end-input  required selectors
 *   data-events-url                   JSON feed (?exclude= optional)
 *   data-exclude-id                   current project id to omit from busy days
 *   data-submit-form                  optional form to submit after apply
 *   data-schedule-toggle              optional checkbox to turn on before filling
 */
(function (window, document) {
  var MODAL_ID = 'cp-date-picker-modal';
  var calendar = null;
  var busyEvents = [];
  var selection = { start: null, end: null };
  var active = null;
  var eventsCache = {};

  function $(id) {
    return document.getElementById(id);
  }

  function fmtIso(date) {
    var y = date.getFullYear();
    var m = String(date.getMonth() + 1).padStart(2, '0');
    var d = String(date.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + d;
  }

  function parseIso(iso) {
    var parts = String(iso || '').split('-');
    return new Date(+parts[0], +parts[1] - 1, +parts[2]);
  }

  function fmtDisplay(iso) {
    if (!iso) return '—';
    return parseIso(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
  }

  function addDaysIso(iso, days) {
    var d = parseIso(iso);
    d.setDate(d.getDate() + days);
    return fmtIso(d);
  }

  function rangeOverlapsBusy(startIso, endIso) {
    if (!startIso || !endIso) return null;
    var startMs = parseIso(startIso).getTime();
    var endMs = parseIso(endIso).getTime();
    for (var i = 0; i < busyEvents.length; i++) {
      var ev = busyEvents[i];
      var evStart = parseIso(ev.start).getTime();
      var evEnd = parseIso(addDaysIso(ev.end, -1)).getTime();
      if (startMs <= evEnd && endMs >= evStart) return ev;
    }
    return null;
  }

  function refreshSelectionDisplay() {
    var startLabel = $('cp-date-picker-start');
    var endLabel = $('cp-date-picker-end');
    var confirmBtn = $('cp-date-picker-confirm');
    var conflictBox = $('cp-date-picker-conflict');
    if (startLabel) startLabel.textContent = fmtDisplay(selection.start);
    if (endLabel) endLabel.textContent = fmtDisplay(selection.end);
    if (confirmBtn) confirmBtn.disabled = !(selection.start && selection.end);

    if (calendar) {
      var sources = calendar.getEventSources();
      for (var i = 0; i < sources.length; i++) {
        if (sources[i].id === 'cp-selection') sources[i].remove();
      }
      if (selection.start && selection.end) {
        calendar.addEventSource({
          id: 'cp-selection',
          events: [{
            id: 'cp-selection',
            start: selection.start,
            end: addDaysIso(selection.end, 1),
            allDay: true,
            display: 'background',
            color: '#2563eb',
          }]
        });
      }
    }

    if (!conflictBox) return;
    var conflict = rangeOverlapsBusy(selection.start, selection.end);
    if (conflict) {
      conflictBox.classList.remove('admin-flash--js-hidden');
      conflictBox.innerHTML = 'This range overlaps <strong>' +
        (conflict.title || 'another project') + '</strong>';
    } else {
      conflictBox.classList.add('admin-flash--js-hidden');
      conflictBox.textContent = '';
    }
  }

  function setSelection(startIso, endIso) {
    if (startIso && endIso && startIso > endIso) {
      var tmp = startIso;
      startIso = endIso;
      endIso = tmp;
    }
    selection.start = startIso || null;
    selection.end = endIso || null;
    refreshSelectionDisplay();
  }

  function handleDayClick(iso) {
    if (!selection.start || (selection.start && selection.end)) {
      setSelection(iso, null);
      return;
    }
    setSelection(selection.start, iso);
  }

  function destroyCalendar() {
    if (calendar) {
      calendar.destroy();
      calendar = null;
    }
  }

  function initCalendar(calendarEl, initialDate) {
    if (!calendarEl || typeof FullCalendar === 'undefined') return;
    destroyCalendar();
    calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridMonth',
      initialDate: initialDate || new Date(),
      firstDay: 0,
      height: 'auto',
      headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
      selectable: true,
      unselectAuto: false,
      longPressDelay: 150,
      eventDisplay: 'block',
      events: busyEvents,
      eventDidMount: function (info) {
        if (info.event.extendedProps && info.event.extendedProps.status) {
          info.el.setAttribute('title', info.event.title + ' — ' + info.event.extendedProps.status);
        }
      },
      select: function (info) {
        var startIso = fmtIso(info.start);
        var endIso = fmtIso(new Date(info.end.getTime() - 24 * 60 * 60 * 1000));
        if (startIso === endIso) {
          handleDayClick(startIso);
        } else {
          setSelection(startIso, endIso);
        }
        calendar.unselect();
      },
    });
    calendar.render();
  }

  function eventsUrlFor(cfg) {
    var url = cfg.eventsUrl || '';
    if (!url) return '';
    if (cfg.excludeId) {
      url += (url.indexOf('?') >= 0 ? '&' : '?') + 'exclude=' + encodeURIComponent(cfg.excludeId);
    }
    return url;
  }

  function loadEvents(cfg, callback) {
    var url = eventsUrlFor(cfg);
    if (!url) {
      busyEvents = [];
      callback();
      return;
    }
    if (eventsCache[url]) {
      busyEvents = eventsCache[url];
      callback();
      return;
    }
    fetch(url, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (r) { return r.ok ? r.json() : []; })
      .then(function (data) {
        busyEvents = Array.isArray(data) ? data : [];
        eventsCache[url] = busyEvents;
        callback();
      })
      .catch(function () {
        busyEvents = [];
        callback();
      });
  }

  function openModal(cfg) {
    var modal = $(MODAL_ID);
    var calendarEl = $('cp-date-picker-calendar');
    var confirmBtn = $('cp-date-picker-confirm');
    if (!modal) return;
    active = cfg;

    var startInput = cfg.startInput;
    var endInput = cfg.endInput;
    if (startInput && startInput.value && endInput && endInput.value) {
      selection.start = startInput.value;
      selection.end = endInput.value;
    } else if (startInput && startInput.value) {
      selection.start = startInput.value;
      selection.end = null;
    } else {
      selection.start = null;
      selection.end = null;
    }

    if (confirmBtn) {
      confirmBtn.setAttribute(
        'title',
        cfg.submitForm ? 'Save these start and end dates now' : 'Fill the start and end date fields'
      );
    }

    modal.hidden = false;
    document.body.classList.add('cp-modal-open');

    loadEvents(cfg, function () {
      var initialDate = (startInput && startInput.value) ? startInput.value : new Date();
      initCalendar(calendarEl, initialDate);
      if (calendar) {
        if (selection.start) calendar.gotoDate(selection.start);
        calendar.updateSize();
      }
      refreshSelectionDisplay();
    });
  }

  function closeModal() {
    var modal = $(MODAL_ID);
    if (!modal) return;
    modal.hidden = true;
    document.body.classList.remove('cp-modal-open');
  }

  function applySelection() {
    if (!selection.start || !selection.end || !active) return;
    var startInput = active.startInput;
    var endInput = active.endInput;
    var toggle = active.toggle;

    if (toggle && !toggle.checked) {
      toggle.checked = true;
      toggle.dispatchEvent(new Event('change', { bubbles: true }));
    }

    if (startInput) {
      startInput.disabled = false;
      startInput.value = selection.start;
      startInput.dispatchEvent(new Event('change', { bubbles: true }));
      startInput.dispatchEvent(new Event('input', { bubbles: true }));
    }
    if (endInput) {
      endInput.disabled = false;
      endInput.value = selection.end;
      endInput.dispatchEvent(new Event('change', { bubbles: true }));
      endInput.dispatchEvent(new Event('input', { bubbles: true }));
    }

    document.dispatchEvent(new CustomEvent('schedule-date-picker:apply', {
      bubbles: true,
      detail: { start: selection.start, end: selection.end }
    }));

    var form = active.submitForm;
    if (!form) {
      closeModal();
      return;
    }

    var confirmBtn = $('cp-date-picker-confirm');
    if (confirmBtn) {
      confirmBtn.disabled = true;
      confirmBtn.setAttribute('aria-busy', 'true');
    }

    var onSubmit = function (e) {
      form.removeEventListener('submit', onSubmit);
      if (e.defaultPrevented && confirmBtn) {
        confirmBtn.disabled = false;
        confirmBtn.removeAttribute('aria-busy');
      }
    };
    form.addEventListener('submit', onSubmit);

    if (typeof form.requestSubmit === 'function') {
      form.requestSubmit();
    } else {
      form.submit();
    }
  }

  function qs(sel) {
    if (!sel) return null;
    try {
      return document.querySelector(sel);
    } catch (e) {
      return null;
    }
  }

  function configFromButton(btn) {
    return {
      startInput: qs(btn.getAttribute('data-start-input')),
      endInput: qs(btn.getAttribute('data-end-input')),
      toggle: qs(btn.getAttribute('data-schedule-toggle')),
      submitForm: qs(btn.getAttribute('data-submit-form')),
      eventsUrl: btn.getAttribute('data-events-url') || '',
      excludeId: btn.getAttribute('data-exclude-id') || '',
    };
  }

  function bindModalChrome() {
    var modal = $(MODAL_ID);
    if (!modal || modal.getAttribute('data-schedule-date-picker-bound') === '1') return;
    modal.setAttribute('data-schedule-date-picker-bound', '1');
    if (modal.parentNode !== document.body) {
      document.body.appendChild(modal);
    }

    modal.addEventListener('click', function (e) {
      if (e.target === modal || (e.target.matches && e.target.matches('[data-cp-modal-close]'))) {
        closeModal();
      }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !modal.hidden) closeModal();
    });

    var clearBtn = $('cp-date-picker-clear');
    if (clearBtn) {
      clearBtn.addEventListener('click', function () { setSelection(null, null); });
    }
    var confirmBtn = $('cp-date-picker-confirm');
    if (confirmBtn) {
      confirmBtn.addEventListener('click', applySelection);
    }
  }

  function initButton(btn) {
    if (!btn || btn.getAttribute('data-schedule-date-picker-init') === '1') return;
    btn.setAttribute('data-schedule-date-picker-init', '1');
    bindModalChrome();
    btn.addEventListener('click', function () {
      openModal(configFromButton(btn));
    });
  }

  function initAll(scope) {
    (scope || document).querySelectorAll('[data-schedule-date-picker]').forEach(initButton);
  }

  window.AdminScheduleDatePicker = {
    init: initButton,
    initAll: initAll,
    open: openModal,
    close: closeModal,
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { initAll(document); });
  } else {
    initAll(document);
  }
})(window, document);
