@php
  $pageTitle = $pageTitle ?? 'Room Area';
  $backUrl = $backUrl ?? route('admin.utilities.index');
  $backLabel = $backLabel ?? 'All utilities';
  $clientProjectName = $clientProject->name ?? null;
  $initialRoomId = $initialRoomId ?? request()->integer('ra_room') ?: null;
  $initialProjectId = request()->integer('ra_project') ?: null;
@endphp

<div class="util-ra-openplan3d-wrap util-ra--fullpage"
  data-room-area-openplan3d
  data-projects="{{ json_encode($projectsJson ?? []) }}"
  data-client-projects="{{ json_encode(collect($clientProjects ?? [])->map(fn ($p) => ['id' => $p->id, 'name' => method_exists($p, 'linkSelectLabel') ? $p->linkSelectLabel() : ($p->name ?? 'Project #'.$p->id)])) }}"
  data-initial-room-id="{{ $initialRoomId }}"
  data-initial-project-id="{{ $initialProjectId }}"
  data-bound-client-project="{{ isset($clientProject) ? $clientProject->id : '' }}"
  data-room-store-template="{{ route('admin.utilities.room-area.rooms.store', ['room_area_project' => '__ID__']) }}"
  data-room-update-template="{{ route('admin.utilities.room-area.rooms.update', ['room_area_room' => '__ID__']) }}"
  data-room-destroy-template="{{ route('admin.utilities.room-area.rooms.destroy', ['room_area_room' => '__ID__']) }}"
  data-project-store-url="{{ route('admin.utilities.room-area.projects.store') }}"
  data-project-update-template="{{ route('admin.utilities.room-area.projects.update', ['room_area_project' => '__ID__']) }}"
  data-quote-room-template="{{ route('admin.utilities.room-area.quote') }}?layout=room:__ID__"
  data-quote-project-template="{{ route('admin.utilities.room-area.quote') }}?layout=project:__ID__"
