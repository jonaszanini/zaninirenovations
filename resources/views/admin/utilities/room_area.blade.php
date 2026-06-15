@extends('admin.layouts.app')

@section('heading', 'Room Area')

@push('head')
  <link rel="stylesheet" href="{{ asset('css/admin-utilities.css') }}?v={{ @filemtime(public_path('css/admin-utilities.css')) ?: time() }}">
@endpush

@section('content')
  <div class="admin-toolbar">
    <p class="admin-toolbar-lede">
      Draw room outlines on a grid, calibrate scale from a known wall length, and compute square footage.
      Save rooms under a project and optionally link it to a client job.
    </p>
    <a href="{{ route('admin.utilities.index') }}" class="admin-btn admin-btn--ghost">
      <i class="fa fa-arrow-left"></i> All utilities
    </a>
  </div>

  <div
    class="util-ra"
    data-room-area
    data-projects="{{ json_encode($projectsJson) }}"
    data-client-projects="{{ json_encode($clientProjects->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])) }}"
    data-validate-url="{{ route('admin.utilities.room-area.validate') }}"
    data-project-store-url="{{ route('admin.utilities.room-area.projects.store') }}"
    data-project-update-template="{{ route('admin.utilities.room-area.projects.update', ['room_area_project' => '__ID__']) }}"
    data-project-destroy-template="{{ route('admin.utilities.room-area.projects.destroy', ['room_area_project' => '__ID__']) }}"
    data-room-store-template="{{ route('admin.utilities.room-area.rooms.store', ['room_area_project' => '__ID__']) }}"
    data-room-destroy-template="{{ route('admin.utilities.room-area.rooms.destroy', ['room_area_room' => '__ID__']) }}"
    data-background-upload-template="{{ route('admin.utilities.room-area.background.upload', ['room_area_project' => '__ID__']) }}"
    data-background-destroy-template="{{ route('admin.utilities.room-area.background.destroy', ['room_area_project' => '__ID__']) }}">
    <input type="hidden" id="util-ra-csrf" value="{{ csrf_token() }}">

    {{-- Project picker --}}
    <section class="admin-card util-ra-project-bar">
      <div class="util-ra-project-bar__row">
        <div class="admin-form-group util-ra-project-select">
          <label for="ra-project-select">Project</label>
          <select class="admin-input" id="ra-project-select" data-ra-project-select>
            <option value="">— Select a project —</option>
          </select>
        </div>
        <div class="util-ra-project-bar__actions">
          <button type="button" class="admin-btn admin-btn--ghost util-ra-action-btn" data-ra-new-project-toggle>
            <i class="fa fa-plus"></i> New project
          </button>
          <button type="button" class="admin-btn admin-btn--danger util-ra-action-btn" data-ra-delete-project hidden>
            <i class="fa fa-trash"></i> Delete project
          </button>
        </div>
      </div>

      <div class="util-ra-new-project" data-ra-new-project hidden>
        <div class="util-ra-new-project__grid">
          <div class="admin-form-group">
            <label for="ra-new-project-name">Project name</label>
            <input class="admin-input" type="text" id="ra-new-project-name" maxlength="120" placeholder="e.g. Smith basement">
          </div>
          <div class="admin-form-group">
            <label for="ra-new-client-project">Link to client project <span class="util-ra-optional">(optional)</span></label>
            <select class="admin-input" id="ra-new-client-project" data-ra-new-client-project>
              <option value="">— None —</option>
              @foreach ($clientProjects as $cp)
                <option value="{{ $cp->id }}">{{ $cp->name }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <button type="button" class="admin-btn admin-btn--primary" data-ra-create-project>
          <i class="fa fa-check"></i> Create project
        </button>
      </div>

      <div class="util-ra-linked" data-ra-linked hidden>
        <span class="util-ra-linked__label">Linked to:</span>
        <a href="#" data-ra-linked-link class="util-ra-linked__name"></a>
        <div class="admin-form-group util-ra-link-edit">
          <label for="ra-link-client-project" class="util-sr-only">Link to client project</label>
          <select class="admin-input" id="ra-link-client-project" data-ra-link-client-project>
            <option value="">— None —</option>
            @foreach ($clientProjects as $cp)
              <option value="{{ $cp->id }}">{{ $cp->name }}</option>
            @endforeach
          </select>
        </div>
      </div>
    </section>

    <section class="admin-card util-ra-empty" data-ra-empty>
      <span class="util-ra-empty__icon"><i class="fa fa-object-group"></i></span>
      <p class="util-ra-empty__title">Select or create a project</p>
      <p class="util-ra-empty__hint">Choose an existing room area project above, or click <strong>New project</strong> to start drawing rooms.</p>
    </section>

    <div class="util-ra-workspace" data-ra-workspace hidden>
      <div class="util-ra-grid">
        <div class="util-ra-main">
          {{-- Scale & background --}}
          <section class="admin-card util-ra-setup-card">
            <h3 class="util-ra-card-title">Scale &amp; background</h3>
            <div class="util-ra-setup-grid">
              <div class="util-ra-scale-block">
                <p class="util-ra-hint">Click <strong>Calibrate</strong>, then pick two points on a known wall and enter its real length.</p>
                <div class="util-ra-scale-row">
                  <button type="button" class="admin-btn admin-btn--ghost util-ra-action-btn" data-ra-calibrate-toggle>
                    <i class="fa fa-arrows-h"></i> Calibrate
                  </button>
                  <div class="admin-form-group util-ra-scale-input">
                    <label for="ra-known-length">Known length (ft)</label>
                    <input class="admin-input" type="text" id="ra-known-length" inputmode="decimal" placeholder="e.g. 12.5 or 10' 6&quot;" data-ra-known-length disabled>
                  </div>
                  <button type="button" class="admin-btn admin-btn--primary util-ra-action-btn" data-ra-apply-scale disabled>
                    Apply scale
                  </button>
                </div>
                <p class="util-ra-scale-status" data-ra-scale-status>Scale not set — calibrate before saving rooms.</p>
              </div>
              <div class="util-ra-bg-block">
                <label class="util-ra-bg-label">Floor plan image <span class="util-ra-optional">(optional)</span></label>
                <div class="util-ra-bg-actions">
                  <label class="admin-btn admin-btn--ghost util-ra-action-btn util-ra-file-label">
                    <i class="fa fa-upload"></i> Upload image
                    <input type="file" accept="image/jpeg,image/png,image/webp" data-ra-bg-file hidden>
                  </label>
                  <button type="button" class="admin-btn admin-btn--danger util-ra-action-btn" data-ra-bg-remove hidden>
                    <i class="fa fa-times"></i> Remove
                  </button>
                </div>
              </div>
            </div>
          </section>

          {{-- Canvas toolbar --}}
          <section class="admin-card util-ra-canvas-card">
            <div class="util-ra-canvas-head">
              <h3 class="util-ra-card-title">Draw room</h3>
              <div class="util-ra-canvas-tools">
                <span class="util-ra-tool-label">Snap</span>
                <div class="util-segmented util-segmented--compact" data-ra-snap-group>
                  <button type="button" class="util-segmented__btn is-active" data-ra-snap="off">Off</button>
                  <button type="button" class="util-segmented__btn" data-ra-snap="90">90°</button>
                  <button type="button" class="util-segmented__btn" data-ra-snap="45">45°</button>
                </div>
                <button type="button" class="admin-btn admin-btn--ghost util-ra-action-btn" data-ra-undo-vertex disabled>
                  <i class="fa fa-undo"></i> Undo
                </button>
                <button type="button" class="admin-btn admin-btn--ghost util-ra-action-btn" data-ra-close-shape disabled>
                  <i class="fa fa-check-circle"></i> Close shape
                </button>
                <button type="button" class="admin-btn admin-btn--ghost util-ra-action-btn" data-ra-clear-shape>
                  <i class="fa fa-eraser"></i> Clear
                </button>
              </div>
            </div>
            <p class="util-ra-hint">Click each corner. Double-click the first point or use <strong>Close shape</strong> when done (minimum 3 corners).</p>
            <div class="util-ra-canvas-wrap" data-ra-canvas-wrap>
              <div id="ra-konva-container" class="util-ra-konva"></div>
            </div>
            <div class="util-ra-live-area" data-ra-live-area hidden>
              <span class="util-ra-live-area__label">Current shape:</span>
              <strong data-ra-live-sqft>—</strong> sq ft
              <span class="util-ra-live-area__secondary">(<span data-ra-live-sqm>—</span> m²)</span>
            </div>
            <p class="util-ra-feedback" data-ra-feedback hidden></p>
          </section>

          {{-- Save current room --}}
          <section class="admin-card util-ra-save-card">
            <h3 class="util-ra-card-title">Save room</h3>
            <div class="util-ra-save-row">
              <div class="admin-form-group">
                <label for="ra-room-name">Room name</label>
                <input class="admin-input" type="text" id="ra-room-name" maxlength="120" placeholder="e.g. Basement rec room" data-ra-room-name>
              </div>
              <button type="button" class="admin-btn admin-btn--primary util-ra-action-btn" data-ra-save-room disabled>
                <i class="fa fa-save"></i> Save room
              </button>
            </div>
          </section>
        </div>

        <aside class="util-ra-sidebar">
          <section class="admin-card util-ra-rooms-card">
            <h3 class="util-ra-card-title">Saved rooms</h3>
            <p class="util-ra-rooms-empty" data-ra-rooms-empty>No rooms saved yet.</p>
            <ul class="util-ra-rooms-list" data-ra-rooms-list hidden></ul>
            <div class="util-ra-total" data-ra-total hidden>
              <span class="util-ra-total__label">Total area</span>
              <span class="util-ra-total__value"><strong data-ra-total-sqft>0</strong> sq ft</span>
              <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-ra-copy-total>
                <i class="fa fa-copy"></i> Copy
              </button>
            </div>
          </section>
        </aside>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script src="https://unpkg.com/konva@9/konva.min.js"></script>
  <script src="{{ asset('js/admin-utilities-room-area.js') }}?v={{ @filemtime(public_path('js/admin-utilities-room-area.js')) ?: time() }}"></script>
@endpush
