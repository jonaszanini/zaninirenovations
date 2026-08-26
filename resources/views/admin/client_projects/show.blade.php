@extends('admin.layouts.app')

@section('heading', $project->name)

@push('head')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
  <link rel="stylesheet" href="{{ asset('css/admin-vehicles.css') }}?v={{ @filemtime(public_path('css/admin-vehicles.css')) ?: time() }}">
@endpush

@section('content')
  @php
    $statusLabels = \App\Models\ClientProject::statusLabels();
    $statusColors = \App\Models\ClientProject::statusColors();
    $isCompleted = $project->isCompleted();
    $isClosed = $project->isClosed();
    $isAccepted = $project->isAccepted();
  @endphp

  {{-- HEADER / STATUS BAR -------------------------------------------------- --}}
  <div class="admin-card cp-header-card">
    <div class="cp-header-layout">
      <div class="cp-header-row cp-header-row--title">
        <div class="cp-header-title-block">
          <h2 class="cp-header-name">
            <span class="cp-header-ref" title="Project reference">{{ $project->referenceCode() }}</span>
            <span class="cp-header-ref-sep" aria-hidden="true"> — </span>
            <span class="cp-header-project-name">{{ $project->name }}</span>
          </h2>

          <div class="cp-header-badges">
            <span class="cp-billing-type" title="How this project is billed">
              @if ($project->isHourly())
                <i class="fa fa-clock-o" aria-hidden="true"></i> Hourly rate
              @else
                <i class="fa fa-tag" aria-hidden="true"></i> Fixed price
              @endif
            </span>
            @if (filled($project->notes))
              @php
                $notesPreview = \Illuminate\Support\Str::limit(trim((string) $project->notes), 800);
              @endphp
              <span class="cp-notes-popover-wrap">
                <button type="button"
                        class="cp-notes-indicator cp-notes-indicator--btn"
                        id="cp-notes-toggle"
                        aria-expanded="false"
                        aria-controls="cp-notes-popover">
                  <i class="fa fa-sticky-note-o" aria-hidden="true"></i> Notes
                </button>
                <button type="button"
                        class="cp-notes-edit-icon-btn"
                        id="cp-notes-edit-btn"
                        onclick="cpOpenNotesDrawer()"
                        title="Edit notes">
                  <i class="fa fa-pencil" aria-hidden="true"></i>
                </button>
                <div class="cp-notes-popover" id="cp-notes-popover" role="dialog" aria-label="Project notes preview" hidden>
                  <div class="cp-notes-popover-body">{{ $notesPreview }}</div>
                </div>
              </span>
            @else
              <button type="button"
                      class="cp-notes-insert-btn"
                      onclick="cpOpenNotesDrawer()"
                      title="Add internal notes for this project">
                <i class="fa fa-sticky-note-o" aria-hidden="true"></i> Add notes
              </button>
            @endif
          </div>


          <div class="cp-header-client-row">
            <span class="cp-header-client">{{ $project->client_name }}</span>
            @if ($project->usesClientSnapshot())
              <span class="cp-header-snapshot admin-form-help">
                <i class="fa fa-lock" aria-hidden="true"></i>
                Snapshot as of completion
                @if ($project->clientSnapshotCapturedLabel())
                  ({{ $project->clientSnapshotCapturedLabel() }})
                @endif
              </span>
            @endif
            @include('admin.client_projects._contacted_via_display', [
              'project' => $project,
              'lineClass' => 'cp-contacted-via-line',
              'compact' => true,
            ])
          </div>
        </div>

        <div class="cp-header-status">
          <span class="cp-badge cp-badge--lg" style="background: {{ $project->statusColor() }}20; color: {{ $project->statusColor() }};">
            {{ $project->statusLabel() }}
          </span>
          @if (filled($project->status_note))
            <div class="cp-header-status-note" title="{{ $project->status_note }}">{{ \Illuminate\Support\Str::limit(trim((string) $project->status_note), 80) }}</div>
          @endif
          <div class="cp-updated">Updated {{ \App\Models\ClientProject::formatDateTime($project->updated_at) }}</div>
        </div>
      </div>

      @if ($project->client_phone || $project->client_email || $project->secondaryClientEmail() || $project->client_address)
        <div class="cp-header-details">
          @if ($project->client_phone)
            <div class="cp-header-detail">
              <span class="cp-header-detail__icon" aria-hidden="true"><i class="fa fa-phone"></i></span>
              <span class="cp-header-detail__body">
                <span class="cp-header-detail__label">Phone</span>
                <span class="cp-header-detail__value">{{ \App\Models\ClientProject::formatPhone($project->client_phone) }}</span>
              </span>
            </div>
          @endif
          @if ($project->client_email)
            <div class="cp-header-detail">
              <span class="cp-header-detail__icon" aria-hidden="true"><i class="fa fa-envelope"></i></span>
              <span class="cp-header-detail__body">
                <span class="cp-header-detail__label">Email</span>
                <a class="cp-header-detail__value" href="mailto:{{ $project->client_email }}">{{ $project->client_email }}</a>
              </span>
            </div>
          @endif
          @if ($project->secondaryClientEmail())
            <div class="cp-header-detail">
              <span class="cp-header-detail__icon" aria-hidden="true"><i class="fa fa-envelope-o"></i></span>
              <span class="cp-header-detail__body">
                <span class="cp-header-detail__label">CC email</span>
                <a class="cp-header-detail__value" href="mailto:{{ $project->secondaryClientEmail() }}" title="Secondary email — CC'd on every client email">{{ $project->secondaryClientEmail() }}</a>
              </span>
            </div>
          @endif
          @if ($project->client_address)
            <div class="cp-header-detail cp-header-detail--wide">
              <span class="cp-header-detail__icon" aria-hidden="true"><i class="fa fa-map-marker"></i></span>
              <span class="cp-header-detail__body">
                <span class="cp-header-detail__label">Address</span>
                <a class="cp-header-detail__value"
                   href="https://www.google.com/maps/search/?api=1&query={{ urlencode($project->client_address) }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   title="Open in Google Maps">{{ $project->client_address }}</a>
              </span>
            </div>
          @endif
        </div>
      @endif
    </div>

    {{-- ─── Notes inline edit drawer ──────────────────────────────────────────
         Opens from the "Add notes" or pencil icon buttons. A small slide-over
         panel with a textarea + save (PUT to client-projects.update with the
         required fields included as hidden inputs).
    ──────────────────────────────────────────────────────────────────────── --}}
    <div id="cp-notes-drawer"
         class="cp-nd"
         role="dialog"
         aria-modal="true"
         aria-labelledby="cp-nd-title"
         hidden>
      <div class="cp-nd__overlay" onclick="cpCloseNotesDrawer()"></div>
      <div class="cp-nd__panel">
        <div class="cp-nd__header">
          <h3 class="cp-nd__title" id="cp-nd-title">
            <i class="fa fa-sticky-note-o" aria-hidden="true"></i>
            {{ filled($project->notes) ? 'Edit notes' : 'Add notes' }}
          </h3>
          <button type="button" class="cp-nd__close" onclick="cpCloseNotesDrawer()" aria-label="Close">
            <i class="fa fa-times" aria-hidden="true"></i>
          </button>
        </div>

        <form action="{{ route('admin.client-projects.update', $project) }}"
              method="post"
              class="cp-nd__form"
              id="cp-nd-form">
          @csrf
          @method('PUT')
          {{-- Required by the controller (same pattern as the schedule partial form) --}}
          <input type="hidden" name="name"         value="{{ $project->name }}">
          <input type="hidden" name="client_name"  value="{{ $project->client_name }}">

          <div class="cp-nd__body">
            <label class="cp-nd__label" for="cp-nd-textarea">
              Internal notes
              <span class="cp-nd__label-hint">Not visible to the client.</span>
            </label>
            <textarea id="cp-nd-textarea"
                      name="notes"
                      class="admin-input cp-nd__textarea"
                      rows="10"
                      maxlength="5000"
                      placeholder="Add any internal notes about this project, scope details, client preferences, reminders…">{{ old('notes', $project->notes) }}</textarea>
            <p class="admin-form-help cp-nd__char-hint" id="cp-nd-char-hint"></p>
          </div>

          <div class="cp-nd__footer">
            <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm" id="cp-nd-save">
              <i class="fa fa-save" aria-hidden="true"></i> Save notes
            </button>
            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" onclick="cpCloseNotesDrawer()">
              Cancel
            </button>
            @if (filled($project->notes))
              {{-- Clear notes option --}}
              <button type="button"
                      class="admin-btn admin-btn--ghost admin-btn--sm cp-nd__clear-btn"
                      id="cp-nd-clear"
                      title="Remove all notes from this project">
                <i class="fa fa-trash-o" aria-hidden="true"></i> Clear
              </button>
            @endif
          </div>
        </form>
      </div>
    </div>

    <style>
      /* ── Notes edit icon button (pencil, beside the Notes badge) ──────── */
      .cp-notes-edit-icon-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        background: none;
        border: 1px solid transparent;
        border-radius: 5px;
        color: #6b7280;
        cursor: pointer;
        font-size: 11px;
        padding: 0;
        margin-left: 2px;
        vertical-align: middle;
        transition: background 0.15s, color 0.15s, border-color 0.15s;
      }
      .cp-notes-edit-icon-btn:hover {
        background: #f3f4f6;
        border-color: #d1d5db;
        color: #024768;
      }

      /* ── "Add notes" insert button ────────────────────────────────────── */
      .cp-notes-insert-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: none;
        border: 1px dashed #c9d3e0;
        border-radius: 6px;
        padding: 3px 9px;
        font-size: 12px;
        color: #6b7280;
        cursor: pointer;
        transition: background 0.15s, color 0.15s, border-color 0.15s;
      }
      .cp-notes-insert-btn:hover {
        background: #f0f7ff;
        border-color: #6faacd;
        color: #024768;
      }

      /* ── Notes drawer shell ───────────────────────────────────────────── */
      .cp-nd {
        position: fixed;
        inset: 0;
        z-index: 1060;
        display: flex;
        justify-content: flex-end;
      }
      .cp-nd[hidden]   { display: none; }
      .cp-nd.is-open   { display: flex; }

      .cp-nd__overlay {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.4);
        cursor: pointer;
        animation: cpNdFade 0.16s ease;
      }

      .cp-nd__panel {
        position: relative;
        width: min(480px, 100vw);
        height: 100%;
        background: #fff;
        display: flex;
        flex-direction: column;
        box-shadow: -4px 0 28px rgba(0,0,0,0.16);
        animation: cpNdSlide 0.2s cubic-bezier(.22,.68,0,1.2);
      }

      @keyframes cpNdFade  { from { opacity:0; } to { opacity:1; } }
      @keyframes cpNdSlide { from { transform: translateX(100%); } to { transform: translateX(0); } }

      .cp-nd__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 18px 20px 14px;
        border-bottom: 1px solid #e5e7eb;
        flex-shrink: 0;
      }
      .cp-nd__title {
        margin: 0;
        font-size: 17px;
        font-weight: 700;
        color: #111827;
        display: flex;
        align-items: center;
        gap: 8px;
      }
      .cp-nd__close {
        background: none;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: #6b7280;
        font-size: 13px;
        flex-shrink: 0;
        transition: background 0.12s, color 0.12s;
      }
      .cp-nd__close:hover { background: #f3f4f6; color: #111827; }

      .cp-nd__form   { display: flex; flex-direction: column; flex: 1 1 0; min-height: 0; }
      .cp-nd__body   { flex: 1 1 0; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 8px; }

      .cp-nd__label {
        font-size: 13px;
        font-weight: 600;
        color: #374151;
        display: flex;
        flex-direction: column;
        gap: 2px;
      }
      .cp-nd__label-hint {
        font-size: 11px;
        font-weight: 400;
        color: #9ca3af;
      }

      .cp-nd__textarea {
        width: 100%;
        resize: vertical;
        min-height: 180px;
        font-size: 14px;
        line-height: 1.6;
        color: #111827;
        box-sizing: border-box;
      }
      .cp-nd__char-hint {
        font-size: 11px;
        color: #9ca3af;
        text-align: right;
        margin: 0;
      }

      .cp-nd__footer {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
        padding: 14px 20px;
        border-top: 1px solid #e5e7eb;
        flex-shrink: 0;
      }
      .cp-nd__clear-btn {
        margin-left: auto;
        color: #b91c1c !important;
        border-color: #fca5a5 !important;
      }
      .cp-nd__clear-btn:hover { background: #fef2f2 !important; }
    </style>

    <script>
      (function () {
        var drawer   = document.getElementById('cp-notes-drawer');
        var textarea = document.getElementById('cp-nd-textarea');
        var charHint = document.getElementById('cp-nd-char-hint');
        var clearBtn = document.getElementById('cp-nd-clear');
        var MAX      = 5000;

        function updateCharHint() {
          if (!textarea || !charHint) return;
          var remaining = MAX - textarea.value.length;
          charHint.textContent = remaining + ' characters remaining';
        }

        window.cpOpenNotesDrawer = function () {
          if (!drawer) return;
          drawer.removeAttribute('hidden');
          drawer.classList.add('is-open');
          document.body.style.overflow = 'hidden';
          if (textarea) {
            textarea.focus();
            textarea.setSelectionRange(textarea.value.length, textarea.value.length);
          }
          updateCharHint();
        };

        window.cpCloseNotesDrawer = function () {
          if (!drawer) return;
          drawer.classList.remove('is-open');
          drawer.setAttribute('hidden', '');
          document.body.style.overflow = '';
        };

        // Character counter
        if (textarea) {
          textarea.addEventListener('input', updateCharHint);
          updateCharHint();
        }

        // Clear button: wipe textarea content before submit
        if (clearBtn) {
          clearBtn.addEventListener('click', function () {
            if (!confirm('Remove all notes from this project?')) return;
            if (textarea) textarea.value = '';
            var form = document.getElementById('cp-nd-form');
            if (form) form.submit();
          });
        }

        // Escape key closes
        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape' && drawer && !drawer.hidden) {
            cpCloseNotesDrawer();
          }
        });
      })();
    </script>

    @if ($project->photos->isNotEmpty())
      @include('admin.client_projects._project_photos_gallery', [
        'project' => $project,
        'galleryId' => 'cp-show-project-photos',
        'editable' => false,
      ])
    @endif

    @unless ($isClosed)
      {{-- Status form: label + help on one line, then status | quick note | save.
           Status still auto-saves on change; Save note keeps a note on the current status. --}}
      <form action="{{ route('admin.client-projects.status.update', $project) }}" method="post" class="cp-status-form" id="cp-status-form" data-current-status="{{ $project->status }}">
        @csrf
        <div class="cp-status-form-head">
          <label for="cp-status-select" class="cp-status-form-label">Change status</label>
          <span class="cp-hint" title="Workflow: Visit required → Visit scheduled → Quote Pending → Quote Sent → Proposal Accepted → Pending First Payment → Scheduled → In Progress → Awaiting Final Payment → Completed. Cancelled is final; rejected projects can be reopened. Time tracking, quotation, contract, invoice, and financials unlock from Proposal Accepted onward.">?</span>
          <span class="cp-status-form-help">Status saves on change. Optional note is for this status only.</span>
        </div>
        <div class="cp-status-controls">
          <select id="cp-status-select" name="status" class="admin-input cp-status-select">
            @foreach ($statusLabels as $key => $label)
              <option value="{{ $key }}" @selected($project->status === $key)>{{ $label }}</option>
            @endforeach
          </select>
          <input type="text"
                 id="cp-status-note"
                 name="note"
                 class="admin-input cp-status-note"
                 maxlength="2000"
                 value="{{ old('note', $project->status_note) }}"
                 placeholder="Quick note for this status…"
                 aria-label="Quick note for this status"
                 title="Saved with this status. Changing status starts a new note unless you type one as you change.">
          <button type="submit" class="admin-btn admin-btn--primary cp-status-save" id="cp-status-save">
            <i class="fa fa-save" aria-hidden="true"></i> Save note
          </button>
        </div>
      </form>

      <div id="cp-cancel-modal" class="cp-status-modal" hidden>
        <div class="cp-status-modal-backdrop" data-cancel-modal-dismiss tabindex="-1" aria-hidden="true"></div>
        <div class="cp-status-modal-panel" role="dialog" aria-modal="true" aria-labelledby="cp-cancel-modal-title">
          <h2 id="cp-cancel-modal-title" class="cp-status-modal-title">Cancel project</h2>
          <p class="cp-status-modal-hint">This will mark the project as <strong>Cancelled</strong>. Please provide a reason — it will be saved in the project history.</p>
          <label for="cp-cancel-reason" class="cp-status-modal-label">Reason for cancelling</label>
          <textarea id="cp-cancel-reason" class="admin-input cp-status-modal-textarea" rows="4" maxlength="2000" required placeholder="e.g. Client went with another contractor"></textarea>
          <div class="cp-status-modal-actions">
            <button type="button" class="admin-btn admin-btn--ghost" data-cancel-modal-dismiss>Keep project active</button>
            <button type="button" class="admin-btn admin-btn--primary" id="cp-cancel-confirm">Confirm cancellation</button>
          </div>
        </div>
      </div>

      <script>
        (function () {
          var form = document.getElementById('cp-status-form');
          if (!form) return;
          var select = form.querySelector('#cp-status-select');
          if (!select) return;

          var cancelledStatus = @json(\App\Models\ClientProject::STATUS_CANCELLED);
          var currentStatus = form.getAttribute('data-current-status');
          var noteInput = document.getElementById('cp-status-note');
          var modal = document.getElementById('cp-cancel-modal');
          var cancelReason = document.getElementById('cp-cancel-reason');
          var confirmBtn = document.getElementById('cp-cancel-confirm');

          form.classList.add('cp-status-form--auto');

          var submitting = false;
          var originalNote = noteInput ? String(noteInput.value || '') : '';

          function submitStatus() {
            if (submitting) return;
            submitting = true;
            select.disabled = true;
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'status';
            hidden.value = select.value;
            form.appendChild(hidden);
            form.submit();
          }

          function openCancelModal() {
            if (!modal || !cancelReason) return;
            cancelReason.value = '';
            modal.hidden = false;
            document.documentElement.classList.add('cp-cancel-modal-open');
            cancelReason.focus();
          }

          function closeCancelModal(revertSelect) {
            if (!modal) return;
            modal.hidden = true;
            document.documentElement.classList.remove('cp-cancel-modal-open');
            if (revertSelect) {
              select.value = currentStatus;
            }
          }

          select.addEventListener('change', function () {
            if (submitting) return;
            if (select.value === currentStatus) return;

            if (select.value === cancelledStatus) {
              openCancelModal();
              return;
            }

            // Unedited notes belong to the previous status. Start the new
            // status blank unless the user typed a fresh note first.
            if (noteInput && String(noteInput.value || '') === originalNote) {
              noteInput.value = '';
            }

            submitStatus();
          });

          if (confirmBtn) {
            confirmBtn.addEventListener('click', function () {
              if (!cancelReason) return;
              var reason = cancelReason.value.trim();
              if (!reason) {
                cancelReason.setCustomValidity('Please enter a reason for cancelling.');
                cancelReason.reportValidity();
                cancelReason.setCustomValidity('');
                return;
              }
              if (noteInput) {
                noteInput.value = reason;
              }
              closeCancelModal(false);
              submitStatus();
            });
          }

          document.querySelectorAll('[data-cancel-modal-dismiss]').forEach(function (el) {
            el.addEventListener('click', function () {
              closeCancelModal(true);
            });
          });

          document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal && !modal.hidden) {
              closeCancelModal(true);
            }
          });
        })();
      </script>
      @if (filled($project->notes))
        <script>
          (function () {
            var toggle = document.getElementById('cp-notes-toggle');
            var popover = document.getElementById('cp-notes-popover');
            if (!toggle || !popover) return;

            function closePopover() {
              toggle.setAttribute('aria-expanded', 'false');
              popover.hidden = true;
            }

            function openPopover() {
              toggle.setAttribute('aria-expanded', 'true');
              popover.hidden = false;
            }

            toggle.addEventListener('click', function (e) {
              e.preventDefault();
              if (popover.hidden) {
                openPopover();
              } else {
                closePopover();
              }
            });

            document.addEventListener('click', function (e) {
              if (popover.hidden) return;
              if (toggle.contains(e.target) || popover.contains(e.target)) return;
              closePopover();
            });

            document.addEventListener('keydown', function (e) {
              if (e.key === 'Escape') closePopover();
            });
          })();
        </script>
      @endif
    @else
      @if ($project->isCancelled())
        <p class="cp-final-note cp-final-note--cancelled">
          <i class="fa fa-times-circle"></i>
          This project is marked as <strong>Cancelled</strong>.
          @if (filled($project->cancelled_reason))
            <span class="cp-final-note-reason">Reason: {{ $project->cancelled_reason }}</span>
          @endif
        </p>
      @else
        <div class="cp-reopen-block">
          <p class="cp-final-note"><i class="fa fa-ban"></i> This project is marked as <strong>Rejected</strong>.</p>
          <form action="{{ route('admin.client-projects.reopen', $project) }}" method="post" class="cp-reopen-form" id="cp-reopen-form">
            @csrf
            <div class="cp-reopen-form-head">
              <label for="cp-reopen-status" class="cp-reopen-form-label">Reopen project</label>
              <span class="cp-reopen-form-help">Restore workflow by setting a new status.</span>
            </div>
            <div class="cp-reopen-controls">
              <select id="cp-reopen-status" name="status" class="admin-input cp-reopen-select" required>
                @foreach (\App\Models\ClientProject::reopenableStatuses() as $statusKey)
                  <option value="{{ $statusKey }}" @selected($project->suggestedReopenStatus() === $statusKey)>
                    {{ $statusLabels[$statusKey] ?? $statusKey }}
                  </option>
                @endforeach
              </select>
              <button type="submit" class="admin-btn admin-btn--primary cp-reopen-submit">
                <i class="fa fa-undo"></i> Reopen
              </button>
            </div>
            <div class="admin-form-group cp-reopen-note-wrap">
              <label for="cp-reopen-note" class="cp-reopen-note-label">Note (optional)</label>
              <input type="text" id="cp-reopen-note" name="note" class="admin-input" maxlength="2000" placeholder="e.g. Client reconsidered after follow-up call">
            </div>
          </form>
        </div>
        <script>
          (function () {
            var form = document.getElementById('cp-reopen-form');
            if (!form) return;
            form.addEventListener('submit', function (e) {
              if (!window.confirm('Reopen this rejected project and restore the selected status?')) {
                e.preventDefault();
              }
            });
          })();
        </script>
      @endif
    @endunless

    <div class="cp-header-actions">
      <a href="{{ route('admin.client-projects.edit', $project) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-pencil"></i> Edit details
      </a>
      @if ($calendarLink)
        <a href="{{ $calendarLink }}" target="_blank" rel="noopener noreferrer" class="cp-calendar-link cp-calendar-link--btn">
          <i class="fa fa-calendar" aria-hidden="true"></i>
          <span>Add to Google Calendar</span>
        </a>
      @endif
    </div>
  </div>

  {{-- TABS ---------------------------------------------------------------- --}}
  <div class="cp-tabs" role="tablist">
    <button type="button" class="cp-tab is-active" data-tab="overview" role="tab">Overview</button>
    <button type="button" class="cp-tab" data-tab="schedule" role="tab">Calendar</button>
    <button type="button" class="cp-tab" data-tab="time" role="tab">Time Tracking</button>
    <button type="button" class="cp-tab" data-tab="quotation" role="tab">Quotation</button>
    <button type="button" class="cp-tab" data-tab="contract" role="tab">Contract</button>
    <button type="button" class="cp-tab" data-tab="invoice" role="tab">Invoice</button>
    <button type="button" class="cp-tab" data-tab="financials" role="tab">Financials</button>
    <button type="button" class="cp-tab" data-tab="utilities" role="tab">
      Utilities
      @if (($linkedUtilities['total_count'] ?? 0) > 0)
        <span class="cp-tab-count">{{ $linkedUtilities['total_count'] }}</span>
      @endif
    </button>
    <button type="button" class="cp-tab" data-tab="history" role="tab">History</button>
  </div>

  {{-- OVERVIEW TAB --------------------------------------------------------- --}}
  <section class="cp-tab-panel is-active" data-panel="overview">
    @if ($isCompleted)
      @include('admin.client_projects._final_summary', ['project' => $project])
    @endif

    @include('admin.client_projects._travel_cost', ['project' => $project])

    @if ($linkedUtilities['has_any'] ?? false)
      <div class="admin-card cp-utilities-teaser">
        <div class="cp-utilities-teaser__text">
          <h3>Linked utilities</h3>
          <p class="admin-form-help">
            {{ $linkedUtilities['total_count'] }}
            {{ \Illuminate\Support\Str::plural('saved item', $linkedUtilities['total_count']) }}
            from admin calculators are linked to this project.
          </p>
        </div>
        <a href="#utilities" class="admin-btn admin-btn--ghost admin-btn--sm">View utilities</a>
      </div>
    @endif

    <div class="admin-card">
      <h3>Details</h3>
      <dl class="cp-dl">
        <dt>Project name</dt><dd>{{ $project->name }}</dd>
        <dt>Client</dt><dd>{{ $project->client_name }}</dd>
        <dt>Email</dt><dd>{{ $project->client_email ?: '—' }}</dd>
        <dt>Secondary email</dt><dd>{{ $project->secondaryClientEmail() ?: '—' }}</dd>
        <dt>Phone</dt><dd>{{ \App\Models\ClientProject::formatPhone($project->client_phone) ?: '—' }}</dd>
        <dt>Address</dt><dd>{{ $project->client_address ?: '—' }}</dd>
        <dt>Created</dt><dd>{{ \App\Models\ClientProject::formatDateTime($project->created_at) }}</dd>
        <dt>Status changed</dt><dd>{{ \App\Models\ClientProject::formatDateTime($project->status_changed_at) }}</dd>
        @if ($project->visit_required || $project->visit_date || $project->visit_done)
          <dt>Visit</dt>
          <dd>
            @if ($project->visit_done)
              Done
              @if ($project->visit_date)
                ({{ \App\Models\ClientProject::formatDate($project->visit_date) }})
              @endif
            @elseif ($project->visit_required)
              {{ $project->visit_date ? \App\Models\ClientProject::formatDate($project->visit_date) : 'Required — date not set yet' }}
            @elseif ($project->visit_date)
              {{ \App\Models\ClientProject::formatDate($project->visit_date) }}
            @endif
          </dd>
        @endif
      </dl>

      @if ($project->notes)
        <h3 style="margin-top:24px;">Notes</h3>
        <div class="cp-notes-box">{{ $project->notes }}</div>
      @endif
    </div>
  </section>

  {{-- SCHEDULE / CALENDAR TAB ---------------------------------------------- --}}
  <section class="cp-tab-panel" data-panel="schedule" id="schedule">
    @php
      $datesSet = $project->start_date && $project->end_date;
      $hasContact = $project->hasClientContactInfo();
      $canSendDates = !$isClosed && $datesSet && $hasContact;
      $lastSentDates = \App\Models\ClientCommunicationLog::query()
        ->where('client_project_id', $project->id)
        ->where('reason', \App\Models\ClientCommunicationLog::REASON_SCHEDULE_DATES_MANUAL)
        ->where('status', \App\Models\ClientCommunicationLog::STATUS_SENT)
        ->latest('sent_at')
        ->first();
      $lastSentRecipients = $lastSentDates
        ? trim($lastSentDates->recipient.($lastSentDates->cc ? ', '.$lastSentDates->cc : ''), ', ')
        : null;
      $sendDatesConfirm = $lastSentDates
        ? 'Re-send the project dates to '.($project->clientEmailRecipientsLabel() ?: 'the client').'?'
        : 'Email the project dates to '.($project->clientEmailRecipientsLabel() ?: 'the client').'?';
    @endphp

    <form id="cp-schedule-dates-form" action="{{ route('admin.client-projects.update', $project) }}" method="post" class="cp-schedule-form">
      @csrf
      @method('PUT')
      {{-- Include required fields (controller validates the full update payload). --}}
      <input type="hidden" name="name" value="{{ $project->name }}">
      <input type="hidden" name="client_name" value="{{ $project->client_name }}">
      <input type="hidden" name="client_email" value="{{ $project->client_email }}">
      <input type="hidden" name="client_phone" value="{{ $project->client_phone }}">
      <input type="hidden" name="client_address" value="{{ $project->client_address }}">
      <input type="hidden" name="notes" value="{{ $project->notes }}">

      @php
        $cpShowVisitDateVal = old('visit_date', optional($project->visit_date)?->toDateString());
        $cpShowVisitExpandOpen = (bool) old('visit_required', $project->visit_required)
          || (bool) old('visit_done', $project->visit_done)
          || filled($cpShowVisitDateVal)
          || $errors->has('visit_date');
      @endphp
      <div class="admin-card cp-visit-card cp-visit-section">
        @include('admin.client_projects._visit_required_toggle', [
          'visitRequiredId' => 'cp-visit-required',
          'visitRequiredChecked' => old('visit_required', $project->visit_required),
          'expandableId' => 'cp-schedule-visit-expandable',
          'visitDateId' => 'cp-visit-date',
          'visitDateValue' => $cpShowVisitDateVal,
          'expandOpen' => $cpShowVisitExpandOpen,
          'showVisitDone' => true,
          'visitDoneId' => 'cp-visit-done',
          'visitDoneChecked' => old('visit_done', $project->visit_done),
        ])
      </div>

      <div class="admin-card">
        <h3>Start &amp; End dates</h3>
        <p class="admin-form-help">Saving both dates schedules the project on the Project Calendar and emails you a Google Calendar link. Picking dates on the calendar saves them immediately.</p>
        <div id="cp-schedule-date-warning" class="admin-flash admin-flash--warning admin-flash--js-hidden" role="alert"></div>

        @include('admin.partials.schedule_date_picker', [
          'startDateId' => 'cp-start',
          'endDateId' => 'cp-end',
          'excludeProjectId' => $project->id,
          'submitFormId' => 'cp-schedule-dates-form',
          'hint' => 'See other projects\' booked days, click two days (start, then end), then Use these dates to save.',
        ])

        <div class="admin-form-grid-2">
          <div class="admin-form-group">
            <label for="cp-start">Start date</label>
            <input class="admin-input" type="date" id="cp-start" name="start_date" value="{{ old('start_date', optional($project->start_date)->toDateString()) }}">
            @error('start_date') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
          <div class="admin-form-group">
            <label for="cp-end">End date</label>
            <input class="admin-input" type="date" id="cp-end" name="end_date" value="{{ old('end_date', optional($project->end_date)->toDateString()) }}">
            @error('end_date') <p class="admin-form-error">{{ $message }}</p> @enderror
            <p class="admin-form-help">Same-day start &amp; end is allowed.</p>
          </div>
        </div>

        @if ($project->start_date && $project->end_date)
          <div class="cp-schedule-summary cp-schedule-summary--with-action">
            <span class="cp-schedule-summary__dates">
              <strong>Scheduled:</strong>
              {{ \App\Models\ClientProject::formatDate($project->start_date) }}
              <span class="cp-schedule-summary__arrow" aria-hidden="true">→</span>
              {{ \App\Models\ClientProject::formatDate($project->end_date) }}
            </span>
            @if ($calendarLink)
              <a href="{{ $calendarLink }}" target="_blank" rel="noopener noreferrer" class="cp-calendar-link">
                <i class="fa fa-calendar" aria-hidden="true"></i>
                <span>Add to Google Calendar</span>
              </a>
            @endif
          </div>
        @endif

        @unless ($isClosed)
          @if (!$datesSet)
            <p class="admin-form-help cp-send-dates-note cp-send-dates-note--warn">
              <i class="fa fa-exclamation-triangle"></i>
              Save both dates first to enable <strong>Send dates to client</strong>.
            </p>
          @elseif (!$hasContact)
            <p class="admin-form-help cp-send-dates-note cp-send-dates-note--warn">
              <i class="fa fa-exclamation-triangle"></i>
              Missing client contact info: {{ implode(', ', $project->missingClientContactFields()) }}.
              <a href="{{ route('admin.client-projects.edit', $project) }}">Edit project details</a> to fix.
            </p>
          @endif

          @if ($lastSentDates)
            <p class="admin-form-help cp-send-dates-note">
              <i class="fa fa-check-circle" style="color:#10b981;"></i>
              Dates last emailed on
              <strong>{{ \App\Models\ClientProject::formatDateTime($lastSentDates->sent_at) }}</strong>
              to {{ $lastSentRecipients ?: $project->clientEmailRecipientsLabel() }}.
            </p>
          @endif
        @endunless

        <div class="cp-schedule-actions">
          <button type="submit" class="admin-btn admin-btn--primary">Save dates</button>
          @unless ($isClosed)
            {{-- This button lives in the dates card but submits the small
                 #cp-send-dates-form below via the HTML5 `form=` attribute. --}}
            <button type="submit"
                    form="cp-send-dates-form"
                    class="admin-btn admin-btn--primary cp-send-dates-btn"
                    @disabled(!$canSendDates)
                    title="Email {{ $project->clientEmailRecipientsLabel() ?: 'the client' }} the scheduled dates.">
              <i class="fa fa-paper-plane"></i>
              {{ $lastSentDates ? 'Re-send dates to client' : 'Send dates to client' }}
            </button>
          @endunless
        </div>
      </div>
    </form>

    {{-- Standalone form for the "Send dates to client" button placed inside
         the dates card above (referenced via `form="cp-send-dates-form"`).
         Kept separate so its POST doesn't piggyback on the project-update
         payload. --}}
    @unless ($isClosed)
      <form id="cp-send-dates-form"
            action="{{ route('admin.client-projects.schedule-dates.send', $project) }}"
            method="post"
            class="cp-send-dates-form"
            onsubmit="return confirm('{{ $sendDatesConfirm }}');">
        @csrf
      </form>
    @endunless

    {{-- Hourly-only: manual "service confirmation" email. The rate was
         agreed verbally up front, so the client doesn't get the automatic
         pre-quote acknowledgement — instead Jonas sends this confirmation once
         the dates are locked in. --}}
    @if ($project->billing_type === \App\Models\ClientProject::BILLING_HOURLY && !$isClosed)
      <div class="admin-card cp-service-confirmation-card">
        <h3>Service confirmation</h3>
        <p class="admin-form-help">
          Hourly-billing projects skip the quotation flow because the rate was agreed verbally.
          Once the dates are saved above, send the client a confirmation email with the scheduled
          {{ $project->start_date && $project->end_date && \Illuminate\Support\Carbon::parse($project->start_date)->isSameDay(\Illuminate\Support\Carbon::parse($project->end_date)) ? 'date' : 'dates' }},
          hourly rate, materials policy, and payment instructions.
        </p>

        @php
          $datesSet = $project->start_date && $project->end_date;
          $hasContact = $project->hasClientContactInfo();
          $alreadySent = (bool) $project->service_confirmation_sent_at;
          $canSend = $datesSet && $hasContact;
        @endphp

        @if (!$datesSet)
          <p class="admin-form-help" style="color:#b45309;">
            <i class="fa fa-exclamation-triangle"></i>
            Save both the start and end dates before you can send the confirmation.
          </p>
        @elseif (!$hasContact)
          <p class="admin-form-help" style="color:#b45309;">
            <i class="fa fa-exclamation-triangle"></i>
            Missing client contact info: {{ implode(', ', $project->missingClientContactFields()) }}.
            <a href="{{ route('admin.client-projects.edit', $project) }}">Edit project details</a> to fix.
          </p>
        @endif

        @if ($alreadySent)
          <p class="admin-form-help">
            <i class="fa fa-check-circle" style="color:#10b981;"></i>
            Last sent on
            <strong>{{ \App\Models\ClientProject::formatDateTime($project->service_confirmation_sent_at) }}</strong>.
          </p>
        @endif

        <form action="{{ route('admin.client-projects.service-confirmation.send', $project) }}"
              method="post"
              class="cp-schedule-form"
              onsubmit="return confirm('{{ $alreadySent ? 'Re-send the service confirmation email to '.($project->clientEmailRecipientsLabel() ?: 'the client').'?' : 'Send the service confirmation email to '.($project->clientEmailRecipientsLabel() ?: 'the client').'?' }}');">
          @csrf
          <button type="submit"
                  class="admin-btn admin-btn--primary"
                  @disabled(!$canSend)>
            <i class="fa fa-paper-plane"></i>
            {{ $alreadySent ? 'Re-send service confirmation' : 'Send service confirmation' }}
          </button>
        </form>
      </div>
    @endif

  </section>

  {{-- TIME TRACKING TAB --------------------------------------------------- --}}
  <section class="cp-tab-panel" data-panel="time" id="time">
    @if (!$isAccepted)
      <div class="admin-card cp-locked">
        <h3>Time tracking is locked</h3>
        <p>Time entries become available once the project status is <strong>Proposal Accepted</strong> or later.</p>
      </div>
    @else
      @include('admin.client_projects._time', ['project' => $project])
      @include('admin.client_projects._helper_time', ['project' => $project, 'activeHelpers' => $activeHelpers ?? collect()])
    @endif
  </section>

  {{-- QUOTATION TAB ------------------------------------------------------- --}}
  <section class="cp-tab-panel" data-panel="quotation" id="quotation">
    @include('admin.client_projects._quotation', ['project' => $project])
  </section>

  {{-- CONTRACT TAB -------------------------------------------------------- --}}
  <section class="cp-tab-panel" data-panel="contract" id="contract">
    @include('admin.client_projects._contract', ['project' => $project])
  </section>

  {{-- INVOICE TAB --------------------------------------------------------- --}}
  <section class="cp-tab-panel" data-panel="invoice" id="invoice">
    @include('admin.client_projects._invoice', ['project' => $project])
  </section>

  {{-- FINANCIALS TAB ------------------------------------------------------- --}}
  <section class="cp-tab-panel" data-panel="financials" id="financials">
    @if (!$isAccepted)
      <div class="admin-card cp-locked">
        <h3>Financials are locked</h3>
        <p>Financial details become available once the project status is <strong>Proposal Accepted</strong> or later.</p>
      </div>
    @else
      @include('admin.client_projects._financials', ['project' => $project])
    @endif
  </section>

  {{-- UTILITIES TAB ------------------------------------------------------- --}}
  <section class="cp-tab-panel" data-panel="utilities" id="utilities">
    @include('admin.client_projects._utilities', ['project' => $project, 'linkedUtilities' => $linkedUtilities])
  </section>

  {{-- HISTORY TAB --------------------------------------------------------- --}}
  <section class="cp-tab-panel" data-panel="history">
    <div class="admin-card">
      <h3>Status history</h3>
      @if ($project->statusHistory->isEmpty())
        <p class="admin-form-help">No history yet.</p>
      @else
        <div class="admin-table-scroll">
        <table class="admin-table cp-history-table">
          <thead>
            <tr>
              <th>When</th>
              <th>Event</th>
              <th>Note</th>
              <th>By</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($project->statusHistory as $entry)
              <tr>
                <td>{{ $entry->changedAtDisplay() }}</td>
                <td>
                  @if ($entry->isEvent())
                    <span class="cp-badge" style="background:#e5e7eb;color:#4b5563;">Event</span>
                  @else
                    <span class="cp-badge" style="background:#dbeafe;color:#1e40af;">
                      {{ $entry->fromLabel() }} → {{ $entry->toLabel() }}
                    </span>
                  @endif
                </td>
                <td>{{ $entry->note ?: '—' }}</td>
                <td>{{ $entry->user?->email ?? 'system' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
        </div>
      @endif
    </div>
  </section>

  {{-- TAB SCRIPT ---------------------------------------------------------- --}}
  <script>
    (function () {
      var tabs = document.querySelectorAll('.cp-tab');
      var panels = document.querySelectorAll('.cp-tab-panel');
      if (!tabs.length) return;

      function show(key) {
        tabs.forEach(function (t) {
          t.classList.toggle('is-active', t.getAttribute('data-tab') === key);
        });
        panels.forEach(function (p) {
          p.classList.toggle('is-active', p.getAttribute('data-panel') === key);
        });
        // remember last tab for a refresh within the same page session
        try { sessionStorage.setItem('cpActiveTab-{{ $project->id }}', key); } catch (e) {}
      }

      tabs.forEach(function (t) {
        t.addEventListener('click', function () { show(t.getAttribute('data-tab')); });
      });

      // Priority: explicit URL hash (e.g. /show#contract) wins over the
      // session-remembered tab. This lets the controller redirect a user
      // back to the right tab after a save/email action.
      var hashTab = (window.location.hash || '').replace('#', '');
      if (hashTab && document.querySelector('.cp-tab[data-tab="' + hashTab + '"]')) {
        show(hashTab);
      } else {
        try {
          var saved = sessionStorage.getItem('cpActiveTab-{{ $project->id }}');
          if (saved) show(saved);
        } catch (e) {}
      }

      window.addEventListener('hashchange', function () {
        var tab = (window.location.hash || '').replace('#', '');
        if (tab && document.querySelector('.cp-tab[data-tab="' + tab + '"]')) {
          show(tab);
        }
      });
    })();
  </script>
  <script>
    (function () {
      var form = document.getElementById('cp-schedule-dates-form');
      if (!form) return;
      var start = form.querySelector('[name="start_date"]');
      var end = form.querySelector('[name="end_date"]');
      var warn = document.getElementById('cp-schedule-date-warning');
      var visitCb = document.getElementById('cp-visit-required');
      var visitDate = document.getElementById('cp-visit-date');
      var visitDoneCb = document.getElementById('cp-visit-done');
      var visitExpand = document.getElementById('cp-schedule-visit-expandable');

      function syncVisitExpandable() {
        if (!visitExpand) return;
        var required = visitCb ? visitCb.checked : false;
        var done = visitDoneCb ? visitDoneCb.checked : false;
        var open = required || done;
        visitExpand.classList.toggle('is-open', open);
        visitExpand.setAttribute('aria-hidden', open ? 'false' : 'true');
      }

      function syncVisitFields() {
        if (!visitDate) return;
        var required = visitCb ? visitCb.checked : false;
        var done = visitDoneCb ? visitDoneCb.checked : false;
        if (!required && !done) {
          visitDate.value = '';
        }
        visitDate.readOnly = !!done;
        visitDate.disabled = !done && !required;
        visitDate.classList.toggle('cp-visit-date-input--locked', !!done || !required);
        syncVisitExpandable();
      }

      function checkScheduleDates() {
        if (!start || !end || !warn) return true;
        if (!start.value || !end.value) {
          warn.classList.add('admin-flash--js-hidden');
          warn.textContent = '';
          return true;
        }
        if (start.value> end.value) {
          warn.textContent = 'The start date is after the end date. Choose an end date on or after the start date (same day is fine), then save.';
          warn.classList.remove('admin-flash--js-hidden');
          return false;
        }
        warn.classList.add('admin-flash--js-hidden');
        warn.textContent = '';
        return true;
      }

      if (start && end && warn) {
        start.addEventListener('change', checkScheduleDates);
        end.addEventListener('change', checkScheduleDates);
      }
      if (visitCb) visitCb.addEventListener('change', syncVisitFields);
      if (visitDoneCb) visitDoneCb.addEventListener('change', syncVisitFields);
      if (visitDate) visitDate.addEventListener('change', syncVisitExpandable);
      if (visitDate) visitDate.addEventListener('input', syncVisitExpandable);
      syncVisitFields();

      form.addEventListener('submit', function (e) {
        if (visitDate && visitCb && !visitCb.checked && !(visitDoneCb && visitDoneCb.checked)) {
          visitDate.disabled = false;
          visitDate.readOnly = false;
          visitDate.value = '';
        }
        if (!checkScheduleDates()) {
          e.preventDefault();
          if (warn) warn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      });
    })();
  </script>
  <script src="{{ asset('js/admin-vehicles.js') }}?v={{ @filemtime(public_path('js/admin-vehicles.js')) ?: time() }}" defer></script>
@endsection
