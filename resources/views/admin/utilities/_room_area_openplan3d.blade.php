@php
  $pageTitle = $pageTitle ?? 'Room Area';
  $backUrl = $backUrl ?? route('admin.utilities.index');
  $backLabel = $backLabel ?? 'All utilities';
  $clientProjectName = $clientProject->name ?? null;
  $initialRoomId = $initialRoomId ?? request()->integer('ra_room') ?: null;
  $initialProjectId = request()->integer('ra_project') ?: null;
  $guideQuery = array_filter([
    'ra_project' => $initialProjectId,
    'ra_room' => $initialRoomId,
  ]);
  $guideUrl = isset($clientProject)
    ? route('admin.client-projects.utilities.room-area.guide', array_filter([
        'client_project' => $clientProject,
        'room_area_room' => $initialRoomId,
      ]))
    : route('admin.utilities.room-area.guide', $guideQuery);
@endphp

<div class="util-ra-openplan3d-wrap util-ra--fullpage"
  data-room-area-openplan3d
  data-projects="{{ json_encode($projectsJson ?? []) }}"
  data-client-projects="{{ json_encode(collect($clientProjects ?? [])->map(fn ($p) => ['id' => $p->id, 'name' => method_exists($p, 'linkSelectLabel') ? $p->linkSelectLabel() : ($p->name ?? 'Project #'.$p->id)])) }}"
  data-initial-room-id="{{ $initialRoomId }}"
  data-initial-project-id="{{ $initialProjectId }}"
  data-room-store-template="{{ route('admin.utilities.room-area.rooms.store', ['room_area_project' => '__ID__']) }}"
  data-room-update-template="{{ route('admin.utilities.room-area.rooms.update', ['room_area_room' => '__ID__']) }}"
  data-room-destroy-template="{{ route('admin.utilities.room-area.rooms.destroy', ['room_area_room' => '__ID__']) }}"
  data-project-store-url="{{ route('admin.utilities.room-area.projects.store') }}"
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

      <a href="{{ $guideUrl }}" class="admin-btn admin-btn--ghost openplan3d-tool-btn util-ra-full-header__help" data-ra-help-guide aria-label="Open Room Area guide" title="Open Room Area walkthrough and help guide">
        <i class="fa fa-question-circle" aria-hidden="true"></i>
        <span class="openplan3d-btn-label">Guide</span>
      </a>

      <button type="button" class="admin-btn admin-btn--ghost openplan3d-tool-btn" id="openplan3d-theme-toggle" aria-label="Toggle dark/light theme" title="Toggle dark/light theme">
        <i class="fa fa-moon-o" data-theme-icon="moon" aria-hidden="true"></i>
        <i class="fa fa-sun-o" data-theme-icon="sun" aria-hidden="true" hidden></i>
      </button>

      <a href="#" class="admin-btn admin-btn--ghost openplan3d-tool-btn" id="openplan3d-quote-btn" hidden title="Create or add lines to a quotation">
        <i class="fa fa-file-text-o" aria-hidden="true"></i>
        <span class="openplan3d-btn-label">Quote</span>
      </a>

      <button type="button" class="admin-btn admin-btn--primary openplan3d-save-btn" id="openplan3d-save-btn" data-hotkey-save disabled title="Save Plan (Ctrl+S)">
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

  {{-- Iframe Container --}}
  <main class="util-ra-openplan3d-frame-container" id="openplan3d-container">
    <iframe
      id="openplan3d-iframe"
      src="{{ asset('vendor/openplan3d/editor/index.html') }}"
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
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var rootEl = document.querySelector('[data-room-area-openplan3d]');
  if (!rootEl) return;

  var iframe = document.getElementById('openplan3d-iframe');
  var projectSelect = document.getElementById('openplan3d-project-select');
  var roomSelect = document.getElementById('openplan3d-room-select');
  var roomNameInput = document.getElementById('openplan3d-room-name');
  var saveBtn = document.getElementById('openplan3d-save-btn');
  var newRoomBtn = document.getElementById('openplan3d-new-room-btn');
  var settingsBtn = document.getElementById('openplan3d-settings-btn');
  var settingsDrawer = document.getElementById('openplan3d-settings-drawer');
  var settingsClose = document.getElementById('openplan3d-settings-close');
  var dirtyBadge = document.getElementById('openplan3d-dirty-badge');
  var feedbackEl = document.getElementById('openplan3d-feedback');

  var wallHeightInput = document.getElementById('ra-wall-height');
  var ceilingHeightInput = document.getElementById('ra-ceiling-height');
  var wasteInput = document.getElementById('ra-floor-waste');
  var boxInput = document.getElementById('ra-floor-box');

  var metricArea = document.getElementById('metric-area');
  var metricPerimeter = document.getElementById('metric-perimeter');
  var metricBaseboard = document.getElementById('metric-baseboard');
  var metricWallArea = document.getElementById('metric-wall-area');
  var metricOpenings = document.getElementById('metric-openings');
  var metricCasing = document.getElementById('metric-casing');
  var metricBoxes = document.getElementById('metric-boxes');

  var csrfToken = (document.getElementById('openplan3d-csrf') || {}).value || '';
  var projects = [];
  try {
    projects = JSON.parse(rootEl.dataset.projects || '[]');
  } catch (err) {
    projects = [];
  }

  var initialRoomId = parseInt(rootEl.dataset.initialRoomId, 10) || null;
  var initialProjectId = parseInt(rootEl.dataset.initialProjectId, 10) || null;

  var roomStoreTemplate = rootEl.dataset.roomStoreTemplate || '';
  var roomUpdateTemplate = rootEl.dataset.roomUpdateTemplate || '';
  var projectStoreUrl = rootEl.dataset.projectStoreUrl || '';
  var quoteRoomTemplate = rootEl.dataset.quoteRoomTemplate || '';

  var quoteBtn = document.getElementById('openplan3d-quote-btn');
  var currentProject = null;
  var currentRoom = null;
  var currentPlan = null;
  var isDirty = false;
  var isSaving = false;
  var iframeReady = false;

  function setDirty(val) {
    isDirty = !!val;
    if (dirtyBadge) {
      dirtyBadge.hidden = !isDirty;
    }
  }

  function showFeedback(msg, isError) {
    if (!feedbackEl) return;
    feedbackEl.textContent = msg;
    feedbackEl.className = 'openplan3d-feedback ' + (isError ? 'openplan3d-feedback--error' : 'openplan3d-feedback--success');
    feedbackEl.hidden = false;
    if (!isError) {
      setTimeout(function () {
        feedbackEl.hidden = true;
      }, 5000);
    }
  }

  function getFinishSettings() {
    return {
      wall_height_ft: parseFloat(wallHeightInput ? wallHeightInput.value : 8) || 8,
      ceiling_height_ft: parseFloat(ceilingHeightInput ? ceilingHeightInput.value : 8) || 8,
      flooring_waste_percent: parseFloat(wasteInput ? wasteInput.value : 10) || 10,
      flooring_box_sqft: parseFloat(boxInput ? boxInput.value : 20) || 20,
    };
  }

  function updateMetrics(plan) {
    if (!plan || !window.OpenPlan3DMeasurementBridge) {
      metricArea.textContent = '0.00';
      metricPerimeter.textContent = '0.00';
      metricBaseboard.textContent = '0.00';
      metricWallArea.textContent = '0.00';
      metricOpenings.textContent = '0 doors · 0 windows';
      metricCasing.textContent = '0.00';
      metricBoxes.textContent = '0';
      return;
    }

    try {
      var bridge = window.OpenPlan3DMeasurementBridge;
      var metrics = bridge.layoutMetrics(plan, null, getFinishSettings());
      metricArea.textContent = (metrics.area_sqft || 0).toFixed(2);
      metricPerimeter.textContent = (metrics.perimeter_ft || 0).toFixed(2);
      metricBaseboard.textContent = (metrics.baseboard_ft || 0).toFixed(2);
      metricWallArea.textContent = (metrics.net_wall_area_sqft || metrics.net_wall_sqft || metrics.wall_area_sqft || 0).toFixed(2);
      metricOpenings.textContent = (metrics.door_count || 0) + ' doors · ' + (metrics.window_count || 0) + ' windows';
      metricCasing.textContent = (metrics.casing_lf || metrics.casing_ft || 0).toFixed(2);
      metricBoxes.textContent = String(metrics.flooring_boxes || metrics.boxes || 0);
    } catch (err) {
      console.warn('[openPlan3D] Metrics computation failed:', err);
    }
  }

  function populateProjects() {
    projectSelect.innerHTML = '';
    var defaultOpt = document.createElement('option');
    defaultOpt.value = '';
    defaultOpt.textContent = '— Select Project —';
    projectSelect.appendChild(defaultOpt);

    projects.forEach(function (proj) {
      var opt = document.createElement('option');
      opt.value = proj.id;
      opt.textContent = proj.name + (proj.client_project_name ? ' (' + proj.client_project_name + ')' : '');
      projectSelect.appendChild(opt);
    });

    var newOpt = document.createElement('option');
    newOpt.value = '__new__';
    newOpt.textContent = '+ Create New Project...';
    projectSelect.appendChild(newOpt);
  }

  function populateRooms() {
    roomSelect.innerHTML = '';
    var defaultOpt = document.createElement('option');
    defaultOpt.value = '';
    defaultOpt.textContent = '— Select Room —';
    roomSelect.appendChild(defaultOpt);

    if (!currentProject || !currentProject.rooms) {
      return;
    }

    currentProject.rooms.forEach(function (room) {
      var opt = document.createElement('option');
      opt.value = room.id;
      opt.textContent = room.name + (room.area_sqft ? ' (' + parseFloat(room.area_sqft).toFixed(1) + ' sf)' : '');
      roomSelect.appendChild(opt);
    });

    var newOpt = document.createElement('option');
    newOpt.value = '__new__';
    newOpt.textContent = '+ Create New Room...';
    roomSelect.appendChild(newOpt);
  }

  function selectProject(projectId) {
    var found = null;
    for (var i = 0; i < projects.length; i++) {
      if (projects[i].id === projectId) {
        found = projects[i];
        break;
      }
    }
    currentProject = found;
    projectSelect.value = currentProject ? String(currentProject.id) : '';
    populateRooms();
  }

  function selectRoom(room) {
    currentRoom = room;
    if (currentRoom) {
      roomSelect.value = currentRoom.id ? String(currentRoom.id) : '__new__';
      roomNameInput.value = currentRoom.name || '';
      if (wallHeightInput) wallHeightInput.value = currentRoom.wall_height_ft || 8;
      if (ceilingHeightInput) ceilingHeightInput.value = currentRoom.ceiling_height_ft || 8;
      if (wasteInput) wasteInput.value = currentRoom.flooring_waste_percent || 10;
      if (boxInput) boxInput.value = currentRoom.flooring_box_sqft || 20;

      currentPlan = currentRoom.plan || null;
      updateMetrics(currentPlan);

      if (iframeReady && iframe && iframe.contentWindow) {
        iframe.contentWindow.postMessage({
          type: 'OPENPLAN3D_LOAD_PLAN',
          plan: currentPlan
        }, '*');
      }
      if (quoteBtn) {
        if (currentRoom.id) {
          quoteBtn.href = quoteRoomTemplate.replace('__ID__', currentRoom.id);
          quoteBtn.hidden = false;
        } else {
          quoteBtn.hidden = true;
        }
      }
      setDirty(false);
      saveBtn.disabled = !currentPlan;
    } else {
      roomSelect.value = '';
      roomNameInput.value = '';
      currentPlan = null;
      updateMetrics(null);
      if (quoteBtn) quoteBtn.hidden = true;
      setDirty(false);
      saveBtn.disabled = true;
    }
  }

  function initializeSelection() {
    populateProjects();

    var targetProject = null;
    var targetRoom = null;

    if (initialRoomId) {
      for (var p = 0; p < projects.length; p++) {
        var pr = projects[p];
        if (pr.rooms) {
          for (var r = 0; r < pr.rooms.length; r++) {
            if (pr.rooms[r].id === initialRoomId) {
              targetProject = pr;
              targetRoom = pr.rooms[r];
              break;
            }
          }
        }
        if (targetRoom) break;
      }
    }

    if (!targetProject && initialProjectId) {
      for (var pi = 0; pi < projects.length; pi++) {
        if (projects[pi].id === initialProjectId) {
          targetProject = projects[pi];
          break;
        }
      }
    }

    if (!targetProject && projects.length > 0) {
      targetProject = projects[0];
    }

    if (targetProject) {
      selectProject(targetProject.id);
      if (targetRoom) {
        selectRoom(targetRoom);
      } else if (targetProject.rooms && targetProject.rooms.length > 0) {
        selectRoom(targetProject.rooms[0]);
      } else {
        createNewRoom();
      }
    }
  }

  function createNewRoom() {
    if (!currentProject) {
      alert('Please select or create a project first.');
      return;
    }
    var roomNumber = (currentProject.rooms ? currentProject.rooms.length : 0) + 1;
    var name = prompt('Room name:', 'Room ' + roomNumber);
    if (!name || !name.trim()) return;

    var newRoom = {
      id: null,
      name: name.trim(),
      plan: null,
      wall_height_ft: 8,
      ceiling_height_ft: 8,
      flooring_waste_percent: 10,
      flooring_box_sqft: 20
    };

    selectRoom(newRoom);
    saveBtn.disabled = false;
    setDirty(false);
  }

  function createNewProject() {
    var name = prompt('Project name:');
    if (!name || !name.trim()) return;

    var payload = {
      name: name.trim(),
      _token: csrfToken
    };

    fetch(projectStoreUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify(payload)
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
      if (data.ok && data.project) {
        projects.push(data.project);
        populateProjects();
        selectProject(data.project.id);
        createNewRoom();
        showFeedback('Project created successfully!');
      } else {
        showFeedback(data.message || 'Failed to create project.', true);
      }
    })
    .catch(function (err) {
      showFeedback('Server error creating project.', true);
    });
  }

  function savePlan() {
    if (isSaving) return;
    if (!currentProject) {
      showFeedback('No active project selected.', true);
      return;
    }

    var roomName = (roomNameInput.value || '').trim();
    if (!roomName) {
      showFeedback('Please enter a room name.', true);
      roomNameInput.focus();
      return;
    }

    if (!currentPlan) {
      showFeedback('No floor plan data available to save.', true);
      return;
    }

    isSaving = true;
    saveBtn.disabled = true;
    var saveSpinner = saveBtn.querySelector('.openplan3d-save-spinner');
    var saveText = saveBtn.querySelector('.openplan3d-save-text');
    if (saveSpinner) saveSpinner.hidden = false;
    if (saveText) saveText.hidden = true;

    var isUpdate = currentRoom && currentRoom.id;
    var url = isUpdate
      ? roomUpdateTemplate.replace('__ID__', currentRoom.id)
      : roomStoreTemplate.replace('__ID__', currentProject.id);

    var finish = getFinishSettings();
    var submitOnce = 'openplan3d_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

    var payload = {
      name: roomName,
      plan: currentPlan,
      wall_height_ft: finish.wall_height_ft,
      ceiling_height_ft: finish.ceiling_height_ft,
      flooring_waste_percent: finish.flooring_waste_percent,
      flooring_box_sqft: finish.flooring_box_sqft,
      _submit_once: submitOnce,
      _token: csrfToken
    };

    fetch(url, {
      method: isUpdate ? 'PUT' : 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify(payload)
    })
    .then(function (res) {
      return res.json().then(function (data) {
        return { status: res.status, ok: res.ok, data: data };
      });
    })
    .then(function (result) {
      if (result.ok && result.data && result.data.ok) {
        var updatedRoom = result.data.room;
        var updatedProject = result.data.project;

        currentRoom = updatedRoom;
        if (updatedProject) {
          for (var i = 0; i < projects.length; i++) {
            if (projects[i].id === updatedProject.id) {
              projects[i] = updatedProject;
              currentProject = updatedProject;
              break;
            }
          }
        }

        populateRooms();
        roomSelect.value = String(currentRoom.id);
        roomNameInput.value = currentRoom.name;
        if (quoteBtn && currentRoom && currentRoom.id) {
          quoteBtn.href = quoteRoomTemplate.replace('__ID__', currentRoom.id);
          quoteBtn.hidden = false;
        }

        setDirty(false);
        showFeedback('Plan saved successfully!');
      } else {
        var errMsg = (result.data && (result.data.message || (result.data.errors ? Object.values(result.data.errors).flat().join(' ') : null))) || 'Save failed.';
        showFeedback(errMsg, true);
      }
    })
    .catch(function (err) {
      showFeedback('An unexpected error occurred while saving the plan.', true);
    })
    .finally(function () {
      isSaving = false;
      saveBtn.disabled = false;
      if (saveSpinner) saveSpinner.hidden = true;
      if (saveText) saveText.hidden = false;
    });
  }

  // Event Listeners
  projectSelect.addEventListener('change', function () {
    var val = projectSelect.value;
    if (val === '__new__') {
      createNewProject();
      return;
    }
    if (isDirty) {
      if (!window.confirm('You have unsaved changes. Discard changes and switch project?')) {
        projectSelect.value = currentProject ? String(currentProject.id) : '';
        return;
      }
    }
    selectProject(parseInt(val, 10));
    if (currentProject && currentProject.rooms && currentProject.rooms.length > 0) {
      selectRoom(currentProject.rooms[0]);
    } else {
      selectRoom(null);
    }
  });

  roomSelect.addEventListener('change', function () {
    var val = roomSelect.value;
    if (val === '__new__') {
      createNewRoom();
      return;
    }
    if (isDirty) {
      if (!window.confirm('You have unsaved changes. Discard changes and switch room?')) {
        roomSelect.value = currentRoom && currentRoom.id ? String(currentRoom.id) : '';
        return;
      }
    }
    var roomId = parseInt(val, 10);
    var found = null;
    if (currentProject && currentProject.rooms) {
      for (var i = 0; i < currentProject.rooms.length; i++) {
        if (currentProject.rooms[i].id === roomId) {
          found = currentProject.rooms[i];
          break;
        }
      }
    }
    selectRoom(found);
  });

  roomNameInput.addEventListener('input', function () {
    setDirty(true);
  });

  newRoomBtn.addEventListener('click', function () {
    if (isDirty) {
      if (!window.confirm('You have unsaved changes. Discard changes and create new room?')) {
        return;
      }
    }
    createNewRoom();
  });

  saveBtn.addEventListener('click', savePlan);

  settingsBtn.addEventListener('click', function () {
    var isExpanded = settingsBtn.getAttribute('aria-expanded') === 'true';
    settingsBtn.setAttribute('aria-expanded', String(!isExpanded));
    settingsDrawer.hidden = isExpanded;
  });

  settingsClose.addEventListener('click', function () {
    settingsBtn.setAttribute('aria-expanded', 'false');
    settingsDrawer.hidden = true;
  });

  [wallHeightInput, ceilingHeightInput, wasteInput, boxInput].forEach(function (el) {
    if (!el) return;
    el.addEventListener('input', function () {
      setDirty(true);
      if (currentPlan) {
        updateMetrics(currentPlan);
      }
    });
  });

  window.addEventListener('beforeunload', function (e) {
    if (isDirty) {
      e.preventDefault();
      e.returnValue = '';
    }
  });

  // PostMessage Bridge with openPlan3D Iframe
  window.addEventListener('message', function (e) {
    if (!e.data || typeof e.data !== 'object') return;

    if (e.data.type === 'OPENPLAN3D_READY') {
      iframeReady = true;
      if (currentPlan && iframe && iframe.contentWindow) {
        iframe.contentWindow.postMessage({
          type: 'OPENPLAN3D_LOAD_PLAN',
          plan: currentPlan
        }, '*');
      }
    } else if (e.data.type === 'OPENPLAN3D_PLAN_CHANGED') {
      currentPlan = e.data.plan;
      setDirty(true);
      saveBtn.disabled = false;
      updateMetrics(currentPlan);
    } else if (e.data.type === 'OPENPLAN3D_TRIGGER_SAVE') {
      if (!saveBtn.disabled && !isSaving) {
        savePlan();
      }
    }
  });

  // Theme toggle button support for full-page mode
  var themeToggleBtn = document.getElementById('openplan3d-theme-toggle');
  if (themeToggleBtn) {
    function syncThemeButton() {
      var isDark = document.documentElement.classList.contains('admin-dark');
      var moon = themeToggleBtn.querySelector('[data-theme-icon="moon"]');
      var sun = themeToggleBtn.querySelector('[data-theme-icon="sun"]');
      if (moon) moon.hidden = isDark;
      if (sun) sun.hidden = !isDark;
      themeToggleBtn.setAttribute('title', isDark ? 'Switch to light theme' : 'Switch to dark theme');
      themeToggleBtn.setAttribute('aria-label', isDark ? 'Switch to light theme' : 'Switch to dark theme');
    }

    syncThemeButton();

    themeToggleBtn.addEventListener('click', function () {
      var wasDark = document.documentElement.classList.contains('admin-dark');
      var nowDark = !wasDark;
      document.documentElement.classList.toggle('admin-dark', nowDark);
      if (document.body) {
        document.body.classList.toggle('admin-dark', nowDark);
      }
      try {
        localStorage.setItem('zanini-admin-theme', nowDark ? 'dark' : 'light');
      } catch (e) {}
      syncThemeButton();
      document.dispatchEvent(new CustomEvent('admin-theme-change', { detail: { dark: nowDark } }));
    });

    document.addEventListener('admin-theme-change', syncThemeButton);
  }

  // Kick off initial setup
  initializeSelection();
});
</script>
