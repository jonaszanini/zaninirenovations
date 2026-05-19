@extends('admin.layouts.app')

@section('heading', 'Project Calendar')

@push('head')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
@endpush

@section('content')
  <div class="admin-toolbar">
    <p class="admin-toolbar-lede">Job timelines and visit days from projects. <strong>Personal</strong> events (slate) are only on this calendar — click to edit. Project events open the project.</p>
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
    <span class="cp-legend-item">
      <span class="cp-legend-swatch" style="background: {{ $personalEventColor }};"></span>
      Personal
    </span>
    @foreach ($statusLabels as $key => $label)
      @if ($key !== \App\Models\ClientProject::STATUS_REJECTED)
        <span class="cp-legend-item">
          <span class="cp-legend-swatch" style="background: {{ $statusColors[$key] }};"></span>
          {{ $label }}
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
          <div class="admin-form-group">
            <label for="cp-personal-event-description">Description</label>
            <textarea id="cp-personal-event-description" name="description" rows="4" maxlength="5000" class="admin-input"></textarea>
          </div>
          <p id="cp-personal-event-error" class="admin-form-error" style="display:none;" role="alert"></p>
        </div>
        <div class="cp-modal-footer cp-personal-event-footer">
          <button type="button" class="admin-btn admin-btn--danger admin-btn--sm" id="cp-personal-event-delete" style="display:none;margin-right:auto;">
            Delete
          </button>
          <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-cp-modal-close>Cancel</button>
          <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm" id="cp-personal-event-save">
            <i class="fa fa-check"></i> Save
          </button>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var el = document.getElementById('cp-calendar');
      if (!el || typeof FullCalendar === 'undefined') return;

      var projectEvents = @json($events);
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
      var descInput = document.getElementById('cp-personal-event-description');
      var errorEl = document.getElementById('cp-personal-event-error');
      var deleteBtn = document.getElementById('cp-personal-event-delete');
      var modalTitle = document.getElementById('cp-personal-event-heading');
      var editingId = null;
      var editingFcEvent = null;
      var calendar = null;

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
      }
      function openCreate(startYmd, endYmd) {
        editingId = null;
        editingFcEvent = null;
        modalTitle.textContent = 'Add personal event';
        deleteBtn.style.display = 'none';
        form.reset();
        startInput.value = startYmd || '';
        endInput.value = endYmd || startYmd || '';
        showError('');
        openModal();
      }
      function openEdit(fcEvent) {
        var props = fcEvent.extendedProps || {};
        editingId = props.eventId;
        editingFcEvent = fcEvent;
        modalTitle.textContent = 'Edit personal event';
        deleteBtn.style.display = editingId ? '' : 'none';
        titleInput.value = fcEvent.title || '';
        startInput.value = fcEvent.startStr || (fcEvent.start ? formatYmd(fcEvent.start) : '');
        endInput.value = fcEvent.end ? inclusiveEndFromFc(fcEvent.end) : startInput.value;
        descInput.value = props.description || '';
        showError('');
        openModal();
      }

      document.getElementById('cp-personal-event-add').addEventListener('click', function () {
        openCreate(formatYmd(new Date()), formatYmd(new Date()));
      });

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

      form.addEventListener('submit', function (e) {
        e.preventDefault();
        showError('');
        var payload = {
          title: titleInput.value.trim(),
          description: descInput.value.trim() || null,
          start_date: startInput.value,
          end_date: endInput.value,
        };
        if (!payload.title || !payload.start_date || !payload.end_date) {
          showError('Title and both dates are required.');
          return;
        }
        if (payload.end_date < payload.start_date) {
          showError('End date must be on or after the start date.');
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
            closeModal();
          })
          .catch(function (err) {
            showError(err.message);
          });
      });

      deleteBtn.addEventListener('click', function () {
        if (!editingId) return;
        if (!confirm('Delete this personal event?')) return;
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

      var DESKTOP_TOOLBAR = {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,listMonth'
      };
      var MOBILE_TOOLBAR = {
        left: 'prev today next',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,listMonth'
      };
      var MOBILE_BREAKPOINT = 700;
      function pickToolbar() {
        return window.innerWidth <= MOBILE_BREAKPOINT ? MOBILE_TOOLBAR : DESKTOP_TOOLBAR;
      }

      calendar = new FullCalendar.Calendar(el, {
        initialView: 'dayGridMonth',
        headerToolbar: pickToolbar(),
        firstDay: 0,
        height: 'auto',
        events: projectEvents,
        eventDisplay: 'block',
        selectable: true,
        selectMirror: true,
        unselectAuto: true,
        select: function (info) {
          var startYmd = formatYmd(info.start);
          var endYmd = info.allDay ? inclusiveEndFromFc(info.end) : formatYmd(info.end);
          openCreate(startYmd, endYmd);
          calendar.unselect();
        },
        eventClick: function (info) {
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
          if (props.kind === 'personal' && props.description) {
            hint += '\n' + props.description;
          } else if (props.status) {
            hint += ' — ' + props.status;
          }
          info.el.setAttribute('title', hint);
          if (props.kind === 'personal') {
            info.el.classList.add('fc-event-personal');
            info.el.style.cursor = 'pointer';
          }
        },
        windowResize: function () {
          var next = pickToolbar();
          var current = calendar.getOption('headerToolbar');
          if (!current || current.left !== next.left) {
            calendar.setOption('headerToolbar', next);
          }
        }
      });
      calendar.render();
    });
  </script>
@endsection