>
  <input type="hidden" id="openplan3d-csrf" value="{{ csrf_token() }}">

  {{-- Top Toolbar --}}
  <header class="util-ra-openplan3d-header">
    <div class="util-ra-openplan3d-header__primary">
      <a href="{{ $backUrl }}" class="admin-btn admin-btn--ghost util-ra-openplan3d-back" aria-label="{{ $backLabel }}">
        <i class="fa fa-arrow-left" aria-hidden="true"></i>
        <span>{{ $backLabel }}</span>
      </a>

      <div class="util-ra-openplan3d-titles">
        <div class="util-ra-openplan3d-title-row">
          <h1 class="util-ra-openplan3d-title">{{ $pageTitle }}</h1>
          <span class="util-ra-badge util-ra-badge--openplan3d">openPlan3D</span>
          <span id="openplan3d-dirty-badge" class="openplan3d-dirty-badge" hidden title="Unsaved changes">
            <span class="openplan3d-dirty-dot"></span> Unsaved
          </span>
        </div>
        @if ($clientProjectName)
          <p class="util-ra-openplan3d-sub">{{ $clientProjectName }}</p>
        @endif
      </div>
    </div>

    {{-- Project & Room Navigation Controls --}}
    <div class="util-ra-openplan3d-header__selectors">
      <div class="openplan3d-control-group">
        <label for="openplan3d-project-select" class="openplan3d-control-label">Project</label>
        <select id="openplan3d-project-select" class="admin-input openplan3d-select" aria-label="Select Project">
          <option value="">— Select Project —</option>
        </select>
      </div>

      <div class="openplan3d-control-group">
        <label for="openplan3d-room-select" class="openplan3d-control-label">Room</label>
        <select id="openplan3d-room-select" class="admin-input openplan3d-select" aria-label="Select Room">
          <option value="">— Select Room —</option>
        </select>
      </div>

      <div class="openplan3d-control-group openplan3d-control-group--name">
        <label for="openplan3d-room-name" class="openplan3d-control-label">Room Name</label>
        <input type="text" id="openplan3d-room-name" class="admin-input openplan3d-input" placeholder="Room name" maxlength="120" aria-label="Room Name">
      </div>
    </div>

    {{-- Action Buttons --}}
    <div class="util-ra-openplan3d-header__actions">
      <button type="button" class="admin-btn admin-btn--ghost openplan3d-tool-btn" id="openplan3d-new-room-btn" title="Create new room in current project">
        <i class="fa fa-plus" aria-hidden="true"></i>
        <span class="openplan3d-btn-label">New Room</span>
      </button>

      <button type="button" class="admin-btn admin-btn--ghost openplan3d-tool-btn" id="openplan3d-settings-btn" aria-expanded="false" title="Room finish and height settings">
        <i class="fa fa-sliders" aria-hidden="true"></i>
        <span class="openplan3d-btn-label">Settings</span>
      </button>

      @include('admin.partials.info_tip', [
        'tipLabel' => 'openPlan3D engine preview',
        'tipText' => 'openPlan3D is a self-hosted 2D/3D floor plan editor running completely offline from our server. All measurements, floor plans, and quotes remain securely stored in your database.',
      ])

      <button type="button" class="admin-btn admin-btn--ghost openplan3d-tool-btn" id="openplan3d-theme-toggle" aria-label="Toggle dark/light theme" title="Toggle dark/light theme">
        <i class="fa fa-moon-o" data-theme-icon="moon" aria-hidden="true"></i>
        <i class="fa fa-sun-o" data-theme-icon="sun" aria-hidden="true" hidden></i>
      </button>

      <a href="#" class="admin-btn admin-btn--ghost openplan3d-tool-btn" id="openplan3d-quote-btn" hidden title="Create or add lines to a quotation">
        <i class="fa fa-file-text-o" aria-hidden="true"></i>
        <span class="openplan3d-btn-label">Quote</span>
      </a>

      <button type="button" class="admin-btn admin-btn--primary openplan3d-save-btn" id="openplan3d-save-btn" data-ra-save-room data-hotkey-save disabled title="Save Plan (Ctrl+S)">
        <i class="fa fa-save" aria-hidden="true"></i>
        <span class="openplan3d-save-text">Save Plan</span>
        <span class="openplan3d-save-spinner" hidden><i class="fa fa-spinner fa-spin" aria-hidden="true"></i></span>
      </button>
    </div>
  </header>

  {{-- Collapsible Room & Finish Settings Drawer --}}
  <div class="openplan3d-settings-drawer" id="openplan3d-settings-drawer" hidden>
    <div class="openplan3d-settings-grid">
      <div class="admin-form-group openplan3d-setting-item">
        <label for="ra-wall-height">Wall Height (ft)</label>
        <input type="number" step="0.1" min="1" max="25" id="ra-wall-height" class="admin-input" value="8">
      </div>
      <div class="admin-form-group openplan3d-setting-item">
        <label for="ra-ceiling-height">Ceiling Height (ft)</label>
        <input type="number" step="0.1" min="1" max="25" id="ra-ceiling-height" class="admin-input" value="8">
      </div>
      <div class="admin-form-group openplan3d-setting-item">
        <label for="ra-floor-waste">Flooring Waste (%)</label>
        <input type="number" step="1" min="0" max="100" id="ra-floor-waste" class="admin-input" value="10">
      </div>
      <div class="admin-form-group openplan3d-setting-item">
        <label for="ra-floor-box">Box Size (sf)</label>
        <input type="number" step="0.5" min="1" max="200" id="ra-floor-box" class="admin-input" value="20">
      </div>
      <div class="openplan3d-setting-item openplan3d-setting-item--close">
        <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="openplan3d-settings-close">
          <i class="fa fa-times" aria-hidden="true"></i> Close
        </button>
      </div>
    </div>
  </div>

  {{-- Live Metric Cards Ribbon --}}
  <div class="openplan3d-metrics-ribbon" id="openplan3d-metrics-ribbon">
    <div class="openplan3d-metric-card">
      <span class="openplan3d-metric-label">Floor Area</span>
      <span class="openplan3d-metric-val" id="metric-area">0.00</span>
      <span class="openplan3d-metric-unit">sq ft</span>
    </div>
    <div class="openplan3d-metric-card">
      <span class="openplan3d-metric-label">Perimeter</span>
      <span class="openplan3d-metric-val" id="metric-perimeter">0.00</span>
      <span class="openplan3d-metric-unit">lf</span>
    </div>
    <div class="openplan3d-metric-card">
      <span class="openplan3d-metric-label">Baseboard</span>
      <span class="openplan3d-metric-val" id="metric-baseboard">0.00</span>
      <span class="openplan3d-metric-unit">lf</span>
    </div>
    <div class="openplan3d-metric-card">
      <span class="openplan3d-metric-label">Net Wall Area</span>
      <span class="openplan3d-metric-val" id="metric-wall-area">0.00</span>
      <span class="openplan3d-metric-unit">sq ft</span>
    </div>
    <div class="openplan3d-metric-card">
      <span class="openplan3d-metric-label">Openings</span>
      <span class="openplan3d-metric-val" id="metric-openings">0 doors · 0 windows</span>
    </div>
    <div class="openplan3d-metric-card">
      <span class="openplan3d-metric-label">Casing</span>
      <span class="openplan3d-metric-val" id="metric-casing">0.00</span>
      <span class="openplan3d-metric-unit">lf</span>
    </div>
    <div class="openplan3d-metric-card">
      <span class="openplan3d-metric-label">Boxes</span>
      <span class="openplan3d-metric-val" id="metric-boxes">0</span>
      <span class="openplan3d-metric-unit">boxes</span>
    </div>
  </div>

  {{-- Feedback / Toast Message Box --}}
  <div id="openplan3d-feedback" class="openplan3d-feedback" hidden role="alert" aria-live="polite"></div>

  <div id="openplan3d-noscale-warning" class="openplan3d-noscale-warning" hidden role="status">
    <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
    <span>This room has no scale. Measurements may be wrong until scale is set.</span>
    @include('admin.partials.info_tip', [
      'tipLabel' => 'Room scale',
      'tipText' => 'Scale converts the drawing to real-world feet. openPlan3D plans that include a unit (cm, ft, in) already have scale. Set scale before trusting measurements or quotes.',
    ])
  </div>

  <div id="openplan3d-modal" class="openplan3d-modal" hidden>
    <div class="openplan3d-modal-backdrop" data-openplan3d-modal-dismiss tabindex="-1" aria-hidden="true"></div>
    <div class="openplan3d-modal-panel" role="dialog" aria-modal="true" aria-labelledby="openplan3d-modal-title">
      <h2 id="openplan3d-modal-title" class="openplan3d-modal-title">Confirm</h2>
      <p id="openplan3d-modal-body" class="openplan3d-modal-body"></p>
      <label id="openplan3d-modal-input-wrap" class="openplan3d-modal-field" hidden>
        <span class="openplan3d-modal-field-label">Name</span>
        <input type="text" id="openplan3d-modal-input" class="admin-input" maxlength="120">
      </label>
      <div id="openplan3d-modal-link" class="openplan3d-modal-link" hidden>
        <div class="openplan3d-modal-link-head">
          <span class="openplan3d-modal-field-label">Client project</span>
          @include('admin.partials.info_tip', [
            'tipLabel' => 'Link or standalone',
            'tipText' => 'Keep standalone stores the room under Utilities. Link to a project attaches it to a client job so the crew can open it from that project.',
          ])
        </div>
        <label class="openplan3d-modal-field" for="openplan3d-modal-client-project">
          <select id="openplan3d-modal-client-project" class="admin-input" aria-label="Client project">
            <option value="">— Select a client project —</option>
          </select>
        </label>
        <div class="openplan3d-modal-actions openplan3d-modal-actions--stack">
          <button type="button" class="admin-btn admin-btn--primary" id="openplan3d-modal-link-btn">Link to a project</button>
          <button type="button" class="admin-btn admin-btn--ghost" id="openplan3d-modal-standalone-btn">Keep standalone</button>
          <button type="button" class="admin-btn admin-btn--ghost" id="openplan3d-modal-link-cancel">Cancel</button>
        </div>
      </div>
      <div id="openplan3d-modal-actions" class="openplan3d-modal-actions">
        <button type="button" class="admin-btn admin-btn--ghost" id="openplan3d-modal-cancel">Cancel</button>
        <button type="button" class="admin-btn admin-btn--primary" id="openplan3d-modal-confirm">OK</button>
      </div>
    </div>
  </div>

  {{-- Iframe Container --}}
  <main class="util-ra-openplan3d-frame-container" id="openplan3d-container">
    <iframe
      id="openplan3d-iframe"
      src="{{ asset('vendor/openplan3d/editor/index.html') }}?v={{ @filemtime(public_path('vendor/openplan3d/editor/index.html')) ?: time() }}"
      title="openPlan3D Floor Plan Editor"
      class="util-ra-openplan3d-iframe"
      allow="fullscreen"
      loading="lazy"
    ></iframe>
  </main>
