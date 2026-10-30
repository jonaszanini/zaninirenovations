@extends('admin.layouts.app')

@section('heading')
  Project Calendar
  <x-admin.info-tip label="Project Calendar">Job timelines and visit days from projects. <strong>Personal</strong> events (red) are only on this calendar — click to edit. Project events open the project.</x-admin.info-tip>
@endsection

@push('head')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@7.1.3/skeleton.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@7.1.3/themes/classic/theme.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@7.1.3/themes/classic/palette.css">
  <link rel="stylesheet" href="{{ asset('css/admin-project-calendar-fc7.css') }}?v={{ @filemtime(public_path('css/admin-project-calendar-fc7.css')) ?: time() }}">
@endpush

@section('content')
  <div class="admin-toolbar">
    <div class="cp-calendar-toolbar-actions">
      <button type="button" class="admin-btn admin-btn--primary admin-btn--sm" id="cp-personal-event-add">
        <i class="fa fa-plus"></i> Add personal event
      </button>
      <a href="{{ route('admin.client-projects.index') }}" class="admin-btn admin-btn--ghost">
        <i class="fa fa-list"></i> Back to list
      </a>
    </div>
  </div>

  <div class="cp-calendar-legend">
    <span class="cp-legend-item cp-calendar-edit-hint">
      @include('admin.partials.tipped_label', [
        'label' => 'Edit',
        'tipText' => 'Drag an event to change its date. Drag the edge of a job or personal event to change its length.',
      ])
    </span>
    <span class="cp-legend-item cp-calendar-swipe-hint">
      @include('admin.partials.tipped_label', [
        'label' => 'Swipe',
        'tipText' => 'On phone or tablet, swipe left or right on the calendar to move to the next or previous month, week, or list.',
      ])
    </span>
    <span class="cp-legend-item">
      <span class="cp-legend-swatch" style="background: {{ $personalEventColor }};"></span>
      Personal
    </span>
    @foreach ($statusLabels as $key => $statusLabel)
      @if (! in_array($key, \App\Models\ClientProject::terminalStatuses(), true))
        <span class="cp-legend-item">
          <span class="cp-legend-swatch" style="background: {{ $statusColors[$key] }};"></span>
          {{ $statusLabel }}
        </span>
      @endif
    @endforeach
  </div>

  <div class="admin-card cp-calendar-card">
    <div id="cp-calendar"></div>
  </div>

  {{-- Personal event modal (create / edit / delete) --}}
  <div class="cp-modal-backdrop" id="cp-personal-event-modal" hidden>
    <div class="cp-modal cp-personal-event-modal" role="dialog" aria-labelledby="cp-personal-event-heading" aria-modal="true">
      <div class="cp-modal-header">
        <h3 id="cp-personal-event-heading">Personal event</h3>
        <button type="button" class="cp-modal-close" data-cp-modal-close aria-label="Close">&times;</button>
      </div>
      <form id="cp-personal-event-form" novalidate>
        @include('admin.partials.once_submit')
        <div class="cp-modal-body">
          <div class="admin-form-group">
            <label for="cp-personal-event-title-input">Title <span class="cp-required">*</span></label>
            <input type="text" id="cp-personal-event-title-input" name="title" required maxlength="255" class="admin-input">
          </div>
          <div class="admin-form-group cp-personal-event-dates">
            <div>
              <label for="cp-personal-event-start">Start date <span class="cp-required">*</span></label>
              <input type="date" id="cp-personal-event-start" name="start_date" required class="admin-input">
            </div>
            <div>
              <label for="cp-personal-event-end">End date <span class="cp-required">*</span></label>
              <input type="date" id="cp-personal-event-end" name="end_date" required class="admin-input">
            </div>
          </div>
          <div class="admin-form-group cp-personal-event-times">
            <div class="cp-personal-event-time-field">
              @include('admin.partials.tipped_label', [
                'for' => 'cp-personal-event-start-time',
                'label' => 'Start time',
                'tipText' => 'Optional. Leave both times empty for an all-day event. End time requires a start time.',
              ])
              <div class="cp-personal-event-time-input-row">
                <input type="time" id="cp-personal-event-start-time" name="start_time" class="admin-input" step="900">
                <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm cp-personal-event-time-clear" data-clear-personal-time="start" aria-label="Clear start time">
                  <i class="fa fa-times" aria-hidden="true"></i>
                </button>
              </div>
            </div>
            <div class="cp-personal-event-time-field">
              @include('admin.partials.tipped_label', [
                'for' => 'cp-personal-event-end-time',
                'label' => 'End time',
                'tipText' => 'Optional. Must be after the start time on the same day.',
              ])
              <div class="cp-personal-event-time-input-row">
                <input type="time" id="cp-personal-event-end-time" name="end_time" class="admin-input" step="900">
                <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm cp-personal-event-time-clear" data-clear-personal-time="end" aria-label="Clear end time">
                  <i class="fa fa-times" aria-hidden="true"></i>
                </button>
              </div>
            </div>
          </div>
          <p id="cp-personal-event-time-notice" class="admin-form-help cp-personal-event-time-notice admin-flash--js-hidden" role="status"></p>
          <div class="admin-form-group">
            <label for="cp-personal-event-description">Description</label>
            <textarea id="cp-personal-event-description" name="description" rows="4" maxlength="5000" class="admin-input"></textarea>
          </div>
          <p id="cp-personal-event-error" class="admin-form-error admin-flash--js-hidden" role="alert"></p>
        </div>
        <div class="cp-modal-footer cp-personal-event-footer">
          <button type="button" class="admin-btn admin-btn--danger admin-btn--sm" id="cp-personal-event-delete" style="display:none;margin-right:auto;">
            Delete
          </button>
          <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-cp-modal-close>Cancel</button>
          <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm" id="cp-personal-event-save" data-hotkey-save>
            <i class="fa fa-check"></i> Save
          </button>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@7.1.3/all/global.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@7.1.3/themes/classic/global.js"></script>
  <script src="{{ asset('js/admin-project-calendar-swipe.js') }}?v={{ @filemtime(public_path('js/admin-project-calendar-swipe.js')) ?: time() }}"></script>
  <script src="{{ asset('js/admin-project-calendar-popover.js') }}?v={{ @filemtime(public_path('js/admin-project-calendar-popover.js')) ?: time() }}"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var el = document.getElementById('cp-calendar');
      if (!el || typeof FullCalendar === 'undefined') return;

      var projectEvents = @json($events);
      var moveUrl = @json($calendarMoveUrl);
      var storeUrl = @json($personalEventStoreUrl);
      var updateUrlTemplate = @json($personalEventUpdateUrlTemplate);
      var destroyUrlTemplate = @json($personalEventDestroyUrlTemplate);
      var csrf = document.querySelector('meta[name="csrf-token"]');
      var csrfToken = csrf ? csrf.getAttribute('content') : '';

      var modal = document.getElementById('cp-personal-event-modal');
      var form = document.getElementById('cp-personal-event-form');
      var titleInput = document.getElementById('cp-personal-event-title-input');
      var startInput = document.getElementById('cp-personal-event-start');
      var endInput = document.getElementById('cp-personal-event-end');
      var startTimeInput = document.getElementById('cp-personal-event-start-time');
      var endTimeInput = document.getElementById('cp-personal-event-end-time');
      var descInput = document.getElementById('cp-personal-event-description');
      var errorEl = document.getElementById('cp-personal-event-error');
      var timeNoticeEl = document.getElementById('cp-personal-event-time-notice');
      var deleteBtn = document.getElementById('cp-personal-event-delete');
      var modalTitle = document.getElementById('cp-personal-event-heading');
      var editingId = null;
      var editingFcEvent = null;
      var calendar = null;
      var calendarSwipeNav = null;
      var calendarPopover = null;
      var calendarCard = document.querySelector('.cp-calendar-card');
      var personalEventAddBtn = document.getElementById('cp-personal-event-add');
      var modalReturnFocusEl = null;

      function calendarTodayYmd() {
        return new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Toronto' }).format(new Date());
      }
      function findDayNumberNode(cellEl) {
        if (!cellEl) return null;
        var best = null;
        var bestDepth = -1;
        function walk(node, depth) {
          if (!node || node.nodeType !== 1) return;
          var el = node;
          var text = (el.textContent || '').trim();
          if (/^\d{1,2}$/.test(text) && depth > bestDepth) {
            best = el;
            bestDepth = depth;
          }
          for (var i = 0; i < el.children.length; i++) {
            walk(el.children[i], depth + 1);
          }
        }
        walk(cellEl, 0);
        return best;
      }
      function normalizeDateKey(value) {
        if (!value) return '';
        return String(value).slice(0, 10);
      }
      function refreshAllTodayPills() {
        if (!el) return;
        var todayYmd = calendarTodayYmd();
        var cells = el.querySelectorAll('[data-date]');
        for (var c = 0; c < cells.length; c++) {
          var cell = cells[c];
          markTodayDayNumber(cell, normalizeDateKey(cell.getAttribute('data-date')) === todayYmd);
        }
        if (cells.length === 0) {
          var gridCells = el.querySelectorAll('[role="gridcell"]');
          for (var g = 0; g < gridCells.length; g++) {
            markTodayDayNumber(gridCells[g], false);
          }
        }
      }
      function scheduleRefreshTodayPills() {
        refreshAllTodayPills();
        requestAnimationFrame(function () {
          refreshAllTodayPills();
          requestAnimationFrame(refreshAllTodayPills);
        });
        setTimeout(refreshAllTodayPills, 0);
        setTimeout(refreshAllTodayPills, 50);
        setTimeout(refreshAllTodayPills, 150);
      }
      function syncToolbarA11y() {
        if (!el) return;
        var buttons = el.querySelectorAll('button');
        for (var i = 0; i < buttons.length; i++) {
          var btn = buttons[i];
          var aria = (btn.getAttribute('aria-label') || '').toLowerCase();
          var text = (btn.textContent || '').trim().toLowerCase();
          if (aria.indexOf('previous') !== -1 || aria.indexOf('next') !== -1) {
            btn.removeAttribute('aria-pressed');
            btn.classList.add('cp-calendar-toolbar-nav');
            btn.classList.remove('cp-calendar-toolbar-view');
            continue;
          }
          if (text === 'today' || aria.indexOf('today') !== -1) {
            btn.removeAttribute('aria-pressed');
            btn.classList.add('cp-calendar-toolbar-today');
            btn.classList.remove('cp-calendar-toolbar-nav');
            btn.classList.remove('cp-calendar-toolbar-view');
            continue;
          }
          btn.classList.remove('cp-calendar-toolbar-nav');
          btn.classList.remove('cp-calendar-toolbar-today');
          if (text === 'month' || text === 'week' || text === 'day' || text === 'list') {
            btn.classList.add('cp-calendar-toolbar-view');
            if (!btn.hasAttribute('aria-pressed')) {
              btn.setAttribute('aria-pressed', 'false');
            }
          }
        }
      }

      function adminCalendarColorScheme() {
        return document.documentElement.classList.contains('admin-dark') ? 'dark' : 'light';
      }
      function syncCalendarColorScheme() {
        if (!calendar) return;
        var scheme = adminCalendarColorScheme();
        calendar.setOption('colorScheme', scheme);
        el.setAttribute('data-color-scheme', scheme);
      }
      function markTodayDayNumber(cellEl, isToday) {
        if (!cellEl) return;
        var pillClass = 'cp-calendar-day-today-pill';
        var nodes = cellEl.querySelectorAll('.' + pillClass);
        for (var p = 0; p < nodes.length; p++) {
          nodes[p].classList.remove(pillClass);
        }
        if (!isToday) return;
        var node = findDayNumberNode(cellEl);
        if (node) {
          node.classList.add(pillClass);
        }
      }

      function styleMoreLink(arg) {
        var link = arg.el;
        if (!link) return;
        link.classList.add('cp-calendar-more-link');
        link.style.display = 'inline-flex';
        link.style.alignItems = 'center';
        link.style.justifyContent = 'center';
        link.style.boxSizing = 'border-box';
        link.style.minHeight = '24px';
        link.style.minWidth = '24px';
        link.style.padding = '4px 8px';
        link.style.lineHeight = '1.2';
        link.addEventListener('click', function () {
          if (calendarPopover && calendarPopover.scheduleClamp) {
            setTimeout(function () {
              calendarPopover.scheduleClamp();
            }, 0);
            setTimeout(function () {
              calendarPopover.scheduleClamp();
            }, 50);
          }
        });
      }

      function pad2(n) { return n < 10 ? '0' + n : '' + n; }
      function formatYmd(d) {
        return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
      }
      function inclusiveEndFromFc(endDate) {
        var d = new Date(endDate.getTime());
        d.setDate(d.getDate() - 1);
        return formatYmd(d);
      }
      function eventUrl(template, id) {
        return template.replace('__ID__', String(id));
      }
      function showError(msg) {
        errorEl.textContent = msg || 'Something went wrong.';
        errorEl.style.display = msg ? 'block' : 'none';
      }
      function showTimeNotice(msg) {
        if (!timeNoticeEl) return;
        timeNoticeEl.textContent = msg || '';
        if (msg) {
          timeNoticeEl.classList.remove('admin-flash--js-hidden');
        } else {
          timeNoticeEl.classList.add('admin-flash--js-hidden');
        }
      }
      function openModal() {
        modal.hidden = false;
        document.body.classList.add('cp-modal-open');
        titleInput.focus();
      }
      function closeModal() {
        modal.hidden = true;
        document.body.classList.remove('cp-modal-open');
        editingId = null;
        editingFcEvent = null;
        showError('');
        showTimeNotice('');
        if (modalReturnFocusEl && typeof modalReturnFocusEl.focus === 'function') {
          modalReturnFocusEl.focus();
        }
        modalReturnFocusEl = null;
      }
      function rememberModalFocus() {
        modalReturnFocusEl = document.activeElement && document.activeElement !== document.body
          ? document.activeElement
          : personalEventAddBtn;
      }
      function clearPersonalTime(which) {
        if (which === 'start') {
          var hadEnd = !!endTimeInput.value;
          startTimeInput.value = '';
          endTimeInput.value = '';
          if (hadEnd) {
            showTimeNotice('End time was cleared because start time was removed.');
          } else {
            showTimeNotice('');
          }
        } else if (which === 'end') {
          endTimeInput.value = '';
          showTimeNotice('');
        }
      }
      function openCreate(startYmd, endYmd) {
        editingId = null;
        editingFcEvent = null;
        modalTitle.textContent = 'Add personal event';
        deleteBtn.style.display = 'none';
        form.reset();
        startInput.value = startYmd || '';
        endInput.value = endYmd || startYmd || '';
        clearPersonalTime('start');
        showError('');
        showTimeNotice('');
        rememberModalFocus();
        openModal();
      }
      function openEdit(fcEvent) {
        var props = fcEvent.extendedProps || {};
        editingId = props.eventId;
        editingFcEvent = fcEvent;
        modalTitle.textContent = 'Edit personal event';
        deleteBtn.style.display = editingId ? '' : 'none';
        titleInput.value = props.baseTitle || fcEvent.title || '';
        startInput.value = fcEvent.startStr || (fcEvent.start ? formatYmd(fcEvent.start) : '');
        if (!fcEvent.allDay && fcEvent.start) {
          startInput.value = formatYmd(fcEvent.start);
          endInput.value = formatYmd(fcEvent.start);
        } else {
          endInput.value = fcEvent.end ? inclusiveEndFromFc(fcEvent.end) : startInput.value;
        }
        startTimeInput.value = props.startTime || '';
        endTimeInput.value = props.endTime || '';
        descInput.value = props.description || '';
        showError('');
        showTimeNotice('');
        rememberModalFocus();
        openModal();
      }

      document.getElementById('cp-personal-event-add').addEventListener('click', function () {
        rememberModalFocus();
        openCreate(formatYmd(new Date()), formatYmd(new Date()));
      });

      document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape' && e.key !== 'Esc') return;

        if (document.querySelector('.admin-info-tip-wrap.is-open')) {
          return;
        }

        var popApi = window.AdminProjectCalendarPopover;
        var moreDialog = popApi && popApi.findMorePopoverDialog
          ? popApi.findMorePopoverDialog()
          : null;
        if (moreDialog) {
          if (popApi.closePopover(moreDialog)) {
            e.preventDefault();
            e.stopPropagation();
          }
          return;
        }

        if (!modal.hidden) {
          closeModal();
          e.preventDefault();
        }
      }, true);

      modal.addEventListener('click', function (e) {
        if (e.target === modal || (e.target.matches && e.target.matches('[data-cp-modal-close]'))) {
          closeModal();
        }
      });

      startInput.addEventListener('change', function () {
        if (endInput.value && endInput.value < startInput.value) {
          endInput.value = startInput.value;
        }
      });

      form.querySelectorAll('[data-clear-personal-time]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          clearPersonalTime(btn.getAttribute('data-clear-personal-time'));
        });
      });

      startTimeInput.addEventListener('change', function () {
        if (!startTimeInput.value) {
          if (endTimeInput.value) {
            endTimeInput.value = '';
            showTimeNotice('End time was cleared because start time was removed.');
          } else {
            showTimeNotice('');
          }
        } else {
          showTimeNotice('');
          if (endTimeInput.value && endTimeInput.value <= startTimeInput.value) {
            endTimeInput.value = '';
            showTimeNotice('End time was cleared because it was not after the new start time.');
          }
        }
      });

      form.addEventListener('submit', function (e) {
        e.preventDefault();
        showError('');
        var tokenInput = form.querySelector('[name="_submit_once"]');
        var payload = {
          title: titleInput.value.trim(),
          description: descInput.value.trim() || null,
          start_date: startInput.value,
          end_date: endInput.value,
          start_time: startTimeInput.value || null,
          end_time: endTimeInput.value || null,
          _submit_once: tokenInput ? tokenInput.value : '',
        };
        if (!payload.title || !payload.start_date || !payload.end_date) {
          showError('Title and both dates are required.');
          return;
        }
        if (payload.end_date < payload.start_date) {
          showError('End date must be on or after the start date.');
          return;
        }
        if (payload.end_time && !payload.start_time) {
          showError('End time requires a start time.');
          return;
        }
        if (payload.start_time && payload.start_date !== payload.end_date) {
          showError('Timed events must use the same start and end date.');
          return;
        }
        if (payload.start_time && payload.end_time && payload.end_time <= payload.start_time) {
          showError('End time must be after the start time.');
          return;
        }

        var url = editingId ? eventUrl(updateUrlTemplate, editingId) : storeUrl;
        var method = editingId ? 'PUT' : 'POST';

        fetch(url, {
          method: method,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify(payload),
        })
          .then(function (res) {
            return res.json().then(function (data) {
              if (!res.ok) {
                var msg = data.message || (data.errors && Object.values(data.errors).flat().join(' ')) || 'Could not save.';
                throw new Error(msg);
              }
              return data;
            });
          })
          .then(function (data) {
            if (editingFcEvent) {
              editingFcEvent.remove();
            }
            calendar.addEvent(data.event);
            if (tokenInput) {
              var bytes = new Uint8Array(16);
              if (window.crypto && window.crypto.getRandomValues) {
                window.crypto.getRandomValues(bytes);
                tokenInput.value = Array.prototype.map.call(bytes, function (b) {
                  return ('0' + b.toString(16)).slice(-2);
                }).join('');
              }
            }
            closeModal();
          })
          .catch(function (err) {
            showError(err.message);
          });
      });

      deleteBtn.addEventListener('click', function () {
        if (!editingId) return;
        window.AdminDialog.confirm('Delete this personal event?', { title: 'Delete event', danger: true }).then(function (ok) {
          if (!ok) return;
          fetch(eventUrl(destroyUrlTemplate, editingId), {
          method: 'DELETE',
          headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
          },
        })
          .then(function (res) {
            if (!res.ok) throw new Error('Could not delete.');
            if (editingFcEvent) editingFcEvent.remove();
            closeModal();
          })
          .catch(function () {
            showError('Could not delete this event.');
          });
        });
      });

      function persistMove(info) {
        var props = info.event.extendedProps || {};
        if (!props.kind || !props.recordId) {
          info.revert();
          return;
        }
        fetch(moveUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({
            kind: props.kind,
            id: props.recordId,
            start: info.event.startStr,
            end: info.event.endStr || null,
            all_day: !!info.event.allDay,
          }),
        })
          .then(function (res) {
            return res.json().then(function (data) {
              if (!res.ok) {
                var msg = (data.errors && Object.values(data.errors).flat().join(' ')) || data.message || 'Could not save this change.';
                throw new Error(msg);
              }
              return data;
            });
          })
          .catch(function (err) {
            info.revert();
            if (window.AdminDialog && window.AdminDialog.alert) {
              window.AdminDialog.alert(err.message || 'Could not save this change.');
            }
          });
      }

      var calendarToolbar = {
        left: 'prev today next',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth',
      };

      // Drag-select fights vertical scrolling on phones; taps use dateClick instead.
      var touchLikeCalendar = window.matchMedia('(pointer: coarse)').matches
        || window.matchMedia('(hover: none)').matches;

      calendar = new FullCalendar.Calendar(el, {
        initialView: 'dayGridMonth',
        headerToolbar: calendarToolbar,
        timeZone: 'America/Toronto',
        locale: 'en',
        firstDay: 0,
        height: 'auto',
        colorScheme: adminCalendarColorScheme(),
        dayMinHeight: 68,
        dayMaxEvents: true,
        moreLinkClick: 'popover',
        moreLinkHeight: 28,
        moreLinkClass: 'cp-calendar-more-link',
        moreLinkDidMount: styleMoreLink,
        datesSet: function () {
          scheduleRefreshTodayPills();
          syncToolbarA11y();
        },
        viewDidMount: function () {
          scheduleRefreshTodayPills();
          syncToolbarA11y();
        },
        editable: true,
        eventStartEditable: true,
        eventDurationEditable: true,
        defaultTimedEventDuration: '00:01:00',
        events: projectEvents,
        eventDisplay: 'block',
        eventAllow: function (dropInfo, draggedEvent) {
          var kind = draggedEvent.extendedProps && draggedEvent.extendedProps.kind;
          if (kind === 'job' && !dropInfo.allDay) return false;
          if (kind === 'personal' && draggedEvent.extendedProps.startTime) {
            if (dropInfo.allDay) return false;
            if (dropInfo.start && dropInfo.end && formatYmd(dropInfo.start) !== formatYmd(dropInfo.end)) return false;
          }
          return true;
        },
        eventDrop: persistMove,
        eventResize: persistMove,
        selectable: !touchLikeCalendar,
        selectMirror: !touchLikeCalendar,
        unselectAuto: true,
        selectLongPressDelay: touchLikeCalendar ? 500 : 0,
        dateClick: function (info) {
          if (!touchLikeCalendar) return;
          if (calendarSwipeNav && calendarSwipeNav.shouldSuppressEventClick()) return;
          var ymd = formatYmd(info.date);
          openCreate(ymd, ymd);
        },
        select: function (info) {
          if (touchLikeCalendar) return;
          var startYmd = formatYmd(info.start);
          var endYmd = info.allDay ? inclusiveEndFromFc(info.end) : formatYmd(info.end);
          openCreate(startYmd, endYmd);
          calendar.unselect();
        },
        eventClick: function (info) {
          if (calendarSwipeNav && calendarSwipeNav.shouldSuppressEventClick()) {
            info.jsEvent.preventDefault();
            return;
          }
          var kind = info.event.extendedProps && info.event.extendedProps.kind;
          if (kind === 'personal') {
            info.jsEvent.preventDefault();
            openEdit(info.event);
            return;
          }
          if (info.event.url) {
            info.jsEvent.preventDefault();
            window.location.href = info.event.url;
          }
        },
        eventDidMount: function (info) {
          var props = info.event.extendedProps || {};
          var hint = info.event.title;
          if (props.status) {
            hint += ' — ' + props.status;
          }
          if (props.kind === 'personal' && props.timeLabel) {
            hint += '\n' + props.timeLabel;
          }
          if (props.kind === 'personal' && props.description) {
            hint += '\n' + props.description;
          }
          if (props.kind === 'job' || props.kind === 'visit' || props.kind === 'personal') {
            hint += '\nDrag to reschedule.';
          }
          info.el.setAttribute('title', hint);
          if (props.kind === 'personal') {
            info.el.classList.add('fc-event-personal');
            info.el.style.cursor = 'pointer';
          }
        },
        dayCellDidMount: function (info) {
          markTodayDayNumber(info.el, info.isToday);
        },
      });
      calendar.render();
      el.setAttribute('data-color-scheme', adminCalendarColorScheme());
      scheduleRefreshTodayPills();
      syncToolbarA11y();
      document.addEventListener('admin-theme-change', function () {
        syncCalendarColorScheme();
        scheduleRefreshTodayPills();
        syncToolbarA11y();
      });
      window.addEventListener('resize', function () {
        scheduleRefreshTodayPills();
      });
      if (window.AdminProjectCalendarPopover && calendarCard) {
        calendarPopover = window.AdminProjectCalendarPopover.attach(calendarCard, el);
      }
      if (window.AdminProjectCalendarSwipe) {
        calendarSwipeNav = window.AdminProjectCalendarSwipe.attach(el, calendar, {
          touchLike: touchLikeCalendar,
        });
      }
    });
  </script>
@endsection
