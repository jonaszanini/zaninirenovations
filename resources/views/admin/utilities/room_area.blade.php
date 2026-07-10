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
    data-client-projects="{{ json_encode($clientProjects->map(fn ($p) => ['id' => $p->id, 'name' => $p->linkSelectLabel()])) }}"
    data-validate-url="{{ route('admin.utilities.room-area.validate') }}"
    data-project-store-url="{{ route('admin.utilities.room-area.projects.store') }}"
    data-project-update-template="{{ route('admin.utilities.room-area.projects.update', ['room_area_project' => '__ID__']) }}"
    data-project-destroy-template="{{ route('admin.utilities.room-area.projects.destroy', ['room_area_project' => '__ID__']) }}"
    data-room-store-template="{{ route('admin.utilities.room-area.rooms.store', ['room_area_project' => '__ID__']) }}"
    data-room-update-template="{{ route('admin.utilities.room-area.rooms.update', ['room_area_room' => '__ID__']) }}"
    data-room-destroy-template="{{ route('admin.utilities.room-area.rooms.destroy', ['room_area_room' => '__ID__']) }}"
    data-room-background-upload-template="{{ route('admin.utilities.room-area.rooms.background.upload', ['room_area_room' => '__ID__']) }}"
    data-room-background-destroy-template="{{ route('admin.utilities.room-area.rooms.background.destroy', ['room_area_room' => '__ID__']) }}">
    <input type="hidden" id="util-ra-csrf" value="{{ csrf_token() }}">

    <section class="admin-card util-ra-project-bar">
      <div class="util-ra-project-bar__row">
        <div class="admin-form-group util-ra-project-select">
          <label for="ra-project-select">Project</label>
          <select class="admin-input" id="ra-project-select" data-ra-project-select>
            <option value="">— Select a project —</option>
          </select>
        </div>
        <div class="util-ra-linked" data-ra-linked hidden>
          <div class="admin-form-group util-ra-link-edit">
            <div class="util-ra-link-edit__label-row">
              <label for="ra-link-client-project">Link to client project</label>
              <a href="#" data-ra-linked-link class="util-ra-linked__go" hidden>Go to project</a>
            </div>
            <select class="admin-input" id="ra-link-client-project" data-ra-link-client-project>
              <option value="">— None —</option>
              @foreach ($clientProjects as $cp)
                <option value="{{ $cp->id }}">{{ $cp->linkSelectLabel() }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <div class="util-ra-project-bar__actions">
          <button type="button" class="admin-btn admin-btn--ghost util-ra-action-btn" data-ra-new-project-toggle>
            <i class="fa fa-plus"></i> New project
          </button>
          <button type="button" class="admin-btn admin-btn--ghost util-ra-action-btn util-ra-btn--danger-ghost" data-ra-delete-project hidden>
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
                <option value="{{ $cp->id }}">{{ $cp->linkSelectLabel() }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <button type="button" class="admin-btn admin-btn--primary" data-ra-create-project>
          <i class="fa fa-check"></i> Create project
        </button>
      </div>
    </section>

    <section class="admin-card util-ra-empty" data-ra-empty>
      <span class="util-ra-empty__icon"><i class="fa fa-object-group"></i></span>
      <p class="util-ra-empty__title">Select or create a project</p>
      <p class="util-ra-empty__hint">Choose an existing room area project above, or click <strong>New project</strong> to start drawing rooms. You can save multiple rooms per project.</p>
    </section>

    <div class="util-ra-workspace" data-ra-workspace hidden>
      {{-- Compact setup + save row --}}
      <section class="admin-card util-ra-controls">
        <div class="util-ra-controls__grid">
          <div class="util-ra-controls__block util-ra-controls__scale">
            <span class="util-ra-controls__heading">Scale</span>
            <div class="util-ra-scale-row">
              <button type="button" class="admin-btn admin-btn--ghost util-ra-action-btn" data-ra-calibrate-toggle>
                <i class="fa fa-arrows-h"></i> Calibrate
              </button>
              <div class="admin-form-group util-ra-scale-input">
                <label for="ra-known-length">Known length (ft)</label>
                <input class="admin-input" type="text" id="ra-known-length" inputmode="decimal" placeholder="e.g. 12' 6&quot;, 12.5, or 150&quot;" data-ra-known-length disabled>
              </div>
              <button type="button" class="admin-btn admin-btn--primary util-ra-action-btn" data-ra-apply-scale disabled>
                Apply
              </button>
            </div>
          </div>
          <div class="util-ra-controls__block util-ra-controls__bg">
            <span class="util-ra-controls__heading">Floor plan <span class="util-ra-optional">(per room)</span></span>
            <div class="util-ra-bg-actions">
              <label class="admin-btn admin-btn--ghost util-ra-action-btn util-ra-file-label">
                <i class="fa fa-upload"></i> Upload
                <input type="file" accept="image/jpeg,image/png,image/webp" data-ra-bg-file hidden>
              </label>
              <button type="button" class="admin-btn admin-btn--danger util-ra-action-btn" data-ra-bg-remove hidden>
                <i class="fa fa-trash"></i> Delete image
              </button>
            </div>
          </div>
          <div class="util-ra-controls__block util-ra-controls__save">
            <span class="util-ra-controls__heading" data-ra-save-card-title>Save room</span>
            <div class="util-ra-save-row">
              <div class="admin-form-group">
                <label for="ra-room-name">Room name</label>
                <input class="admin-input" type="text" id="ra-room-name" maxlength="120" placeholder="e.g. Basement rec room" data-ra-room-name>
              </div>
              <button type="button" class="admin-btn admin-btn--primary util-ra-action-btn" data-ra-save-room disabled>
                <i class="fa fa-save"></i> Save room
              </button>
              <button type="button" class="admin-btn admin-btn--ghost util-ra-action-btn" data-ra-cancel-edit hidden>
                Cancel edit
              </button>
              <button type="button" class="admin-btn admin-btn--ghost util-ra-action-btn" data-ra-new-room>
                <i class="fa fa-plus"></i> New room
              </button>
            </div>
          </div>
        </div>
        <p class="util-ra-scale-status" data-ra-scale-status>Scale not set — calibrate before saving rooms.</p>
      </section>

      {{-- Full-width drawing area --}}
      <section class="admin-card util-ra-canvas-card">
        <div class="util-ra-canvas-toolbar">
          <div class="util-ra-toolbar__groups">
            <div class="util-ra-tool-group">
              <span class="util-ra-tool-group__label">Shape</span>
              <div class="util-ra-tool-group__actions">
                <button type="button" class="admin-btn admin-btn--ghost util-ra-action-btn" data-ra-undo-vertex disabled>
                  <i class="fa fa-undo"></i> Undo
                </button>
                <button type="button" class="admin-btn admin-btn--ghost util-ra-action-btn" data-ra-close-shape disabled>
                  <i class="fa fa-check-circle"></i> Close
                </button>
              </div>
            </div>

            <div class="util-ra-tool-group util-ra-tool-group--areas">
              <span class="util-ra-tool-group__label">Areas</span>
              <div class="util-ra-tool-group__actions">
                <div class="util-ra-btn-cluster" role="group" aria-label="Add area options">
                  <button type="button" class="admin-btn admin-btn--ghost util-ra-action-btn" data-ra-add-area disabled title="Add another area in the same color group">
                    <i class="fa fa-plus-square"></i> Add area
                  </button>
                  <button type="button" class="admin-btn admin-btn--ghost util-ra-action-btn util-ra-btn-cluster__secondary" data-ra-add-area-new-group disabled title="Start a new color group">
                    <i class="fa fa-palette"></i> New group
                  </button>
                </div>
                <button type="button" class="admin-btn admin-btn--ghost util-ra-action-btn util-ra-btn--danger-ghost" data-ra-delete-area disabled title="Delete the active area">
                  <i class="fa fa-minus-circle"></i> Delete area
                </button>
                <span class="util-ra-group-chip" data-ra-group-chip hidden>
                  <span class="util-ra-group-chip__swatch" data-ra-group-chip-swatch></span>
                  <span class="util-ra-group-chip__label">Group</span>
                  <strong data-ra-group-chip-total>—</strong>
                </span>
              </div>
            </div>

            <div class="util-ra-tool-group">
              <span class="util-ra-tool-group__label">Tools</span>
              <div class="util-ra-tool-group__actions">
                <button type="button" class="admin-btn admin-btn--ghost util-ra-action-btn" data-ra-clear-shape>
                  <i class="fa fa-eraser"></i> Clear all
                </button>
                <button type="button" class="admin-btn admin-btn--ghost util-ra-action-btn" data-ra-ruler-toggle title="Measure distances on the floor plan">
                  <i class="fa fa-arrows-alt"></i> Ruler
                </button>
              </div>
            </div>

            <div class="util-ra-tool-group util-ra-tool-group--floor-plan">
              <span class="util-ra-tool-group__label">Floor plan</span>
              <div class="util-ra-tool-group__actions">
                <label class="admin-checkbox admin-checkbox--sm util-ra-bg-show" data-ra-bg-show-wrap hidden>
                  <input type="checkbox" class="admin-checkbox__input" data-ra-bg-show checked>
                  <span class="admin-checkbox__mark" aria-hidden="true"></span>
                  <span class="admin-checkbox__text">Show image</span>
                </label>
                <button type="button" class="admin-btn admin-btn--ghost util-ra-action-btn" data-ra-bg-move hidden title="Drag the floor plan to reposition it">
                  <i class="fa fa-arrows"></i> Move image
                </button>
                <button type="button" class="admin-btn admin-btn--ghost util-ra-action-btn util-ra-btn--danger-ghost" data-ra-bg-delete-canvas hidden title="Remove floor plan image from canvas">
                  <i class="fa fa-trash"></i> Delete image
                </button>
              </div>
            </div>
          </div>

          <div class="util-ra-tool-group util-ra-tool-group--units">
            <span class="util-ra-tool-group__label">Units</span>
            <div class="util-ra-tool-group__actions">
              <select class="admin-input util-ra-measure-unit" data-ra-measure-unit aria-label="Measurement units">
                <option value="meter">Meter</option>
                <option value="inch">Inch</option>
                <option value="feet_decimal">Feet Decimal</option>
                <option value="feet_inch_frac" selected>Feet Inch Frac</option>
              </select>
            </div>
          </div>

          <div class="util-ra-tool-group util-ra-tool-group--zoom">
            <span class="util-ra-tool-group__label">Zoom</span>
            <div class="util-ra-tool-group__actions util-ra-zoom-controls">
              <button type="button" class="admin-btn admin-btn--ghost util-ra-action-btn util-ra-zoom-btn" data-ra-zoom-out title="Zoom out" aria-label="Zoom out">
                <i class="fa fa-minus"></i>
              </button>
              <span class="util-ra-zoom-label" data-ra-zoom-label>100%</span>
              <button type="button" class="admin-btn admin-btn--ghost util-ra-action-btn util-ra-zoom-btn" data-ra-zoom-in title="Zoom in" aria-label="Zoom in">
                <i class="fa fa-plus"></i>
              </button>
              <button type="button" class="admin-btn admin-btn--ghost util-ra-action-btn util-ra-zoom-btn util-ra-zoom-btn--text" data-ra-zoom-100 title="Reset zoom to 100%">
                100%
              </button>
              <button type="button" class="admin-btn admin-btn--ghost util-ra-action-btn" data-ra-zoom-reset title="Fit drawing in view">
                <i class="fa fa-compress"></i> Fit
              </button>
            </div>
          </div>
        </div>

        <div class="util-ra-edit-dock" data-ra-edit-dock>
          <div class="util-ra-edit-dock__idle" aria-hidden="true">
            <span class="util-ra-edit-dock__hint">Select a wall, corner, or ruler to edit it here.</span>
          </div>
          <div class="util-ra-edit-dock__panels">
            <div class="util-ra-wall-edit util-ra-ruler-edit" data-ra-ruler-edit hidden>
              <div class="util-ra-wall-edit__inner">
                <span class="util-ra-wall-edit__label">Ruler</span>
                <span class="util-ra-ruler-length" data-ra-ruler-length>—</span>
                <button type="button" class="admin-btn admin-btn--danger util-ra-action-btn" data-ra-delete-ruler>
                  <i class="fa fa-trash"></i> Delete ruler
                </button>
                <button type="button" class="admin-btn admin-btn--ghost util-ra-action-btn" data-ra-ruler-cancel aria-label="Deselect ruler">
                  <i class="fa fa-times"></i>
                </button>
              </div>
            </div>

            <div class="util-ra-wall-edit util-ra-vertex-edit" data-ra-vertex-edit hidden>
              <div class="util-ra-wall-edit__inner">
                <span class="util-ra-wall-edit__label">Corner</span>
                <button type="button" class="admin-btn admin-btn--danger util-ra-action-btn" data-ra-delete-vertex>
                  <i class="fa fa-trash"></i> Delete corner
                </button>
                <button type="button" class="admin-btn admin-btn--ghost util-ra-action-btn" data-ra-vertex-cancel aria-label="Deselect corner">
                  <i class="fa fa-times"></i>
                </button>
              </div>
            </div>

            <div class="util-ra-wall-edit" data-ra-wall-edit hidden>
              <div class="util-ra-wall-edit__inner">
                <span class="util-ra-wall-edit__label">Edit wall length</span>
                <div class="admin-form-group util-ra-wall-edit__input">
                  <label for="ra-wall-length" class="util-sr-only">Wall length in feet</label>
                  <input class="admin-input" type="text" id="ra-wall-length" inputmode="decimal" placeholder="e.g. 12' 6&quot;, 12.5, or 150&quot;" data-ra-wall-length>
                </div>
                <button type="button" class="admin-btn admin-btn--primary util-ra-action-btn" data-ra-wall-apply>Apply</button>
                <button type="button" class="admin-btn admin-btn--danger util-ra-action-btn" data-ra-delete-wall>
                  <i class="fa fa-trash"></i> Delete wall
                </button>
                <button type="button" class="admin-btn admin-btn--ghost util-ra-action-btn" data-ra-wall-cancel aria-label="Cancel wall edit">
                  <i class="fa fa-times"></i>
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="util-ra-canvas-wrap" data-ra-canvas-wrap>
          <div id="ra-konva-container" class="util-ra-konva"></div>
        </div>

        <div class="util-ra-canvas-footer">
          <p class="util-ra-hint util-ra-hint--inline">Click corners to draw. Hold <strong>Space</strong> and drag to pan into extra space beyond the screen; <strong>Shift</strong>+scroll to zoom. Close a shape, then use <strong>Add area</strong> or <strong>New group</strong>. Calibrate scale before saving.</p>
          <div class="util-ra-live-area" data-ra-live-area hidden>
            <div class="util-ra-live-area__row">
              <span class="util-ra-live-area__label">Active shape:</span>
              <strong data-ra-live-active-shape>—</strong> sq ft
            </div>
            <ul class="util-ra-live-area-groups" data-ra-live-group-list></ul>
            <div class="util-ra-live-area__row util-ra-live-area__row--total">
              <span class="util-ra-live-area__label">All areas:</span>
              <strong data-ra-live-grand-total>—</strong> sq ft
              <span class="util-ra-live-area__secondary">(<span data-ra-live-sqm>—</span> m²)</span>
            </div>
            <span class="util-sr-only" data-ra-live-sqft aria-hidden="true">—</span>
          </div>
        </div>
        <p class="util-ra-feedback" data-ra-feedback hidden></p>
      </section>

      {{-- Saved rooms — full width below canvas --}}
      <section class="admin-card util-ra-rooms-card">
        <div class="util-ra-rooms-head">
          <div class="util-ra-rooms-head__intro">
            <h3 class="util-ra-card-title">Saved rooms</h3>
            <p class="util-ra-rooms-hint">Each room has its own floor plan and outline. Draw, save, then start another room.</p>
          </div>
          <div class="util-ra-rooms-head__actions">
            <div class="util-ra-total" data-ra-total hidden>
              <span class="util-ra-total__label">Total</span>
              <span class="util-ra-total__value"><strong data-ra-total-sqft>0</strong> sq ft</span>
              <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-ra-copy-total title="Copy total">
                <i class="fa fa-copy"></i> Copy
              </button>
            </div>
            <button type="button" class="admin-btn admin-btn--primary admin-btn--sm util-ra-action-btn" data-ra-new-room-secondary>
              <i class="fa fa-plus"></i> New room
            </button>
          </div>
        </div>
        <p class="util-ra-rooms-empty" data-ra-rooms-empty>No rooms saved yet. Draw a room on the floor plan above, then click Save room.</p>
        <ul class="util-ra-rooms-list" data-ra-rooms-list hidden></ul>
      </section>
    </div>
  </div>
@endsection

@push('scripts')
  <script src="https://unpkg.com/konva@9/konva.min.js"></script>
  <script src="{{ asset('js/admin-utilities-room-area.js') }}?v={{ @filemtime(public_path('js/admin-utilities-room-area.js')) ?: time() }}"></script>
@endpush