</div>

<style>
.util-ra-openplan3d-wrap {
  display: flex;
  flex-direction: column;
  width: 100vw;
  max-width: 100vw;
  height: 100vh;
  height: 100dvh;
  min-height: 0;
  background: var(--admin-surface, #ffffff);
  border: none;
  border-radius: 0;
  overflow: hidden;
  position: relative;
}

.util-ra-openplan3d-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.5rem 1rem;
  background: var(--admin-surface, #f8fafc);
  border-bottom: 1px solid var(--admin-border, #e2e8f0);
  flex-wrap: wrap;
  min-height: 58px;
}

.util-ra-openplan3d-header__primary {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.util-ra-openplan3d-back {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  min-height: 40px;
  min-width: 40px;
  padding: 0.4rem 0.75rem;
  text-decoration: none;
}

.util-ra-openplan3d-titles {
  display: flex;
  flex-direction: column;
}

.util-ra-openplan3d-title-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.util-ra-openplan3d-title {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--admin-text, #1e293b);
}

.util-ra-badge--openplan3d {
  display: inline-block;
  padding: 0.15rem 0.5rem;
  font-size: 0.75rem;
  font-weight: 600;
  border-radius: 4px;
  background: rgba(2, 71, 104, 0.12);
  color: var(--admin-primary, #024768);
  border: 1px solid rgba(2, 71, 104, 0.25);
}

.openplan3d-dirty-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.75rem;
  font-weight: 600;
  color: #b45309;
  background: #fef3c7;
  padding: 0.15rem 0.5rem;
  border-radius: 4px;
  border: 1px solid #fde68a;
}

.openplan3d-dirty-badge[hidden] {
  display: none !important;
}

.openplan3d-dirty-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background-color: #d97706;
}

.util-ra-openplan3d-sub {
  margin: 0;
  font-size: 0.8rem;
  color: var(--admin-text-muted, #64748b);
}

.util-ra-openplan3d-header__selectors {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex: 1;
  max-width: 650px;
}

.openplan3d-control-group {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  flex: 1;
}

.openplan3d-control-group--name {
  flex: 1.2;
}

.openplan3d-control-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--admin-text-muted, #64748b);
  white-space: nowrap;
}

.openplan3d-select,
.openplan3d-input {
  min-height: 38px;
  padding: 0.35rem 0.6rem;
  font-size: 0.88rem;
  border-radius: 6px;
  border: 1px solid var(--admin-border, #cbd5e1);
  background: var(--admin-input-bg, #ffffff);
  color: var(--admin-text, #1e293b);
  width: 100%;
}

.util-ra-openplan3d-header__actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.openplan3d-tool-btn,
.util-ra-engine-toggle,
.openplan3d-save-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  min-height: 44px;
  padding: 0.5rem 0.85rem;
  font-size: 0.88rem;
  white-space: nowrap;
  border-radius: 6px;
  text-decoration: none;
  cursor: pointer;
}

.openplan3d-save-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Settings Drawer */
.openplan3d-settings-drawer {
  background: var(--admin-card-bg, #ffffff);
  border-bottom: 1px solid var(--admin-border, #e2e8f0);
  padding: 0.6rem 1rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

.openplan3d-settings-grid {
  display: flex;
  align-items: flex-end;
  gap: 1rem;
  flex-wrap: wrap;
}

.openplan3d-setting-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 120px;
  margin-bottom: 0 !important;
}

.openplan3d-setting-item label {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--admin-text-muted, #64748b);
}

.openplan3d-setting-item input {
  min-height: 34px;
  padding: 0.3rem 0.5rem;
  font-size: 0.85rem;
}

.openplan3d-setting-item--close {
  min-width: auto;
  align-self: center;
  margin-top: auto;
}

/* Live Metrics Ribbon */
.openplan3d-metrics-ribbon {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.4rem 1rem;
  background: #f1f5f9;
  border-bottom: 1px solid var(--admin-border, #e2e8f0);
  overflow-x: auto;
  scrollbar-width: thin;
}

.openplan3d-metric-card {
  display: inline-flex;
  align-items: baseline;
  gap: 0.35rem;
  padding: 0.25rem 0.65rem;
  background: var(--admin-card-bg, #ffffff);
  border: 1px solid var(--admin-border, #cbd5e1);
  border-radius: 6px;
  white-space: nowrap;
  font-size: 0.8rem;
}

.openplan3d-metric-label {
  color: var(--admin-text-muted, #64748b);
  font-weight: 500;
}

.openplan3d-metric-val {
  font-weight: 700;
  color: var(--admin-text, #1e293b);
  font-variant-numeric: tabular-nums;
}

.openplan3d-metric-unit {
  font-size: 0.72rem;
  color: var(--admin-text-muted, #94a3b8);
}

/* Feedback message */
.openplan3d-feedback {
  padding: 0.5rem 1rem;
  font-size: 0.85rem;
  border-bottom: 1px solid transparent;
}

.openplan3d-feedback--success {
  background: #ecfdf5;
  color: #065f46;
  border-color: #a7f3d0;
}

.openplan3d-feedback--error {
  background: #fef2f2;
  color: #991b1b;
  border-color: #fecaca;
}

.openplan3d-noscale-warning {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.45rem 1rem;
  font-size: 0.85rem;
  font-weight: 600;
  color: #92400e;
  background: #fffbeb;
  border-bottom: 1px solid #fcd34d;
}

.openplan3d-noscale-warning[hidden] {
  display: none !important;
}

.openplan3d-modal {
  position: fixed;
  inset: 0;
  z-index: 80;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  padding: 0.75rem;
  box-sizing: border-box;
}

.openplan3d-modal[hidden],
.openplan3d-modal [hidden] {
  display: none !important;
}

.openplan3d-modal-backdrop {
  position: absolute;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
}

.openplan3d-modal-panel {
  position: relative;
  z-index: 1;
  width: min(420px, 100%);
  max-height: min(90dvh, 640px);
  overflow: auto;
  background: var(--admin-surface, #ffffff);
  color: var(--admin-text, #1e293b);
  border: 1px solid var(--admin-border, #e2e8f0);
  border-radius: 10px;
  box-shadow: 0 16px 40px rgba(15, 23, 42, 0.2);
  padding: 1rem;
}

.openplan3d-modal-title {
  margin: 0 0 0.4rem;
  font-size: 1.05rem;
  color: var(--admin-heading, #0f172a);
}

.openplan3d-modal-body {
  margin: 0 0 0.75rem;
  font-size: 0.9rem;
  color: var(--admin-muted, #64748b);
  line-height: 1.45;
}

.openplan3d-modal-link-head {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  margin-bottom: 0.35rem;
}

.openplan3d-modal-field {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  margin-bottom: 0.75rem;
}

.openplan3d-modal-field-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--admin-muted, #64748b);
}

.openplan3d-modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.openplan3d-modal-actions--stack {
  justify-content: stretch;
  margin-bottom: 0.5rem;
}

.openplan3d-modal-actions .admin-btn {
  min-height: 44px;
}

.openplan3d-modal-actions--stack .admin-btn {
  flex: 1 1 160px;
}

@media (min-width: 700px) {
  .openplan3d-modal {
    align-items: center;
  }
}

@media (max-width: 390px) {
  .openplan3d-modal {
    padding: 0.5rem;
    align-items: flex-end;
  }
  .openplan3d-modal-panel {
    width: 100%;
  }
}
/* Frame container */
.util-ra-openplan3d-frame-container {
  flex: 1;
  width: 100%;
  height: 100%;
  position: relative;
  background: var(--admin-canvas-bg, #0f172a);
}

.util-ra-openplan3d-iframe {
  width: 100%;
  height: 100%;
  border: 0;
  display: block;
}

/* Responsive styles */
@media (max-width: 1024px) {
  .util-ra-openplan3d-header {
    gap: 0.5rem;
  }
  .util-ra-openplan3d-header__selectors {
    order: 3;
    flex-basis: 100%;
    max-width: 100%;
    margin-top: 0.25rem;
  }
}

@media (max-width: 768px) {
  .util-ra-openplan3d-wrap {
    height: 100vh;
    height: 100dvh;
    border-radius: 0;
    border: none;
  }

  .util-ra-openplan3d-header {
    padding: 0.5rem 0.75rem;
  }

  .openplan3d-btn-label {
    display: none;
  }

  .openplan3d-tool-btn,
  .openplan3d-save-btn,
  .util-ra-engine-toggle {
    min-height: 44px;
    padding: 0.5rem 0.75rem;
  }

  .openplan3d-settings-grid {
    gap: 0.5rem;
  }
}

@media (max-width: 390px) {
  .openplan3d-control-label {
    display: none;
  }

  .openplan3d-noscale-warning {
    padding: 0.4rem 0.75rem;
    font-size: 0.8rem;
    flex-wrap: wrap;
  }
}

/* Dark mode overrides (html.admin-dark, body.admin-dark) */
html.admin-dark .util-ra-openplan3d-wrap,
body.admin-dark .util-ra-openplan3d-wrap,
.admin-dark .util-ra-openplan3d-wrap {
  background: #0f172a;
  color: #e2e8f0;
}

html.admin-dark .util-ra-openplan3d-header,
body.admin-dark .util-ra-openplan3d-header,
.admin-dark .util-ra-openplan3d-header {
  background: #0f172a;
  border-bottom: 1px solid #1e293b;
}

html.admin-dark .util-ra-openplan3d-title,
body.admin-dark .util-ra-openplan3d-title,
.admin-dark .util-ra-openplan3d-title {
  color: #f8fafc;
}

html.admin-dark .util-ra-openplan3d-sub,
body.admin-dark .util-ra-openplan3d-sub,
.admin-dark .util-ra-openplan3d-sub {
  color: #94a3b8;
}

html.admin-dark .util-ra-badge--openplan3d,
body.admin-dark .util-ra-badge--openplan3d,
.admin-dark .util-ra-badge--openplan3d {
  background: rgba(56, 189, 248, 0.14);
  color: #38bdf8;
  border: 1px solid rgba(56, 189, 248, 0.3);
}

html.admin-dark .openplan3d-dirty-badge,
body.admin-dark .openplan3d-dirty-badge,
.admin-dark .openplan3d-dirty-badge {
  background: rgba(245, 158, 11, 0.16);
  color: #fbbf24;
  border: 1px solid rgba(245, 158, 11, 0.35);
}

html.admin-dark .openplan3d-dirty-badge[hidden],
body.admin-dark .openplan3d-dirty-badge[hidden],
.admin-dark .openplan3d-dirty-badge[hidden] {
  display: none !important;
}

html.admin-dark .openplan3d-dirty-dot,
body.admin-dark .openplan3d-dirty-dot,
.admin-dark .openplan3d-dirty-dot {
  background-color: #f59e0b;
}

html.admin-dark .openplan3d-control-label,
body.admin-dark .openplan3d-control-label,
.admin-dark .openplan3d-control-label {
  color: #94a3b8;
}

html.admin-dark .openplan3d-select,
html.admin-dark .openplan3d-input,
body.admin-dark .openplan3d-select,
body.admin-dark .openplan3d-input,
.admin-dark .openplan3d-select,
.admin-dark .openplan3d-input {
  background: #090e17;
  border: 1px solid #334155;
  color: #f8fafc;
}

html.admin-dark .openplan3d-select:focus,
html.admin-dark .openplan3d-input:focus,
body.admin-dark .openplan3d-select:focus,
body.admin-dark .openplan3d-input:focus,
.admin-dark .openplan3d-select:focus,
.admin-dark .openplan3d-input:focus {
  border-color: #38bdf8;
  box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.25);
  outline: none;
}

html.admin-dark .util-ra-openplan3d-header .admin-btn--ghost,
body.admin-dark .util-ra-openplan3d-header .admin-btn--ghost,
.admin-dark .util-ra-openplan3d-header .admin-btn--ghost,
html.admin-dark .util-ra-openplan3d-header .openplan3d-tool-btn,
body.admin-dark .util-ra-openplan3d-header .openplan3d-tool-btn,
.admin-dark .util-ra-openplan3d-header .openplan3d-tool-btn,
html.admin-dark .openplan3d-settings-drawer .admin-btn--ghost,
body.admin-dark .openplan3d-settings-drawer .admin-btn--ghost,
.admin-dark .openplan3d-settings-drawer .admin-btn--ghost {
  background: #1e293b;
  border: 1px solid #334155;
  color: #e2e8f0;
}

html.admin-dark .util-ra-openplan3d-header .admin-btn--ghost:hover,
body.admin-dark .util-ra-openplan3d-header .admin-btn--ghost:hover,
.admin-dark .util-ra-openplan3d-header .admin-btn--ghost:hover,
html.admin-dark .util-ra-openplan3d-header .openplan3d-tool-btn:hover,
body.admin-dark .util-ra-openplan3d-header .openplan3d-tool-btn:hover,
.admin-dark .util-ra-openplan3d-header .openplan3d-tool-btn:hover,
html.admin-dark .openplan3d-settings-drawer .admin-btn--ghost:hover,
body.admin-dark .openplan3d-settings-drawer .admin-btn--ghost:hover,
.admin-dark .openplan3d-settings-drawer .admin-btn--ghost:hover {
  background: #334155;
  border-color: #475569;
  color: #ffffff;
}

html.admin-dark .openplan3d-save-btn,
body.admin-dark .openplan3d-save-btn,
.admin-dark .openplan3d-save-btn {
  background: #0284c7;
  border: 1px solid #38bdf8;
  color: #ffffff;
}

html.admin-dark .openplan3d-save-btn:hover:not(:disabled),
body.admin-dark .openplan3d-save-btn:hover:not(:disabled),
.admin-dark .openplan3d-save-btn:hover:not(:disabled) {
  background: #0369a1;
  border-color: #7dd3fc;
  color: #ffffff;
}

html.admin-dark .openplan3d-save-btn:disabled,
body.admin-dark .openplan3d-save-btn:disabled,
.admin-dark .openplan3d-save-btn:disabled {
  background: #1e293b;
  border-color: #334155;
  color: #64748b;
  opacity: 0.55;
}

html.admin-dark .openplan3d-settings-drawer,
body.admin-dark .openplan3d-settings-drawer,
.admin-dark .openplan3d-settings-drawer {
  background: #111a2c;
  border-bottom: 1px solid #1e293b;
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.4);
}

html.admin-dark .openplan3d-setting-item label,
body.admin-dark .openplan3d-setting-item label,
.admin-dark .openplan3d-setting-item label {
  color: #94a3b8;
}

html.admin-dark .openplan3d-setting-item input,
body.admin-dark .openplan3d-setting-item input,
.admin-dark .openplan3d-setting-item input {
  background: #090e17;
  border: 1px solid #334155;
  color: #f8fafc;
}

html.admin-dark .openplan3d-metrics-ribbon,
body.admin-dark .openplan3d-metrics-ribbon,
.admin-dark .openplan3d-metrics-ribbon {
  background: #070b12;
  border-bottom: 1px solid #1e293b;
}

html.admin-dark .openplan3d-metric-card,
body.admin-dark .openplan3d-metric-card,
.admin-dark .openplan3d-metric-card {
  background: #131c2e;
  border: 1px solid #243247;
}

html.admin-dark .openplan3d-metric-label,
body.admin-dark .openplan3d-metric-label,
.admin-dark .openplan3d-metric-label {
  color: #94a3b8;
}

html.admin-dark .openplan3d-metric-val,
body.admin-dark .openplan3d-metric-val,
.admin-dark .openplan3d-metric-val {
  color: #38bdf8;
}

html.admin-dark .openplan3d-metric-unit,
body.admin-dark .openplan3d-metric-unit,
.admin-dark .openplan3d-metric-unit {
  color: #64748b;
}

html.admin-dark .openplan3d-feedback--success,
body.admin-dark .openplan3d-feedback--success,
.admin-dark .openplan3d-feedback--success {
  background: rgba(74, 222, 128, 0.14);
  color: #4ade80;
  border-color: rgba(74, 222, 128, 0.3);
}

html.admin-dark .openplan3d-feedback--error,
body.admin-dark .openplan3d-feedback--error,
.admin-dark .openplan3d-feedback--error {
  background: rgba(248, 113, 113, 0.14);
  color: #f87171;
  border-color: rgba(248, 113, 113, 0.3);
}

html.admin-dark .openplan3d-noscale-warning,
body.admin-dark .openplan3d-noscale-warning,
.admin-dark .openplan3d-noscale-warning {
  background: rgba(245, 158, 11, 0.16);
  color: #fbbf24;
  border-bottom-color: rgba(245, 158, 11, 0.35);
}

html.admin-dark .openplan3d-noscale-warning[hidden],
body.admin-dark .openplan3d-noscale-warning[hidden],
.admin-dark .openplan3d-noscale-warning[hidden] {
  display: none !important;
}

html.admin-dark .openplan3d-modal-backdrop,
body.admin-dark .openplan3d-modal-backdrop,
.admin-dark .openplan3d-modal-backdrop {
  background: rgba(2, 6, 23, 0.72);
}

html.admin-dark .openplan3d-modal-panel,
body.admin-dark .openplan3d-modal-panel,
.admin-dark .openplan3d-modal-panel {
  background: var(--admin-surface, #111a2c);
  color: var(--admin-text, #e2e8f0);
  border-color: var(--admin-border, #334155);
}

html.admin-dark .openplan3d-modal-title,
body.admin-dark .openplan3d-modal-title,
.admin-dark .openplan3d-modal-title {
  color: var(--admin-heading, #f8fafc);
}

html.admin-dark .openplan3d-modal-body,
body.admin-dark .openplan3d-modal-body,
.admin-dark .openplan3d-modal-body,
html.admin-dark .openplan3d-modal-field-label,
body.admin-dark .openplan3d-modal-field-label,
.admin-dark .openplan3d-modal-field-label {
  color: var(--admin-muted, #94a3b8);
}

html.admin-dark .openplan3d-modal[hidden],
body.admin-dark .openplan3d-modal[hidden],
.admin-dark .openplan3d-modal[hidden],
html.admin-dark .openplan3d-modal [hidden],
body.admin-dark .openplan3d-modal [hidden],
.admin-dark .openplan3d-modal [hidden] {
  display: none !important;
}
</style>
