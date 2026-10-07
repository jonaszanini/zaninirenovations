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
    data-room-background-destroy-template="{{ route('admin.utilities.room-area.rooms.background.destroy', ['room_area_room' => '__ID__']) }}"
    data-pdf-url="{{ route('admin.utilities.room-area.pdf') }}"
    data-room-pdf-template="{{ route('admin.utilities.room-area.rooms.pdf', ['room_area_room' => '__ID__']) }}"
    data-room-photos-store-template="{{ route('admin.utilities.room-area.rooms.photos.store', ['room_area_room' => '__ID__']) }}"
    data-room-photo-show-template="{{ route('admin.utilities.room-area.rooms.photos.show', ['room_area_room' => '__RID__', 'photo' => '__PID__']) }}"
    data-room-photo-update-template="{{ route('admin.utilities.room-area.rooms.photos.update', ['room_area_room' => '__RID__', 'photo' => '__PID__']) }}"
    data-room-photo-destroy-template="{{ route('admin.utilities.room-area.rooms.photos.destroy', ['room_area_room' => '__RID__', 'photo' => '__PID__']) }}"
    data-quote-room-template="{{ route('admin.utilities.room-area.quote') }}?layout=room:__ID__"
    data-quote-project-template="{{ route('admin.utilities.room-area.quote') }}?layout=project:__ID__">
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
        <div class="util-ra-controls__bar">
          <div class="util-ra-tool-group">
            <span class="util-ra-tool-group__label">Scale</span>
            <div class="util-ra-tool-group__actions">
              <span class="util-ra-tip" data-tooltip="Calibrate scale from a known wall">
                <button type="button" class="admin-btn admin-btn--ghost util-ra-icon-btn" data-ra-calibrate-toggle aria-label="Calibrate scale from a known wall">
                  <i class="fa fa-arrows-h" aria-hidden="true"></i>
                </button>
              </span>
              <div class="admin-form-group util-ra-scale-input">
                <label for="ra-known-length" class="util-sr-only">Known length</label>
                <input class="admin-input" type="text" id="ra-known-length" inputmode="decimal" placeholder="e.g. 12.5 ft" data-ra-known-length disabled>
              </div>
              <span class="util-ra-tip" data-tooltip="Apply known length">
                <button type="button" class="admin-btn admin-btn--primary util-ra-icon-btn" data-ra-apply-scale disabled aria-label="Apply known length">
                  <i class="fa fa-check" aria-hidden="true"></i>
                </button>
              </span>
            </div>
          </div>

          <div class="util-ra-tool-group">
            <span class="util-ra-tool-group__label">Floor plan</span>
            <div class="util-ra-tool-group__actions">
              <span class="util-ra-tip" data-tooltip="Upload floor plan">
                <label class="admin-btn admin-btn--ghost util-ra-icon-btn util-ra-file-label">
                  <i class="fa fa-upload" aria-hidden="true"></i>
                  <span class="util-sr-only">Upload floor plan</span>
                  <input type="file" accept="image/jpeg,image/png,image/webp,image/avif,.avif" data-ra-bg-file hidden>
                </label>
              </span>
              <span class="util-ra-tip" data-tooltip="Delete floor plan image">
                <button type="button" class="admin-btn admin-btn--ghost util-ra-icon-btn util-ra-btn--danger-ghost" data-ra-bg-remove hidden aria-label="Delete floor plan image">
                  <i class="fa fa-trash" aria-hidden="true"></i>
                </button>
              </span>
            </div>
          </div>

          <div class="util-ra-tool-group util-ra-tool-group--room">
            <span class="util-ra-tool-group__label">Room</span>
            <div class="util-ra-tool-group__actions">
              <div class="admin-form-group util-ra-room-name">
                <label for="ra-room-name" class="util-sr-only">Room name</label>
                <input class="admin-input" type="text" id="ra-room-name" maxlength="120" placeholder="Room name" data-ra-room-name>
              </div>
              <span class="util-ra-tip" data-tooltip="Save room" data-ra-save-tip>
                <button type="button" class="admin-btn admin-btn--primary util-ra-icon-btn" data-ra-save-room disabled aria-label="Save room">
                  <i class="fa fa-save" aria-hidden="true"></i>
                </button>
              </span>
              <span class="util-ra-tip" data-tooltip="Cancel edit">
                <button type="button" class="admin-btn admin-btn--ghost util-ra-icon-btn" data-ra-cancel-edit hidden aria-label="Cancel edit">
                  <i class="fa fa-times" aria-hidden="true"></i>
                </button>
              </span>
              <span class="util-ra-tip" data-tooltip="New room">
                <button type="button" class="admin-btn admin-btn--ghost util-ra-icon-btn" data-ra-new-room aria-label="New room">
                  <i class="fa fa-plus" aria-hidden="true"></i>
                </button>
              </span>
            </div>
          </div>
        </div>
        <p class="util-ra-scale-status" data-ra-scale-status>Scale not set — calibrate before saving rooms.</p>
      </section>

      {{-- Full-width drawing area --}}
      <section class="admin-card util-ra-canvas-card">
        <p class="util-ra-desktop-notice">
          <i class="fa fa-desktop" aria-hidden="true"></i>
          On a phone, type wall lengths below instead of dragging corners. Two fingers pan/zoom the canvas.
        </p>
        <div class="util-ra-canvas-toolbar">
          <div class="util-ra-toolbar__groups">
            <div class="util-ra-tool-group">
              <span class="util-ra-tool-group__label">Draw</span>
              <div class="util-ra-tool-group__actions">
                <span class="util-ra-tip" data-tooltip="Undo last corner">
                  <button type="button" class="admin-btn admin-btn--ghost util-ra-icon-btn" data-ra-undo-vertex disabled aria-label="Undo last corner">
                    <i class="fa fa-undo" aria-hidden="true"></i>
                  </button>
                </span>
                <span class="util-ra-tip" data-tooltip="Close shape">
                  <button type="button" class="admin-btn admin-btn--ghost util-ra-icon-btn" data-ra-close-shape disabled aria-label="Close shape">
                    <i class="fa fa-check-circle" aria-hidden="true"></i>
                  </button>
                </span>
              </div>
            </div>

            <div class="util-ra-tool-group util-ra-tool-group--areas">
              <span class="util-ra-tool-group__label">Areas</span>
              <div class="util-ra-tool-group__actions">
                <div class="util-ra-btn-cluster" role="group" aria-label="Add area options">
                  <span class="util-ra-tip" data-tooltip="Add area to this group">
                    <button type="button" class="admin-btn admin-btn--ghost util-ra-icon-btn" data-ra-add-area disabled aria-label="Add area to this group">
                      <i class="fa fa-plus-square" aria-hidden="true"></i>
                    </button>
                  </span>
                  <span class="util-ra-tip" data-tooltip="Start a new color group">
                    <button type="button" class="admin-btn admin-btn--ghost util-ra-icon-btn" data-ra-add-area-new-group disabled aria-label="Start a new color group">
                      <i class="fa fa-palette" aria-hidden="true"></i>
                    </button>
                  </span>
                </div>
                <span class="util-ra-tip" data-tooltip="Delete active area">
                  <button type="button" class="admin-btn admin-btn--ghost util-ra-icon-btn util-ra-btn--danger-ghost" data-ra-delete-area disabled aria-label="Delete active area">
                    <i class="fa fa-minus-circle" aria-hidden="true"></i>
                  </button>
                </span>
                <div class="admin-form-group util-ra-area-name" data-ra-area-name-wrap hidden>
                  <label for="ra-area-name" class="util-sr-only">Area name (optional)</label>
                  <input class="admin-input" type="text" id="ra-area-name" maxlength="80" placeholder="Area name" autocomplete="off" data-ra-area-name>
                </div>
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
                <span class="util-ra-tip" data-tooltip="Measure distance">
                  <button type="button" class="admin-btn admin-btn--ghost util-ra-icon-btn" data-ra-ruler-toggle aria-label="Measure distance">
                    <i class="fa fa-arrows-alt" aria-hidden="true"></i>
                  </button>
                </span>
                <label class="admin-btn admin-btn--ghost util-ra-icon-btn util-ra-icon-toggle util-ra-tip" data-tooltip="Show or hide dimensions">
                  <input type="checkbox" class="util-sr-only" data-ra-dimensions-toggle checked>
                  <i class="fa fa-text-width util-ra-icon-toggle__on" aria-hidden="true"></i>
                  <i class="fa fa-eye-slash util-ra-icon-toggle__off" aria-hidden="true"></i>
                  <span class="util-sr-only">Show or hide dimensions</span>
                </label>
                <span class="util-ra-tip" data-tooltip="Clear all areas">
                  <button type="button" class="admin-btn admin-btn--ghost util-ra-icon-btn" data-ra-clear-shape aria-label="Clear all areas">
                    <i class="fa fa-eraser" aria-hidden="true"></i>
                  </button>
                </span>
              </div>
            </div>

            <div class="util-ra-tool-group util-ra-tool-group--plan">
              <span class="util-ra-tool-group__label">Plan</span>
              <div class="util-ra-tool-group__actions">
                <label class="admin-btn admin-btn--ghost util-ra-icon-btn util-ra-icon-toggle util-ra-tip" data-ra-bg-show-wrap data-tooltip="Show or hide floor plan" hidden>
                  <input type="checkbox" class="util-sr-only" data-ra-bg-show checked>
                  <i class="fa fa-eye util-ra-icon-toggle__on" aria-hidden="true"></i>
                  <i class="fa fa-eye-slash util-ra-icon-toggle__off" aria-hidden="true"></i>
                  <span class="util-sr-only">Show or hide floor plan</span>
                </label>
                <span class="util-ra-tip" data-tooltip="Reposition floor plan">
                  <button type="button" class="admin-btn admin-btn--ghost util-ra-icon-btn" data-ra-bg-move hidden aria-label="Reposition floor plan">
                    <i class="fa fa-arrows" aria-hidden="true"></i>
                  </button>
                </span>
                <span class="util-ra-tip" data-tooltip="Remove floor plan image">
                  <button type="button" class="admin-btn admin-btn--ghost util-ra-icon-btn util-ra-btn--danger-ghost" data-ra-bg-delete-canvas hidden aria-label="Remove floor plan image">
                    <i class="fa fa-trash" aria-hidden="true"></i>
                  </button>
                </span>
              </div>
            </div>
          </div>

          <div class="util-ra-toolbar__view">
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
                <span class="util-ra-tip" data-tooltip="Zoom out">
                  <button type="button" class="admin-btn admin-btn--ghost util-ra-icon-btn" data-ra-zoom-out aria-label="Zoom out">
                    <i class="fa fa-minus" aria-hidden="true"></i>
                  </button>
                </span>
                <span class="util-ra-zoom-label" data-ra-zoom-label>100%</span>
                <span class="util-ra-tip" data-tooltip="Zoom in">
                  <button type="button" class="admin-btn admin-btn--ghost util-ra-icon-btn" data-ra-zoom-in aria-label="Zoom in">
                    <i class="fa fa-plus" aria-hidden="true"></i>
                  </button>
                </span>
                <span class="util-ra-tip" data-tooltip="Reset zoom to 100%">
                  <button type="button" class="admin-btn admin-btn--ghost util-ra-icon-btn" data-ra-zoom-100 aria-label="Reset zoom to 100%">
                    <i class="fa fa-search" aria-hidden="true"></i>
                  </button>
                </span>
                <span class="util-ra-tip util-ra-tip--end" data-tooltip="Fit drawing in view">
                  <button type="button" class="admin-btn admin-btn--ghost util-ra-icon-btn" data-ra-zoom-reset aria-label="Fit drawing in view">
                    <i class="fa fa-compress" aria-hidden="true"></i>
                  </button>
                </span>
              </div>
            </div>
          </div>
        </div>

        <div class="util-ra-object-dock">
          <section class="util-ra-palette" data-ra-palette aria-label="Door and window library">
            <div class="util-ra-palette__head">
              <h3 class="util-ra-palette__title">Objects</h3>
              <p class="util-ra-palette__hint">Drag onto a wall, or tap an item then tap a wall.</p>
            </div>
            <div class="util-ra-palette__groups">
              <div class="util-ra-palette__group">
                <span class="util-ra-palette__group-label">Doors</span>
                <div class="util-ra-palette__items" data-ra-palette-doors></div>
              </div>
              <div class="util-ra-palette__group">
                <span class="util-ra-palette__group-label">Windows</span>
                <div class="util-ra-palette__items" data-ra-palette-windows></div>
              </div>
            </div>
          </section>
        </div>

        <div class="util-ra-edit-dock" data-ra-edit-dock>
          <div class="util-ra-edit-dock__idle" aria-hidden="true">
            <span class="util-ra-edit-dock__hint">Select a wall, corner, object, or ruler to edit it here.</span>
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

            <div class="util-ra-wall-edit util-ra-object-edit" data-ra-object-edit hidden>
              <div class="util-ra-wall-edit__inner util-ra-object-edit__inner">
                <span class="util-ra-wall-edit__label util-ra-object-edit__title" data-ra-object-title>Object</span>
                <div class="admin-form-group util-ra-object-edit__field util-ra-object-edit__field--num">
                  <label for="ra-object-width">Width (in)</label>
                  <input class="admin-input" type="text" id="ra-object-width" inputmode="decimal" data-ra-object-width>
                </div>
                <div class="admin-form-group util-ra-object-edit__field util-ra-object-edit__field--num">
                  <label for="ra-object-offset">Offset (in)</label>
                  <input class="admin-input" type="text" id="ra-object-offset" inputmode="decimal" data-ra-object-offset>
                </div>
                <div class="admin-form-group util-ra-object-edit__field util-ra-object-edit__field--swing" data-ra-object-swing-wrap>
                  <label for="ra-object-swing">Swing</label>
                  <select class="admin-input" id="ra-object-swing" data-ra-object-swing>
                    <option value="left_in">Left / in</option>
                    <option value="left_out">Left / out</option>
                    <option value="right_in">Right / in</option>
                    <option value="right_out">Right / out</option>
                  </select>
                </div>
                <div class="admin-form-group util-ra-object-edit__field util-ra-object-edit__field--hinge" data-ra-object-hinge-wrap>
                  <label for="ra-object-hinge">Hinge</label>
                  <select class="admin-input" id="ra-object-hinge" data-ra-object-hinge>
                    <option value="left">Left</option>
                    <option value="right">Right</option>
                  </select>
                </div>
                <div class="util-ra-object-edit__actions">
                  <button type="button" class="admin-btn admin-btn--ghost util-ra-action-btn" data-ra-object-flip aria-label="Flip">
                    <i class="fa fa-exchange"></i> <span class="util-ra-object-edit__btn-text">Flip</span>
                  </button>
                  <button type="button" class="admin-btn admin-btn--ghost util-ra-action-btn" data-ra-object-rotate aria-label="Rotate">
                    <i class="fa fa-refresh"></i> <span class="util-ra-object-edit__btn-text">Rotate</span>
                  </button>
                  <button type="button" class="admin-btn admin-btn--danger util-ra-action-btn" data-ra-delete-object aria-label="Delete object">
                    <i class="fa fa-trash"></i> <span class="util-ra-object-edit__btn-text">Delete</span>
                  </button>
                  <button type="button" class="admin-btn admin-btn--ghost util-ra-action-btn" data-ra-object-cancel aria-label="Deselect object">
                    <i class="fa fa-times"></i>
                  </button>
                </div>
              </div>
            </div>

            <div class="util-ra-wall-edit" data-ra-wall-edit hidden>
              <div class="util-ra-wall-edit__inner">
                <span class="util-ra-wall-edit__label">Edit wall length</span>
                <div class="admin-form-group util-ra-wall-edit__input">
                  <label for="ra-wall-length" class="util-sr-only">Wall length in feet</label>
                  <input class="admin-input" type="text" id="ra-wall-length" inputmode="decimal" placeholder="e.g. 12' 6&quot;, 12.5, or 150&quot;" data-ra-wall-length>
                </div>
                <label class="util-ra-wall-edit__scale-option" title="Recalibrate scale so this wall matches the value you enter; all other wall lengths and areas update proportionally. The drawing shape stays the same.">
                  <input type="checkbox" data-ra-wall-scale-project>
                  <span>Scale entire project</span>
                </label>
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

        <section class="util-ra-type-walls" data-ra-type-walls aria-label="Type walls">
          <div class="util-ra-type-walls__head">
            <h3 class="util-ra-type-walls__title">Type walls</h3>
            <p class="util-ra-type-walls__hint">Tape lengths from the top-left, first wall going right.</p>
          </div>

          <div class="util-ra-type-walls__entry">
            <div class="admin-form-group util-ra-type-walls__len">
              <label for="ra-type-length">Length</label>
              <input class="admin-input" type="text" id="ra-type-length" inputmode="decimal" autocomplete="off" data-ra-type-length placeholder="12'6">
            </div>
            <div class="util-ra-type-walls__dirs" role="group" aria-label="Turn direction">
              <button type="button" class="admin-btn admin-btn--ghost util-ra-type-walls__dir is-active" data-ra-type-dir="right" aria-pressed="true" title="Turn right 90°">
                R90
              </button>
              <button type="button" class="admin-btn admin-btn--ghost util-ra-type-walls__dir" data-ra-type-dir="left" aria-pressed="false" title="Turn left 90°">
                L90
              </button>
              <button type="button" class="admin-btn admin-btn--ghost util-ra-type-walls__dir" data-ra-type-dir="custom" aria-pressed="false" title="Custom angle" aria-label="Custom angle">
                ∠°
              </button>
            </div>
            <div class="admin-form-group util-ra-type-walls__angle" data-ra-type-angle-wrap hidden>
              <label for="ra-type-angle">Turn °</label>
              <input class="admin-input" type="text" id="ra-type-angle" inputmode="decimal" autocomplete="off" data-ra-type-angle placeholder="45">
            </div>
            <button type="button" class="admin-btn admin-btn--primary util-ra-type-walls__add" data-ra-type-add>
              Add
            </button>
          </div>

          <div class="util-ra-type-walls__rect">
            <span class="util-ra-type-walls__rect-label">Rect</span>
            <div class="admin-form-group util-ra-type-walls__rect-field">
              <label for="ra-type-rect-w">W</label>
              <input class="admin-input" type="text" id="ra-type-rect-w" inputmode="decimal" autocomplete="off" data-ra-type-rect-w placeholder="12">
            </div>
            <span class="util-ra-type-walls__times" aria-hidden="true">×</span>
            <div class="admin-form-group util-ra-type-walls__rect-field">
              <label for="ra-type-rect-l">L</label>
              <input class="admin-input" type="text" id="ra-type-rect-l" inputmode="decimal" autocomplete="off" data-ra-type-rect-l placeholder="10">
            </div>
            <button type="button" class="admin-btn admin-btn--ghost util-ra-type-walls__rect-add" data-ra-type-rect>
              Add rect
            </button>
          </div>

          <ol class="util-ra-type-walls__list" data-ra-type-list hidden></ol>

          <div class="util-ra-type-walls__close" data-ra-type-close-bar hidden>
            <button type="button" class="admin-btn admin-btn--primary util-ra-type-walls__close-btn" data-ra-type-close>
              Close room
            </button>
            <button type="button" class="admin-btn admin-btn--ghost util-ra-type-walls__auto" data-ra-type-autoclose hidden>
              Auto-close
            </button>
            <p class="util-ra-type-walls__gap" data-ra-type-gap></p>
          </div>
        </section>

        <div class="util-ra-canvas-wrap" data-ra-canvas-wrap>
          <div id="ra-konva-container" class="util-ra-konva"></div>
        </div>

        <div class="util-ra-canvas-footer">
          <p class="util-ra-hint util-ra-hint--inline">Click corners to draw. Drag a door or window from the palette onto a wall. Hold <strong>Space</strong> and drag to pan; <strong>Shift</strong>+scroll to zoom. Calibrate scale before saving.</p>
          <div class="util-ra-live-area" data-ra-live-area hidden>
            <div class="util-ra-live-area__row util-ra-live-area__row--active">
              <span class="util-ra-live-area__label">Active shape:</span>
              <strong data-ra-live-active-shape>—</strong> sq ft
            </div>
            <ul class="util-ra-live-area-groups" data-ra-live-group-list></ul>
            <div class="util-ra-live-metrics" data-ra-live-metrics>
              <div class="util-ra-live-metrics__item">
                <span class="util-ra-live-area__label">Floor</span>
                <strong><span data-ra-live-grand-total>—</span> sq ft</strong>
              </div>
              <div class="util-ra-live-metrics__item">
                <span class="util-ra-live-area__label">Perimeter</span>
                <strong><span data-ra-live-perimeter>—</span> lf</strong>
              </div>
              <div class="util-ra-live-metrics__item">
                <span class="util-ra-live-area__label">Baseboard</span>
                <strong><span data-ra-live-baseboard>—</span> lf</strong>
              </div>
              <div class="util-ra-live-metrics__item">
                <span class="util-ra-live-area__label">Wall SF</span>
                <strong><span data-ra-live-wall-area>—</span> sq ft</strong>
              </div>
            </div>
            <div class="util-ra-live-area__row util-ra-live-area__row--counts">
              <span data-ra-live-counts>0 doors · 0 windows · 0 openings</span>
              <span class="util-ra-live-area__secondary">(<span data-ra-live-sqm>—</span> m² floor)</span>
            </div>
            <div class="util-ra-live-area__toolbar">
              <div class="admin-form-group util-ra-live-area__height">
                <label for="ra-wall-height">Height</label>
                <input class="admin-input" type="text" id="ra-wall-height" inputmode="decimal" value="8" data-ra-wall-height>
                <span class="util-ra-live-area__height-unit">ft</span>
              </div>
              <div class="util-ra-live-area__exports">
                <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-ra-export-png>
                  <i class="fa fa-image" aria-hidden="true"></i> PNG
                </button>
                <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-ra-export-pdf>
                  <i class="fa fa-file-pdf-o" aria-hidden="true"></i> PDF
                </button>
                <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-ra-attach-pdf hidden>
                  <i class="fa fa-paperclip" aria-hidden="true"></i> Attach
                </button>
                <a class="admin-btn admin-btn--primary admin-btn--sm" data-ra-create-quote hidden href="#">
                  <i class="fa fa-file-text-o" aria-hidden="true"></i> Create quote
                </a>
              </div>
            </div>
            <span class="util-sr-only" data-ra-live-sqft aria-hidden="true">—</span>
          </div>
        </div>

        <section class="util-ra-photos" data-ra-photos hidden aria-label="Site photos">
          <div class="util-ra-photos__head">
            <h3 class="util-ra-photos__title">Site photos</h3>
            <span class="util-ra-photos__count" data-ra-photos-count></span>
          </div>
          <div class="util-ra-photos__add">
            <label class="admin-btn admin-btn--ghost util-ra-photos__file-btn">
              <i class="fa fa-camera" aria-hidden="true"></i> Add photos
              <input type="file" accept="image/*" capture="environment" multiple data-ra-photos-file hidden>
            </label>
          </div>
          <p class="util-ra-photos__empty" data-ra-photos-empty>No photos yet. Add shots from the job site.</p>
          <ul class="util-ra-photos__strip" data-ra-photos-strip hidden></ul>
        </section>
        <p class="util-ra-feedback" data-ra-feedback hidden></p>
      </section>

      <div class="util-ra-lightbox" data-ra-lightbox hidden>
        <button type="button" class="util-ra-lightbox__backdrop" data-ra-lightbox-close aria-label="Close photo"></button>
        <figure class="util-ra-lightbox__figure">
          <img class="util-ra-lightbox__img" data-ra-lightbox-img alt="">
          <div class="util-ra-lightbox__caption-row">
            <label class="util-sr-only" for="ra-lightbox-caption">Photo caption</label>
            <input class="admin-input util-ra-lightbox__caption-input" type="text" id="ra-lightbox-caption" maxlength="160" autocomplete="off" placeholder="Add a caption" data-ra-lightbox-caption>
          </div>
          <button type="button" class="admin-btn admin-btn--ghost util-ra-lightbox__x" data-ra-lightbox-close aria-label="Close">
            <i class="fa fa-times" aria-hidden="true"></i>
          </button>
        </figure>
      </div>

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
            <a class="admin-btn admin-btn--outline admin-btn--sm util-ra-action-btn" data-ra-quote-all hidden href="#">
              <i class="fa fa-file-text-o"></i> Quote all
            </a>
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
