/* Room Area tool — Konva polygon drawing with scale calibration */
(function () {
  'use strict';

  if (typeof Konva === 'undefined') return;
  var RA = window.RoomAreaLib;
  if (!RA) return;

  var root = document.querySelector('[data-room-area]');
  if (!root) return;

  function isFullPage() {
    return root.hasAttribute('data-ra-fullpage');
  }

  function isPhoneChrome() {
    return isFullPage() && typeof window.matchMedia === 'function'
      && window.matchMedia('(max-width: 900px)').matches;
  }

  function raAll(sel) {
    return Array.prototype.slice.call(root.querySelectorAll(sel));
  }

  function setSaveDisabled(disabled) {
    raAll('[data-ra-save-room]').forEach(function (btn) {
      btn.disabled = !!disabled;
    });
  }

  var INCHES_PER_FOOT = 12;
  var SQFT_TO_SQM = 0.092903;
  var FEET_TO_METERS = 0.3048;
  var FIRST_TIP_STORAGE_KEY = 'zr-room-area-guide-tip-v1';
  var MEASURE_UNITS = {
    meter: 'meter',
    inch: 'inch',
    feet_decimal: 'feet_decimal',
    feet_inch_frac: 'feet_inch_frac'
  };
  var DIM_MODES = {
    all: 'all',
    main: 'main',
    area: 'area',
    off: 'off'
  };
  var MIN_CANVAS_W = 260;
  var MIN_CANVAS_H = 520;
  var MIN_CANVAS_H_NARROW = 180;
  var NARROW_CANVAS_MAX = 700;
  var MAX_CANVAS_H = 1100;
  var CANVAS_HEIGHT_RATIO = 0.75;
  var CANVAS_MAX_VH = 0.72;
  var CANVAS_MAX_VH_NARROW = 0.42;
  var CANVAS_MAX_VH_PHONE = 0.36;
  var FIT_LABEL_PAD = 28;
  var FIT_WIDTH_RATIO = 0.90;
  var DRAW_AREA_FACTOR = 6;
  var GRID_PX = 20;
  var VERTEX_HIT = 14;
  var CLOSE_HIT = 16;
  var WALL_HIT = 14;
  var RULER_HIT = 12;
  var TOUCH_HIT_RADIUS = 22;
  var TOUCH_HIT_STROKE = 44;
  var WALL_LONG_PRESS_MS = 550;
  var NUDGE_STEP_PX = 1;
  var NUDGE_STEP_SHIFT_PX = 10;
  var MIN_ZOOM_PERCENT = 8;
  var MAX_ZOOM_PERCENT = 500;
  var ZOOM_STEP = 1.12;
  var ZOOM_FIT_PAD = 48;
  var SHIFT_SNAP_THRESHOLD_DEG = 10;
  var SHIFT_SNAP_STEP = Math.PI / 4;
  var UNDO_MAX = 50;
  var AREA_NAME_MAX = 80;
  var WALL_DOUBLE_CLICK_MS = 400;
  var WALL_SPLIT_DEBOUNCE_MS = 400;
  var GROUP_COLORS = ['#024768', '#2e7d32', '#e65100', '#6a1b9a', '#c62828', '#00838f', '#5d4037'];
  var DEFAULT_GROUP_COLOR = GROUP_COLORS[0];
  var INACTIVE_POLYGON_OPACITY = 0.45;
  var OBJECT_SNAP_PX = 36;
  var OBJECT_MIN_WIDTH_IN = 6;
  var OBJECT_MAX_WIDTH_IN = 240;
  var DEFAULT_WALL_HEIGHT_FT = RA.DEFAULT_WALL_HEIGHT_FT || 8;
  var DEFAULT_EDITOR_PREFS = {
    layers_locked: false,
    dimensions_mode: DIM_MODES.all,
    unit_system: 'imperial',
    measure_unit: MEASURE_UNITS.feet_inch_frac,
    angle_snap: true
  };
  var DEFAULT_DOOR_HEIGHT_IN = 80;
  var DEFAULT_WINDOW_HEIGHT_IN = 36;
  var DEFAULT_TYPED_PPF = 12;
  var CLOSE_GAP_THRESHOLD_IN = 6;
  var SQUARE_SNAP_DEG = 8;
  var WALL_OBJECT_TYPES = { door: true, window: true, opening: true };
  var FREE_OBJECT_TYPES = { stairs: true, cabinet: true, fixture: true, furniture: true };
  var WALL_THICKNESS_PRESETS = [0, 3.5, 4.5, 5.5, 6];
  var SHARED_WALL_ANGLE_DEG = 8;
  var SHARED_MIN_OVERLAP_IN = 6;
  var OBJECT_CATALOG = [
    { key: 'door-30', type: 'door', subtype: 'single', width: 30, label: '30″ door', kind: 'door', place: 'wall' },
    { key: 'door-32', type: 'door', subtype: 'single', width: 32, label: '32″ door', kind: 'door', place: 'wall' },
    { key: 'door-36', type: 'door', subtype: 'single', width: 36, label: '36″ door', kind: 'door', place: 'wall' },
    { key: 'door-60', type: 'door', subtype: 'double', width: 60, label: '60″ double', kind: 'door', place: 'wall' },
    { key: 'door-72', type: 'door', subtype: 'double', width: 72, label: '72″ double', kind: 'door', place: 'wall' },
    { key: 'door-slide', type: 'door', subtype: 'sliding', width: 60, label: 'Sliding', kind: 'door', place: 'wall' },
    { key: 'door-bifold', type: 'door', subtype: 'bifold', width: 60, label: 'Bifold', kind: 'door', place: 'wall' },
    { key: 'opening', type: 'opening', subtype: 'archway', width: 36, label: 'Opening', kind: 'door', place: 'wall' },
    { key: 'win-24', type: 'window', subtype: 'fixed', width: 24, label: '24″ window', kind: 'window', place: 'wall' },
    { key: 'win-36', type: 'window', subtype: 'fixed', width: 36, label: '36″ window', kind: 'window', place: 'wall' },
    { key: 'win-48', type: 'window', subtype: 'fixed', width: 48, label: '48″ window', kind: 'window', place: 'wall' },
    { key: 'win-60', type: 'window', subtype: 'fixed', width: 60, label: '60″ window', kind: 'window', place: 'wall' },
    { key: 'stair-str', type: 'stairs', subtype: 'straight', width: 36, depth: 120, label: 'Straight stairs', kind: 'stairs', place: 'floor' },
    { key: 'stair-l', type: 'stairs', subtype: 'l', width: 72, depth: 72, label: 'L stairs', kind: 'stairs', place: 'floor' },
    { key: 'stair-u', type: 'stairs', subtype: 'u', width: 72, depth: 96, label: 'U stairs', kind: 'stairs', place: 'floor' },
    { key: 'cab-base', type: 'cabinet', subtype: 'base', width: 36, depth: 24, label: 'Base cab', kind: 'cabinet', place: 'floor' },
    { key: 'cab-upper', type: 'cabinet', subtype: 'upper', width: 36, depth: 12, label: 'Upper cab', kind: 'cabinet', place: 'floor' },
    { key: 'cab-corner', type: 'cabinet', subtype: 'corner', width: 24, depth: 24, label: 'Corner cab', kind: 'cabinet', place: 'floor' },
    { key: 'cab-island', type: 'cabinet', subtype: 'island', width: 48, depth: 36, label: 'Island', kind: 'cabinet', place: 'floor' },
    { key: 'fix-toilet', type: 'fixture', subtype: 'toilet', width: 20, depth: 30, label: 'Toilet', kind: 'fixture', place: 'floor' },
    { key: 'fix-tub', type: 'fixture', subtype: 'tub', width: 60, depth: 32, label: 'Tub', kind: 'fixture', place: 'floor' },
    { key: 'fix-shower', type: 'fixture', subtype: 'shower', width: 36, depth: 36, label: 'Shower', kind: 'fixture', place: 'floor' },
    { key: 'fix-vanity', type: 'fixture', subtype: 'vanity', width: 36, depth: 21, label: 'Vanity', kind: 'fixture', place: 'floor' },
    { key: 'furn-sofa', type: 'furniture', subtype: 'sofa', width: 84, depth: 36, label: 'Sofa', kind: 'furniture', place: 'floor' },
    { key: 'furn-bed', type: 'furniture', subtype: 'bed', width: 60, depth: 80, label: 'Bed', kind: 'furniture', place: 'floor' },
    { key: 'furn-table', type: 'furniture', subtype: 'dining_table', width: 72, depth: 36, label: 'Dining', kind: 'furniture', place: 'floor' }
  ];

  function isAdminDark() {
    if (state && state.exporting) return false;
    return document.documentElement.classList.contains('admin-dark');
  }

  function gridStrokeColor() {
    var wrap = els.canvasWrap;
    if (wrap) {
      var color = window.getComputedStyle(wrap).getPropertyValue('--ra-grid-stroke').trim();
      if (color) return color;
    }
    return isAdminDark() ? '#334155' : '#d8dee4';
  }

  function canvasTagFill(selected) {
    if (selected) return 'rgba(249, 168, 37, 0.98)';
    return isAdminDark() ? 'rgba(15, 23, 42, 0.98)' : 'rgba(255,255,255,0.98)';
  }

  function canvasBrandStroke() {
    return isAdminDark() ? '#38bdf8' : '#024768';
  }

  function canvasBrandText(selected) {
    if (selected) return '#5d4037';
    return isAdminDark() ? '#7dd3fc' : '#024768';
  }

  function canvasAreaLabelText() {
    return isAdminDark() ? '#f8fafc' : '#024768';
  }

  function canvasPolygonFill(hex, opacity) {
    var a = opacity == null ? 1 : opacity;
    if (!isAdminDark()) return hexToRgba(hex, 0.12 * a);
    var mixed = mixHex(hex || DEFAULT_GROUP_COLOR, '#7dd3fc', 0.72);
    return hexToRgba(mixed, 0.38 * a);
  }

  function mixHex(aHex, bHex, t) {
    function parts(hex) {
      var h = String(hex || '').replace('#', '');
      if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
      return [
        parseInt(h.substring(0, 2), 16) || 0,
        parseInt(h.substring(2, 4), 16) || 0,
        parseInt(h.substring(4, 6), 16) || 0
      ];
    }
    var a = parts(aHex);
    var b = parts(bHex);
    var r = Math.round(a[0] + (b[0] - a[0]) * t);
    var g = Math.round(a[1] + (b[1] - a[1]) * t);
    var bl = Math.round(a[2] + (b[2] - a[2]) * t);
    return '#' + [r, g, bl].map(function (n) {
      var s = n.toString(16);
      return s.length === 1 ? '0' + s : s;
    }).join('');
  }

  function canvasRulerText(selected) {
    if (selected) return '#5d4037';
    return isAdminDark() ? '#e9d5ff' : '#4a148c';
  }

  var els = {
    projectSelect: root.querySelector('[data-ra-project-select]'),
    newProjectToggle: root.querySelector('[data-ra-new-project-toggle]'),
    newProjectPanel: root.querySelector('[data-ra-new-project]'),
    newProjectName: root.querySelector('#ra-new-project-name'),
    newClientProject: root.querySelector('[data-ra-new-client-project]'),
    createProjectBtn: root.querySelector('[data-ra-create-project]'),
    deleteProjectBtn: root.querySelector('[data-ra-delete-project]'),
    linked: root.querySelector('[data-ra-linked]'),
    linkedLink: root.querySelector('[data-ra-linked-link]'),
    linkClientProject: root.querySelector('[data-ra-link-client-project]'),
    empty: root.querySelector('[data-ra-empty]'),
    workspace: root.querySelector('[data-ra-workspace]'),
    calibrateToggle: root.querySelector('[data-ra-calibrate-toggle]'),
    knownLength: root.querySelector('[data-ra-known-length]'),
    applyScale: root.querySelector('[data-ra-apply-scale]'),
    scaleStatus: root.querySelector('[data-ra-scale-status]'),
    bgFile: root.querySelector('[data-ra-bg-file]'),
    bgRemove: root.querySelector('[data-ra-bg-remove]'),
    bgDeleteCanvas: root.querySelector('[data-ra-bg-delete-canvas]'),
    bgShowWrap: root.querySelector('[data-ra-bg-show-wrap]'),
    bgShowImage: root.querySelector('[data-ra-bg-show]'),
    bgMoveToggle: root.querySelector('[data-ra-bg-move]'),
    undoVertex: root.querySelector('[data-ra-undo-vertex]'),
    redo: root.querySelector('[data-ra-redo]'),
    splitWall: root.querySelector('[data-ra-split-wall]'),
    shortcuts: root.querySelector('[data-ra-shortcuts]'),
    shortcutsToggle: root.querySelector('[data-ra-shortcuts-toggle]'),
    shortcutsPopover: root.querySelector('[data-ra-shortcuts-popover]'),
    closeShape: root.querySelector('[data-ra-close-shape]'),
    addArea: root.querySelector('[data-ra-add-area]'),
    addAreaNewGroup: root.querySelector('[data-ra-add-area-new-group]'),
    deleteArea: root.querySelector('[data-ra-delete-area]'),
    groupChip: root.querySelector('[data-ra-group-chip]'),
    groupChipSwatch: root.querySelector('[data-ra-group-chip-swatch]'),
    groupChipTotal: root.querySelector('[data-ra-group-chip-total]'),
    clearShape: root.querySelector('[data-ra-clear-shape]'),
    deleteWall: root.querySelector('[data-ra-delete-wall]'),
    vertexEdit: root.querySelector('[data-ra-vertex-edit]'),
    deleteVertex: root.querySelector('[data-ra-delete-vertex]'),
    vertexCancel: root.querySelector('[data-ra-vertex-cancel]'),
    rulerToggle: root.querySelector('[data-ra-ruler-toggle]'),
    rulerEdit: root.querySelector('[data-ra-ruler-edit]'),
    rulerLength: root.querySelector('[data-ra-ruler-length]'),
    deleteRuler: root.querySelector('[data-ra-delete-ruler]'),
    rulerCancel: root.querySelector('[data-ra-ruler-cancel]'),
    canvasWrap: root.querySelector('[data-ra-canvas-wrap]'),
    liveArea: root.querySelector('[data-ra-live-area]'),
    liveActiveShape: root.querySelector('[data-ra-live-active-shape]'),
    liveGroupList: root.querySelector('[data-ra-live-group-list]'),
    liveGrandTotal: root.querySelector('[data-ra-live-grand-total]'),
    liveSqft: root.querySelector('[data-ra-live-sqft]'),
    liveSqm: root.querySelector('[data-ra-live-sqm]'),
    livePerimeter: root.querySelector('[data-ra-live-perimeter]'),
    liveBaseboard: root.querySelector('[data-ra-live-baseboard]'),
    liveWallArea: root.querySelector('[data-ra-live-wall-area]'),
    liveCounts: root.querySelector('[data-ra-live-counts]'),
    wallHeight: root.querySelector('[data-ra-wall-height]'),
    ceilingHeight: root.querySelector('[data-ra-ceiling-height]'),
    floorWaste: root.querySelector('[data-ra-floor-waste]'),
    floorBox: root.querySelector('[data-ra-floor-box]'),
    wallList: root.querySelector('[data-ra-wall-list]'),
    liveCeilingArea: root.querySelector('[data-ra-live-ceiling-area]'),
    liveBoxes: root.querySelector('[data-ra-live-boxes]'),
    liveCasing: root.querySelector('[data-ra-live-casing]'),
    wallNetArea: root.querySelector('[data-ra-wall-net-area]'),
    objectCasing: root.querySelector('[data-ra-object-casing]'),
    objectCasingWrap: root.querySelector('[data-ra-object-casing-wrap]'),
    objectDoorFace: root.querySelector('[data-ra-object-door-face]'),
    objectDoorFaceWrap: root.querySelector('[data-ra-object-door-face-wrap]'),
    floorBoxUnit: root.querySelector('[data-ra-floor-box-unit]'),
    exportPng: root.querySelector('[data-ra-export-png]'),
    exportPdf: root.querySelector('[data-ra-export-pdf]'),
    attachPdf: root.querySelector('[data-ra-attach-pdf]'),
    createQuote: root.querySelector('[data-ra-create-quote]'),
    quoteAll: root.querySelector('[data-ra-quote-all]'),
    feedback: root.querySelector('[data-ra-feedback]'),
    roomName: root.querySelector('[data-ra-room-name]'),
    saveRoom: root.querySelector('[data-ra-save-room]'),
    saveTip: root.querySelector('[data-ra-save-tip]'),
    cancelEdit: root.querySelector('[data-ra-cancel-edit]'),
    newRoom: root.querySelector('[data-ra-new-room]'),
    newRoomSecondary: root.querySelector('[data-ra-new-room-secondary]'),
    roomsEmpty: root.querySelector('[data-ra-rooms-empty]'),
    roomsList: root.querySelector('[data-ra-rooms-list]'),
    total: root.querySelector('[data-ra-total]'),
    totalSqft: root.querySelector('[data-ra-total-sqft]'),
    copyTotal: root.querySelector('[data-ra-copy-total]'),
    wallEdit: root.querySelector('[data-ra-wall-edit]'),
    wallLength: root.querySelector('[data-ra-wall-length]'),
    wallScaleProject: root.querySelector('[data-ra-wall-scale-project]'),
    wallApply: root.querySelector('[data-ra-wall-apply]'),
    wallCancel: root.querySelector('[data-ra-wall-cancel]'),
    zoomIn: root.querySelector('[data-ra-zoom-in]'),
    zoomOut: root.querySelector('[data-ra-zoom-out]'),
    zoom100: root.querySelector('[data-ra-zoom-100]'),
    zoomReset: root.querySelector('[data-ra-zoom-reset]'),
    zoomLabel: root.querySelector('[data-ra-zoom-label]'),
    measureUnit: root.querySelector('[data-ra-measure-unit]'),
    dimensionsMode: root.querySelector('[data-ra-dimensions-mode]'),
    lockLayers: root.querySelector('[data-ra-lock-layers]'),
    snapToggle: root.querySelector('[data-ra-snap-toggle]'),
    templatesToggle: root.querySelector('[data-ra-templates-toggle]'),
    templatesPopover: root.querySelector('[data-ra-templates-popover]'),
    templatesRoot: root.querySelector('[data-ra-templates]'),
    moreToggle: root.querySelector('[data-ra-more-toggle]'),
    moreClose: root.querySelector('[data-ra-more-close]'),
    moreSheet: root.querySelector('[data-ra-more-sheet]'),
    sheetTotal: root.querySelector('[data-ra-sheet-total]'),
    areaName: root.querySelector('[data-ra-area-name]'),
    areaNameWrap: root.querySelector('[data-ra-area-name-wrap]'),
    palette: root.querySelector('[data-ra-palette]'),
    paletteToggle: root.querySelector('[data-ra-palette-toggle]'),
    paletteBody: root.querySelector('[data-ra-palette-body]'),
    paletteDoors: root.querySelector('[data-ra-palette-doors]'),
    paletteWindows: root.querySelector('[data-ra-palette-windows]'),
    paletteStairs: root.querySelector('[data-ra-palette-stairs]'),
    paletteCabinets: root.querySelector('[data-ra-palette-cabinets]'),
    paletteFixtures: root.querySelector('[data-ra-palette-fixtures]'),
    paletteFurniture: root.querySelector('[data-ra-palette-furniture]'),
    wallsPanel: root.querySelector('[data-ra-walls-panel]'),
    wallsToggle: root.querySelector('[data-ra-walls-toggle]'),
    wallsBody: root.querySelector('[data-ra-walls-body]'),
    thicknessCustom: root.querySelector('[data-ra-thickness-custom]'),
    thicknessCustomInput: root.querySelector('[data-ra-thickness-custom-input]'),
    liveFootprint: root.querySelector('[data-ra-live-footprint]'),
    liveFootprintWrap: root.querySelector('[data-ra-live-footprint-wrap]'),
    liveFraming: root.querySelector('[data-ra-live-framing]'),
    liveFramingWrap: root.querySelector('[data-ra-live-framing-wrap]'),
    liveCabinets: root.querySelector('[data-ra-live-cabinets]'),
    liveCabinetsWrap: root.querySelector('[data-ra-live-cabinets-wrap]'),
    objectOffsetWrap: root.querySelector('[data-ra-object-offset-wrap]'),
    objectDepth: root.querySelector('[data-ra-object-depth]'),
    objectDepthWrap: root.querySelector('[data-ra-object-depth-wrap]'),
    objectRotation: root.querySelector('[data-ra-object-rotation]'),
    objectRotationWrap: root.querySelector('[data-ra-object-rotation-wrap]'),
    dimensionsToggle: root.querySelector('[data-ra-dimensions-toggle]'),
    objectEdit: root.querySelector('[data-ra-object-edit]'),
    objectTitle: root.querySelector('[data-ra-object-title]'),
    objectWidth: root.querySelector('[data-ra-object-width]'),
    objectOffset: root.querySelector('[data-ra-object-offset]'),
    objectSwing: root.querySelector('[data-ra-object-swing]'),
    objectHinge: root.querySelector('[data-ra-object-hinge]'),
    objectSwingWrap: root.querySelector('[data-ra-object-swing-wrap]'),
    objectHingeWrap: root.querySelector('[data-ra-object-hinge-wrap]'),
    objectFlip: root.querySelector('[data-ra-object-flip]'),
    objectRotate: root.querySelector('[data-ra-object-rotate]'),
    deleteObject: root.querySelector('[data-ra-delete-object]'),
    objectCancel: root.querySelector('[data-ra-object-cancel]'),
    typeLength: root.querySelector('[data-ra-type-length]'),
    typeAngleWrap: root.querySelector('[data-ra-type-angle-wrap]'),
    typeAngle: root.querySelector('[data-ra-type-angle]'),
    typeAdd: root.querySelector('[data-ra-type-add]'),
    typeRectW: root.querySelector('[data-ra-type-rect-w]'),
    typeRectL: root.querySelector('[data-ra-type-rect-l]'),
    typeRect: root.querySelector('[data-ra-type-rect]'),
    typeList: root.querySelector('[data-ra-type-list]'),
    typeCloseBar: root.querySelector('[data-ra-type-close-bar]'),
    typeClose: root.querySelector('[data-ra-type-close]'),
    typeGap: root.querySelector('[data-ra-type-gap]'),
    typeAutoclose: root.querySelector('[data-ra-type-autoclose]'),
    typeDirBtns: root.querySelectorAll('[data-ra-type-dir]'),
    photos: root.querySelector('[data-ra-photos]'),
    photosCount: root.querySelector('[data-ra-photos-count]'),
    photosFile: root.querySelector('[data-ra-photos-file]'),
    photosEmpty: root.querySelector('[data-ra-photos-empty]'),
    photosStrip: root.querySelector('[data-ra-photos-strip]'),
    lightbox: root.querySelector('[data-ra-lightbox]'),
    lightboxImg: root.querySelector('[data-ra-lightbox-img]'),
    lightboxCaption: root.querySelector('[data-ra-lightbox-caption]')
  };

  var state = {
    projects: [],
    activeProjectId: null,
    groups: [],
    activeGroupId: null,
    activePolygonId: null,
    nextGroupId: 1,
    nextPolygonId: 1,
    objects: [],
    nextObjectId: 1,
    selectedObjectId: null,
    showDimensions: true,
    dimensionsMode: DIM_MODES.all,
    layersLocked: false,
    templateKind: null,
    angleSnap: true,
    altHeld: false,
    imperialUnit: MEASURE_UNITS.feet_inch_frac,
    alignGuides: null,
    openingHint: null,
    armedCatalogKey: null,
    placingCatalog: null,
    objectGhost: null,
    draggingObject: false,
    objectPointerDown: null,
    calibrating: false,
    calibPoints: [],
    rulerMode: false,
    rulerDraft: [],
    selectedRulerId: null,
    nextRulerId: 1,
    rulerPreview: null,
    drawPreview: null,
    draftRulers: [],
    activeBackgroundUrl: null,
    bgImageVisible: true,
    bgLayout: null,
    bgMoveMode: false,
    draggingBg: false,
    draftBackground: null,
    draftScale: null,
    workingPpf: null,
    stage: null,
    worldGroup: null,
    viewport: { scale: 1, x: 0, y: 0, zoomPercent: 100 },
    panning: false,
    didPan: false,
    lastPanScreen: null,
    spaceHeld: false,
    shiftHeld: false,
    pinching: false,
    pinch: null,
    wallLongPressTimer: null,
    emptyPanFromTouch: false,
    layers: {},
    konvaLayer: null,
    bgImage: null,
    bgNaturalW: 0,
    bgNaturalH: 0,
    lastUiScale: 1,
    selectedWallIndex: null,
    selectedVertexIndices: [],
    draggingVertex: false,
    draggingWall: false,
    draggingShape: false,
    shapePointerDown: null,
    didShapeDrag: false,
    wallDragIndex: null,
    wallDragPointerStart: null,
    wallDragOrigA: null,
    wallDragOrigB: null,
    wallPointerDown: null,
    wallClickTimer: null,
    lastWallClick: null,
    lastWallSplit: null,
    didWallDrag: false,
    didVertexDrag: false,
    vertexDragFrom: null,
    vertexDragOrigins: null,
    undoStack: [],
    redoStack: [],
    draggingRuler: false,
    editingRoomId: null,
    editorToken: 0,
    savedSnapshot: null,
    measureUnit: MEASURE_UNITS.feet_inch_frac,
    viewportW: 900,
    viewportH: 640,
    canvasW: 5400,
    canvasH: 3840,
    wallHeightFt: DEFAULT_WALL_HEIGHT_FT,
    ceilingHeightFt: null,
    flooringWastePercent: RA.DEFAULT_WASTE_PERCENT || 10,
    flooringDirection: RA.DIRECTION_LENGTH || 0,
    flooringBoxSqFt: RA.DEFAULT_BOX_SQFT || 20,
    wallThicknessIn: 0,
    transformer: null,
    sharedPairs: [],
    exporting: false,
    typedTurn: 'right',
    typedHeading: 0,
    typedStart: null,
    typedWalls: [],
    lightboxPhotoId: null
  };

  var resizeTimer = null;

  function cloneGroups(groups) {
    return (groups || []).map(function (group) {
      return {
        id: group.id,
        color: group.color,
        polygons: (group.polygons || []).map(function (poly) {
          return {
            id: poly.id,
            closed: !!poly.closed,
            name: polygonName(poly),
            vertices: (poly.vertices || []).map(function (v) {
              return { x: v.x, y: v.y };
            })
          };
        })
      };
    });
  }

  function cloneObjects(objects) {
    return (objects || []).map(function (obj) {
      return {
        id: obj.id,
        type: obj.type,
        subtype: obj.subtype || '',
        wall_id: obj.wall_id,
        width: obj.width,
        offset: obj.offset,
        swing: obj.swing || 'left_in',
        hinge: obj.hinge || 'left',
        height: obj.height,
        sill_height: obj.sill_height,
        x: obj.x,
        y: obj.y,
        depth: obj.depth,
        rotation: obj.rotation || 0,
        casing_sides: obj.casing_sides,
        door_face: obj.door_face
      };
    });
  }

  function objectType(obj) {
    return RA.objectType ? RA.objectType(obj) : String(obj && obj.type || '').toLowerCase().trim();
  }

  function isWallObject(obj) {
    return !!(obj && WALL_OBJECT_TYPES[objectType(obj)]);
  }

  function isFreeObject(obj) {
    return !!(obj && FREE_OBJECT_TYPES[obj.type]);
  }

  function objectTitle(obj) {
    if (!obj) return 'Object';
    if (obj.type === 'window') return Math.round(obj.width) + '″ window';
    if (obj.type === 'opening') return Math.round(obj.width) + '″ opening';
    if (obj.subtype === 'double') return Math.round(obj.width) + '″ double door';
    if (obj.subtype === 'sliding' || obj.subtype === 'pocket') return Math.round(obj.width) + '″ sliding door';
    if (obj.subtype === 'bifold') return Math.round(obj.width) + '″ bifold';
    if (obj.type === 'stairs') {
      if (obj.subtype === 'l') return 'L stairs';
      if (obj.subtype === 'u') return 'U stairs';
      return 'Straight stairs';
    }
    if (obj.type === 'cabinet') {
      if (obj.subtype === 'upper') return 'Upper cabinet';
      if (obj.subtype === 'corner') return 'Corner cabinet';
      if (obj.subtype === 'island') return 'Island';
      return 'Base cabinet';
    }
    if (obj.type === 'fixture') {
      if (obj.subtype === 'tub') return 'Tub';
      if (obj.subtype === 'shower') return 'Shower';
      if (obj.subtype === 'vanity') return 'Vanity';
      return 'Toilet';
    }
    if (obj.type === 'furniture') {
      if (obj.subtype === 'bed') return 'Bed';
      if (obj.subtype === 'dining_table') return 'Dining table';
      return 'Sofa';
    }
    return Math.round(obj.width) + '″ door';
  }

  function makeWallId(polygonId, startIdx, endIdx) {
    return 'wall_' + polygonId + '_' + startIdx + '_' + endIdx;
  }

  function parseWallId(wallId) {
    var match = String(wallId || '').match(/^wall_(.+)_(\d+)_(\d+)$/);
    if (!match) return null;
    return {
      polygonId: match[1],
      start: parseInt(match[2], 10),
      end: parseInt(match[3], 10)
    };
  }

  function findPolygonEntryById(polygonId) {
    var found = null;
    allPolygons().some(function (entry) {
      if (entry.polygon.id === polygonId) {
        found = entry;
        return true;
      }
      return false;
    });
    return found;
  }

  function wallLengthInches(a, b) {
    var ppf = pixelsPerFoot() || 12;
    return (dist(a, b) / ppf) * INCHES_PER_FOOT;
  }

  function inchesToPixels(inches) {
    return (inches / INCHES_PER_FOOT) * (pixelsPerFoot() || 12);
  }

  function pixelsToInches(px) {
    return (px / (pixelsPerFoot() || 12)) * INCHES_PER_FOOT;
  }

  function catalogPlacesOnFloor(item) {
    return !!(item && (item.place === 'floor' || FREE_OBJECT_TYPES[item.type]));
  }

  function listClosedWalls() {
    var walls = [];
    allClosedPolygons().forEach(function (entry) {
      var verts = entry.polygon.vertices;
      var n = verts.length;
      for (var i = 0; i < n; i++) {
        var j = (i + 1) % n;
        walls.push({
          wallId: makeWallId(entry.polygon.id, i, j),
          polygonId: entry.polygon.id,
          groupId: entry.group.id,
          start: i,
          end: j,
          a: verts[i],
          b: verts[j],
          verts: verts,
          color: entry.group.color
        });
      }
    });
    return walls;
  }

  function resolveWall(wallId) {
    var parsed = parseWallId(wallId);
    if (!parsed) return null;
    var entry = findPolygonEntryById(parsed.polygonId);
    if (!entry || !entry.polygon.closed || entry.polygon.vertices.length < 3) return null;
    var verts = entry.polygon.vertices;
    if (parsed.start < 0 || parsed.start >= verts.length) return null;
    var end = (parsed.start + 1) % verts.length;
    return {
      wallId: makeWallId(entry.polygon.id, parsed.start, end),
      polygonId: entry.polygon.id,
      groupId: entry.group.id,
      start: parsed.start,
      end: end,
      a: verts[parsed.start],
      b: verts[end],
      verts: verts,
      color: entry.group.color
    };
  }

  function projectOntoWall(pos, wall) {
    var dx = wall.b.x - wall.a.x;
    var dy = wall.b.y - wall.a.y;
    var lenSq = dx * dx + dy * dy;
    if (lenSq < 1e-9) return null;
    var t = ((pos.x - wall.a.x) * dx + (pos.y - wall.a.y) * dy) / lenSq;
    t = Math.max(0, Math.min(1, t));
    var point = { x: wall.a.x + t * dx, y: wall.a.y + t * dy };
    return {
      wall: wall,
      t: t,
      point: point,
      dist: dist(pos, point),
      lengthIn: wallLengthInches(wall.a, wall.b)
    };
  }

  function snapToNearestWall(pos, threshold) {
    if (!pos) return null;
    var maxDist = threshold != null ? threshold : hitTolerance(OBJECT_SNAP_PX);
    var best = null;
    listClosedWalls().forEach(function (wall) {
      var hit = projectOntoWall(pos, wall);
      if (!hit) return;
      if (hit.dist <= maxDist && (!best || hit.dist < best.dist)) {
        best = hit;
      }
    });
    return best;
  }

  function objectPlacement(obj) {
    if (isFreeObject(obj) && obj.x != null && obj.y != null) {
      var width = Math.max(OBJECT_MIN_WIDTH_IN, Math.min(OBJECT_MAX_WIDTH_IN, obj.width || 36));
      var depth = Math.max(OBJECT_MIN_WIDTH_IN, Math.min(OBJECT_MAX_WIDTH_IN, obj.depth || 24));
      var rot = obj.rotation || 0;
      return {
        wall: null,
        a: { x: obj.x, y: obj.y },
        b: { x: obj.x, y: obj.y },
        mid: { x: obj.x, y: obj.y },
        angle: rot * Math.PI / 180,
        width: width,
        depth: depth,
        offset: 0,
        lengthIn: 0,
        widthPx: inchesToPixels(width),
        depthPx: inchesToPixels(depth),
        inward: 1,
        free: true
      };
    }
    var wall = resolveWall(obj.wall_id);
    if (!wall) return null;
    var lengthIn = wallLengthInches(wall.a, wall.b);
    if (lengthIn < 1e-6) return null;
    var width = Math.max(OBJECT_MIN_WIDTH_IN, Math.min(OBJECT_MAX_WIDTH_IN, obj.width || 36));
    var maxOffset = Math.max(0, lengthIn - width);
    var offset = Math.max(0, Math.min(maxOffset, obj.offset || 0));
    var t0 = offset / lengthIn;
    var t1 = (offset + width) / lengthIn;
    var a = {
      x: wall.a.x + (wall.b.x - wall.a.x) * t0,
      y: wall.a.y + (wall.b.y - wall.a.y) * t0
    };
    var b = {
      x: wall.a.x + (wall.b.x - wall.a.x) * t1,
      y: wall.a.y + (wall.b.y - wall.a.y) * t1
    };
    var mid = { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
    var angle = Math.atan2(wall.b.y - wall.a.y, wall.b.x - wall.a.x);
    var inward = wallInwardSign(wall);
    return {
      wall: wall,
      a: a,
      b: b,
      mid: mid,
      angle: angle,
      width: width,
      offset: offset,
      lengthIn: lengthIn,
      widthPx: inchesToPixels(width),
      inward: inward
    };
  }

  function wallInwardSign(wall) {
    var dx = wall.b.x - wall.a.x;
    var dy = wall.b.y - wall.a.y;
    var len = Math.hypot(dx, dy) || 1;
    var nx = -dy / len;
    var ny = dx / len;
    var probe = {
      x: (wall.a.x + wall.b.x) / 2 + nx * 8,
      y: (wall.a.y + wall.b.y) / 2 + ny * 8
    };
    return pointInPolygon(probe, wall.verts) ? 1 : -1;
  }

  function clampObjectToWall(obj, wall) {
    var lengthIn = wallLengthInches(wall.a, wall.b);
    obj.width = Math.max(OBJECT_MIN_WIDTH_IN, Math.min(OBJECT_MAX_WIDTH_IN, obj.width || 36));
    if (obj.width > lengthIn - 1) obj.width = Math.max(OBJECT_MIN_WIDTH_IN, lengthIn - 1);
    obj.offset = Math.max(0, Math.min(Math.max(0, lengthIn - obj.width), obj.offset || 0));
    return obj;
  }

  function applySnapToObject(obj, snap) {
    obj.wall_id = snap.wall.wallId;
    var width = obj.width || 36;
    var offset = (snap.t * snap.lengthIn) - (width / 2);
    obj.offset = offset;
    clampObjectToWall(obj, snap.wall);
  }

  function createObjectFromCatalog(item, snap, pos) {
    var obj = {
      id: 'obj_' + state.nextObjectId,
      type: item.type,
      subtype: item.subtype,
      width: item.width,
      offset: 0,
      swing: 'left_in',
      hinge: 'left',
      casing_sides: RA.defaultCasingSides(item.type),
      door_face: item.type && String(item.type).toLowerCase() === 'door' ? 'interior' : undefined
    };
    state.nextObjectId += 1;
    if (item.place === 'floor' || FREE_OBJECT_TYPES[item.type]) {
      var size = item.depth != null ? { width: item.width, depth: item.depth } : defaultFreeSize(item.type, item.subtype);
      obj.width = size.width;
      obj.depth = size.depth;
      obj.rotation = 0;
      obj.x = pos ? pos.x : (snap && snap.point ? snap.point.x : 80);
      obj.y = pos ? pos.y : (snap && snap.point ? snap.point.y : 80);
      return obj;
    }
    obj.wall_id = snap.wall.wallId;
    if (item.type === 'window' || item.type === 'opening') {
      obj.height = item.type === 'window' ? DEFAULT_WINDOW_HEIGHT_IN : 84;
      obj.sill_height = item.type === 'window' ? 36 : 0;
    }
    applySnapToObject(obj, snap);
    return obj;
  }

  function defaultFreeSize(type, subtype) {
    if (type === 'stairs' && subtype === 'l') return { width: 72, depth: 72 };
    if (type === 'stairs' && subtype === 'u') return { width: 72, depth: 96 };
    if (type === 'stairs') return { width: 36, depth: 120 };
    if (type === 'cabinet' && subtype === 'upper') return { width: 36, depth: 12 };
    if (type === 'cabinet' && subtype === 'corner') return { width: 24, depth: 24 };
    if (type === 'cabinet' && subtype === 'island') return { width: 48, depth: 36 };
    if (type === 'cabinet') return { width: 36, depth: 24 };
    if (type === 'fixture' && subtype === 'tub') return { width: 60, depth: 32 };
    if (type === 'fixture' && subtype === 'shower') return { width: 36, depth: 36 };
    if (type === 'fixture' && subtype === 'vanity') return { width: 36, depth: 21 };
    if (type === 'fixture') return { width: 20, depth: 30 };
    if (type === 'furniture' && subtype === 'bed') return { width: 60, depth: 80 };
    if (type === 'furniture' && subtype === 'dining_table') return { width: 72, depth: 36 };
    return { width: 84, depth: 36 };
  }

  function selectedObject() {
    if (!state.selectedObjectId) return null;
    return state.objects.find(function (o) { return o.id === state.selectedObjectId; }) || null;
  }

  function findObjectAtPoint(pos) {
    if (!pos) return null;
    var hit = null;
    var best = Infinity;
    state.objects.forEach(function (obj) {
      if (isFreeObject(obj)) {
        var box = freeObjectBox(obj);
        if (!box) return;
        if (pos.x >= box.x0 && pos.x <= box.x1 && pos.y >= box.y0 && pos.y <= box.y1) {
          var d = dist(pos, { x: obj.x, y: obj.y });
          if (d < best) {
            best = d;
            hit = obj;
          }
        }
        return;
      }
      var place = objectPlacement(obj);
      if (!place) return;
      var dWall = distanceToSegment(pos, place.a, place.b);
      if (dWall <= hitTolerance(objectHitRadius()) && dWall < best) {
        best = dWall;
        hit = obj;
      }
    });
    return hit;
  }

  function freeObjectBox(obj) {
    var w = inchesToPixels(obj.width || 36);
    var d = inchesToPixels(obj.depth || 24);
    var rad = (obj.rotation || 0) * Math.PI / 180;
    var hw = w / 2;
    var hd = d / 2;
    var cos = Math.cos(rad);
    var sin = Math.sin(rad);
    var corners = [
      { x: obj.x + (-hw) * cos - (-hd) * sin, y: obj.y + (-hw) * sin + (-hd) * cos },
      { x: obj.x + (hw) * cos - (-hd) * sin, y: obj.y + (hw) * sin + (-hd) * cos },
      { x: obj.x + (hw) * cos - (hd) * sin, y: obj.y + (hw) * sin + (hd) * cos },
      { x: obj.x + (-hw) * cos - (hd) * sin, y: obj.y + (-hw) * sin + (hd) * cos }
    ];
    var x0 = Math.min(corners[0].x, corners[1].x, corners[2].x, corners[3].x);
    var y0 = Math.min(corners[0].y, corners[1].y, corners[2].y, corners[3].y);
    var x1 = Math.max(corners[0].x, corners[1].x, corners[2].x, corners[3].x);
    var y1 = Math.max(corners[0].y, corners[1].y, corners[2].y, corners[3].y);
    var pad = hitTolerance(8);
    return { x0: x0 - pad, y0: y0 - pad, x1: x1 + pad, y1: y1 + pad, w: w, d: d };
  }

  function objectsOnWall(wallId) {
    return state.objects.filter(function (obj) { return obj.wall_id === wallId; });
  }

  function rematchObjectToNearestWall(obj) {
    if (isFreeObject(obj)) return true;
    var place = objectPlacement(obj);
    var pos = place ? place.mid : null;
    if (!pos) {
      var parsed = parseWallId(obj.wall_id);
      var entry = parsed ? findPolygonEntryById(parsed.polygonId) : null;
      if (entry && entry.polygon.vertices.length) {
        pos = polygonCentroid(entry.polygon.vertices);
      }
    }
    if (!pos) return false;
    var snap = snapToNearestWall(pos, Infinity);
    if (!snap) return false;
    applySnapToObject(obj, snap);
    return true;
  }

  function rematchAllObjects() {
    state.objects = state.objects.filter(function (obj) {
      return rematchObjectToNearestWall(obj);
    });
    if (state.selectedObjectId && !selectedObject()) {
      clearObjectSelection();
    }
  }

  function removeObjectsForPolygon(polygonId) {
    state.objects = state.objects.filter(function (obj) {
      var parsed = parseWallId(obj.wall_id);
      return !parsed || parsed.polygonId !== polygonId;
    });
    if (state.selectedObjectId && !selectedObject()) clearObjectSelection();
  }

  function syncObjectIdCounter() {
    var max = 0;
    state.objects.forEach(function (obj) {
      var n = parseInt(String(obj.id).replace(/\D/g, ''), 10);
      if (n > max) max = n;
    });
    state.nextObjectId = Math.max(state.nextObjectId, max + 1, 1);
  }

  function findGroupById(groupId) {
    return state.groups.find(function (g) { return g.id === groupId; }) || null;
  }

  function findPolygonInGroup(group, polygonId) {
    if (!group) return null;
    return group.polygons.find(function (p) { return p.id === polygonId; }) || null;
  }

  function activeGroup() {
    return findGroupById(state.activeGroupId);
  }

  function activePolygon() {
    var group = activeGroup();
    return group ? findPolygonInGroup(group, state.activePolygonId) : null;
  }

  function ensureInitialCanvas() {
    if (!state.groups.length) {
      startNewGroup(false);
    }
    if (!activePolygon()) {
      var group = activeGroup() || state.groups[0];
      if (group && group.polygons.length) {
        state.activeGroupId = group.id;
        state.activePolygonId = group.polygons[0].id;
      } else if (group) {
        startNewPolygon(group.id);
      }
    }
  }

  function activeVertices() {
    ensureInitialCanvas();
    var poly = activePolygon();
    return poly ? poly.vertices : [];
  }

  function isActiveClosed() {
    var poly = activePolygon();
    return poly ? !!poly.closed : false;
  }

  function setActiveClosed(closed) {
    var poly = activePolygon();
    if (poly) poly.closed = !!closed;
  }

  function setActiveVertices(vertices) {
    var poly = activePolygon();
    if (!poly) return;
    poly.vertices = (vertices || []).map(function (v) {
      return { x: parseFloat(v.x), y: parseFloat(v.y) };
    });
  }

  function paletteColorForIndex(index) {
    return GROUP_COLORS[index % GROUP_COLORS.length];
  }

  function createPolygon() {
    var id = 'p' + state.nextPolygonId;
    state.nextPolygonId += 1;
    return { id: id, vertices: [], closed: false, name: '' };
  }

  function polygonName(poly) {
    return poly && typeof poly.name === 'string' ? poly.name.trim() : '';
  }

  function sanitizeAreaName(raw) {
    return String(raw || '').replace(/\s+/g, ' ').trim().slice(0, AREA_NAME_MAX);
  }

  function createGroup(color) {
    var id = 'g' + state.nextGroupId;
    state.nextGroupId += 1;
    var poly = createPolygon();
    return {
      id: id,
      color: color || paletteColorForIndex(state.groups.length),
      polygons: [poly]
    };
  }

  function startNewGroup(selectIt) {
    var group = createGroup();
    state.groups.push(group);
    if (selectIt !== false) {
      state.activeGroupId = group.id;
      state.activePolygonId = group.polygons[0].id;
      clearWallSelection();
      clearVertexSelection();
    }
    return group;
  }

  function startNewPolygon(groupId) {
    var group = groupId ? findGroupById(groupId) : activeGroup();
    if (!group) group = startNewGroup();
    var poly = createPolygon();
    group.polygons.push(poly);
    state.activeGroupId = group.id;
    state.activePolygonId = poly.id;
    clearWallSelection();
    clearVertexSelection();
    return poly;
  }

  function resetCanvasGroups() {
    state.groups = [];
    state.activeGroupId = null;
    state.activePolygonId = null;
    state.nextGroupId = 1;
    state.nextPolygonId = 1;
    state.objects = [];
    state.nextObjectId = 1;
    state.selectedObjectId = null;
    disarmObjectPlacement();
  }

  function allPolygons() {
    var out = [];
    state.groups.forEach(function (group) {
      (group.polygons || []).forEach(function (poly) {
        out.push({ group: group, polygon: poly });
      });
    });
    return out;
  }

  function allClosedPolygons() {
    return allPolygons().filter(function (entry) {
      return entry.polygon.closed && entry.polygon.vertices.length >= 3;
    });
  }

  function hasAnyClosedPolygon() {
    return allClosedPolygons().length > 0;
  }

  function groupAreaSqFt(group) {
    var total = 0;
    (group.polygons || []).forEach(function (poly) {
      if (poly.closed && poly.vertices.length >= 3) {
        var sqft = areaSqFt(poly.vertices);
        if (sqft !== null) total += sqft;
      }
    });
    return Math.round(total * 100) / 100;
  }

  function canvasTotalAreaSqFt() {
    var total = 0;
    state.groups.forEach(function (group) {
      total += groupAreaSqFt(group);
    });
    return Math.round(total * 100) / 100;
  }

  function polygonPerimeterLf(vertices) {
    var ppf = pixelsPerFoot();
    if (!ppf || !vertices || vertices.length < 3) return 0;
    var sum = 0;
    for (var i = 0; i < vertices.length; i++) {
      sum += dist(vertices[i], vertices[(i + 1) % vertices.length]) / ppf;
    }
    return Math.round(sum * 100) / 100;
  }

  function canvasPerimeterLf() {
    var total = 0;
    allClosedPolygons().forEach(function (entry) {
      if (!isSimplePolygon(entry.polygon.vertices)) return;
      total += polygonPerimeterLf(entry.polygon.vertices);
    });
    return Math.round(total * 100) / 100;
  }

  function wallHeightFt() {
    var n = state.wallHeightFt;
    if (!isFinite(n) || n < 1) return DEFAULT_WALL_HEIGHT_FT;
    if (n > 20) return 20;
    return Math.round(n * 100) / 100;
  }

  function ceilingHeightFt() {
    return RA.ceilingHeightFt(state.ceilingHeightFt, wallHeightFt());
  }

  function flooringWastePercent() {
    return RA.normalizeWastePercent(state.flooringWastePercent);
  }

  function finishSnapshot() {
    return {
      wallHeightFt: state.wallHeightFt,
      ceilingHeightFt: state.ceilingHeightFt,
      flooringWastePercent: state.flooringWastePercent,
      flooringDirection: state.flooringDirection,
      flooringBoxSqFt: state.flooringBoxSqFt
    };
  }

  function applyFinishSnapshot(snap) {
    if (!snap) return;
    if (snap.wallHeightFt != null && isFinite(snap.wallHeightFt)) state.wallHeightFt = snap.wallHeightFt;
    state.ceilingHeightFt = snap.ceilingHeightFt;
    if (snap.flooringWastePercent != null) state.flooringWastePercent = snap.flooringWastePercent;
    if (snap.flooringDirection != null) state.flooringDirection = snap.flooringDirection;
    if (snap.flooringBoxSqFt != null) state.flooringBoxSqFt = snap.flooringBoxSqFt;
    syncFinishInputs(true);
  }

  function flooringBoxSqFt() {
    return RA.normalizeBoxSqFt(state.flooringBoxSqFt);
  }

  function flooringDirectionDeg() {
    return RA.normalizeDirection(state.flooringDirection);
  }

  function syncWallHeightInput(force) {
    if (!els.wallHeight) return;
    if (!force && document.activeElement === els.wallHeight) return;
    els.wallHeight.value = RA.wallHeightDisplay(state.wallHeightFt, isMetricDisplay(), fmt);
  }

  function syncCeilingHeightInput(force) {
    if (!els.ceilingHeight) return;
    if (!force && document.activeElement === els.ceilingHeight) return;
    els.ceilingHeight.value = RA.wallHeightDisplay(ceilingHeightFt(), isMetricDisplay(), fmt);
  }

  function syncFinishInputs(force) {
    syncWallHeightInput(force);
    syncCeilingHeightInput(force);
    if (els.floorWaste && (force || document.activeElement !== els.floorWaste)) {
      els.floorWaste.value = String(flooringWastePercent());
    }
    if (els.floorBox && (force || document.activeElement !== els.floorBox)) {
      els.floorBox.value = String(flooringBoxSqFt());
    }
    raAll('[data-ra-floor-dir]').forEach(function (btn) {
      var on = parseInt(btn.getAttribute('data-ra-floor-dir'), 10) === flooringDirectionDeg();
      btn.classList.toggle('is-active', on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }

  function setWallHeight(value, recordUndo) {
    var n = parseFloat(value);
    if (!isFinite(n) || n < 1) n = DEFAULT_WALL_HEIGHT_FT;
    if (n > 20) n = 20;
    var prev = state.wallHeightFt;
    var next = Math.round(n * 100) / 100;
    if (recordUndo && Math.abs(next - prev) > 0.0001) pushUndoSnapshot();
    state.wallHeightFt = next;
    if (state.ceilingHeightFt == null || Math.abs((state.ceilingHeightFt || 0) - prev) < 0.001) {
      state.ceilingHeightFt = null;
    }
    syncFinishInputs(true);
  }

  function setCeilingHeight(value, recordUndo) {
    var n = parseFloat(value);
    var next = null;
    if (isFinite(n) && n >= 1) {
      if (n > 20) n = 20;
      next = Math.round(n * 100) / 100;
    }
    if (recordUndo) {
      var prev = state.ceilingHeightFt;
      if ((prev == null && next != null) || (prev != null && next == null) || (prev != null && next != null && Math.abs(prev - next) > 0.0001)) {
        pushUndoSnapshot();
      }
    }
    state.ceilingHeightFt = next;
    syncCeilingHeightInput(true);
  }

  function setFlooringWaste(value, recordUndo) {
    var parsed = RA.parseWastePercent(value);
    if (!parsed.ok) {
      setFeedback(parsed.error, true);
      syncFinishInputs(true);
      return false;
    }
    if (recordUndo) pushUndoSnapshot();
    state.flooringWastePercent = parsed.value;
    syncFinishInputs(true);
    return true;
  }

  function setFlooringBox(value, recordUndo) {
    var next = RA.normalizeBoxSqFt(value);
    if (recordUndo && Math.abs((state.flooringBoxSqFt || 0) - next) > 0.0001) pushUndoSnapshot();
    state.flooringBoxSqFt = next;
    syncFinishInputs(true);
  }

  function setFlooringDirection(deg, recordUndo) {
    var next = RA.normalizeDirection(deg);
    if (recordUndo && state.flooringDirection !== next) pushUndoSnapshot();
    state.flooringDirection = next;
    syncFinishInputs(true);
    if (state.stage) redrawFlooringDirection();
  }

  function resetFinishSettings() {
    state.ceilingHeightFt = null;
    state.flooringWastePercent = RA.DEFAULT_WASTE_PERCENT || 10;
    state.flooringDirection = RA.DIRECTION_LENGTH || 0;
    state.flooringBoxSqFt = RA.DEFAULT_BOX_SQFT || 20;
    syncFinishInputs(true);
  }

  function wallHeightFtFromValue(value) {
    var parsed = RA.parseWallHeight(value, isMetricDisplay());
    if (parsed && parsed.warning) {
      setFeedback(parsed.warning, true);
      return null;
    }
    if (!parsed || !isFinite(parsed.feet) || parsed.feet < 1) return DEFAULT_WALL_HEIGHT_FT;
    if (parsed.feet > 20) return 20;
    return Math.round(parsed.feet * 100) / 100;
  }

  function objectHeightInches(obj, heightFt) {
    return RA.objectHeightInches(obj, heightFt);
  }

  function metricObjects() {
    return (state.objects || []).filter(function (obj) {
      return obj && isWallObject(obj) && resolveWall(obj.wall_id);
    });
  }

  function wallThicknessIn() {
    var n = parseFloat(state.wallThicknessIn);
    if (!isFinite(n) || n < 0) return 0;
    if (n > 12) return 12;
    return Math.round(n * 100) / 100;
  }

  function setWallThickness(value, skipFeedback) {
    var n = parseFloat(value);
    if (!isFinite(n) || n < 0) n = 0;
    if (n > 12) n = 12;
    state.wallThicknessIn = Math.round(n * 100) / 100;
    syncThicknessUi();
    if (!skipFeedback) {
      redrawAll();
    }
  }

  function thicknessIsPreset(n) {
    return WALL_THICKNESS_PRESETS.some(function (p) {
      return Math.abs(p - n) < 0.001;
    });
  }

  function syncThicknessUi() {
    var n = wallThicknessIn();
    var preset = thicknessIsPreset(n);
    root.querySelectorAll('[data-ra-thickness]').forEach(function (btn) {
      var val = btn.getAttribute('data-ra-thickness');
      var on = false;
      if (val === 'custom') on = n > 0 && !preset;
      else on = Math.abs(parseFloat(val) - n) < 0.001;
      btn.classList.toggle('is-active', on);
    });
    if (els.thicknessCustom) show(els.thicknessCustom, n > 0 && !preset);
    if (els.thicknessCustomInput && document.activeElement !== els.thicknessCustomInput) {
      els.thicknessCustomInput.value = n > 0 && !preset ? String(n) : '';
    }
  }

  function setWallsOpen(open) {
    if (!els.wallsPanel) return;
    els.wallsPanel.classList.toggle('is-open', !!open);
    if (els.wallsToggle) {
      els.wallsToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    syncPhoneSheetForDock();
  }

  function toggleWallsOpen() {
    var open = els.wallsPanel && els.wallsPanel.classList.contains('is-open');
    setWallsOpen(!open);
    if (!open && isNarrowPalette()) setPaletteOpen(false);
  }

  function computeWallMetrics() {
    var perimeter = canvasPerimeterLf();
    var height = wallHeightFt();
    var floor = canvasTotalAreaSqFt();
    var doorCount = 0;
    var windowCount = 0;
    var openingCount = 0;
    var baseboardIn = 0;
    var wallDeduction = 0;
    metricObjects().forEach(function (obj) {
      var width = parseFloat(obj.width) || 0;
      if (width <= 0) return;
      var objHeight = objectHeightInches(obj, height);
      var area = (width / INCHES_PER_FOOT) * (objHeight / INCHES_PER_FOOT);
      var kind = objectType(obj);
      if (kind === 'door') {
        doorCount += 1;
        baseboardIn += width;
        wallDeduction += area;
      } else if (kind === 'window') {
        windowCount += 1;
        wallDeduction += area;
      } else if (kind === 'opening') {
        openingCount += 1;
        baseboardIn += width;
      }
    });
    var shared = detectSharedWalls();
    state.sharedPairs = shared;
    var sharedLf = 0;
    shared.forEach(function (pair) { sharedLf += pair.overlapLf; });
    sharedLf = Math.round(sharedLf * 100) / 100;
    var framing = Math.max(0, Math.round((perimeter - sharedLf) * 100) / 100);
    var thickness = wallThicknessIn();
    var exterior = Math.max(0, perimeter - sharedLf * 2);
    var outside = thickness > 0
      ? Math.round((floor + exterior * (thickness / INCHES_PER_FOOT)) * 100) / 100
      : floor;
    var library = libraryMetrics();
    var baseboard = Math.max(0, Math.round((perimeter - baseboardIn / INCHES_PER_FOOT) * 100) / 100);
    var wallArea = Math.max(0, Math.round((perimeter * height - wallDeduction) * 100) / 100);
    var ceiling = ceilingHeightFt();
    var walls = perWallNetAreas(ceiling);
    var waste = flooringWastePercent();
    var box = flooringBoxSqFt();
    return {
      area_sqft: floor,
      inside_area_sqft: floor,
      outside_area_sqft: outside,
      perimeter_lf: perimeter,
      baseboard_lf: baseboard,
      wall_height_ft: height,
      wall_area_sqft: walls.reduce(function (sum, wall) { return sum + wall.net_area_sqft; }, 0),
      door_count: doorCount,
      window_count: windowCount,
      opening_count: openingCount,
      drywall_openings: doorCount + windowCount + openingCount,
      wall_thickness_in: thickness,
      shared_wall_lf: sharedLf,
      framing_lf: framing,
      cabinet_base_lf: library.cabinet_base_lf,
      cabinet_upper_lf: library.cabinet_upper_lf,
      stairs_count: library.stairs_count,
      stairs_treads: library.stairs_treads,
      ceiling_height_ft: ceiling,
      ceiling_area_sqft: RA.ceilingAreaSqFt(floor),
      net_wall_area_sqft: walls.reduce(function (sum, wall) { return sum + wall.net_area_sqft; }, 0),
      walls: walls,
      flooring_waste_percent: waste,
      flooring_direction: flooringDirectionDeg(),
      flooring_box_sqft: box,
      flooring_boxes: RA.flooringBoxes(floor, waste, box),
      flooring_with_waste_sqft: RA.flooringWithWasteSqFt(floor, waste),
      casing_lf: RA.totalCasingLf(metricObjects(), ceiling)
    };
  }

  function perWallNetAreas(ceiling) {
    var height = ceiling != null ? ceiling : ceilingHeightFt();
    return RA.perWallNetAreas({
      groups: state.groups,
      objects: metricObjects()
    }, pixelsPerFoot() || 0, height);
  }

  function libraryMetrics() {
    var baseIn = 0;
    var upperIn = 0;
    var stairsCount = 0;
    var treads = 0;
    (state.objects || []).forEach(function (obj) {
      if (!obj) return;
      if (obj.type === 'cabinet') {
        var w = parseFloat(obj.width) || 0;
        var d = parseFloat(obj.depth) || 0;
        if (obj.subtype === 'upper') upperIn += w;
        else if (obj.subtype === 'corner') baseIn += w + d;
        else baseIn += w;
      } else if (obj.type === 'stairs') {
        stairsCount += 1;
        var depth = parseFloat(obj.depth) || 120;
        var base = Math.max(1, Math.round(depth / 10));
        if (obj.subtype === 'l') treads += base + 3;
        else if (obj.subtype === 'u') treads += base + 6;
        else treads += base;
      }
    });
    return {
      cabinet_base_lf: Math.round(baseIn / INCHES_PER_FOOT * 100) / 100,
      cabinet_upper_lf: Math.round(upperIn / INCHES_PER_FOOT * 100) / 100,
      stairs_count: stairsCount,
      stairs_treads: treads
    };
  }

  function detectSharedWalls() {
    var walls = listClosedWalls();
    var pairs = [];
    var seen = {};
    var ppf = pixelsPerFoot() || 12;
    var gap = Math.max(6, (ppf / 12) * 2);
    var minOverlap = Math.max(gap, (ppf / 12) * SHARED_MIN_OVERLAP_IN);
    var angTol = SHARED_WALL_ANGLE_DEG * Math.PI / 180;
    for (var i = 0; i < walls.length; i++) {
      for (var j = i + 1; j < walls.length; j++) {
        var wa = walls[i];
        var wb = walls[j];
        if (wa.polygonId === wb.polygonId) continue;
        var hit = sharedWallOverlap(wa, wb, gap, minOverlap, ppf, angTol);
        if (!hit) continue;
        var key = wa.wallId < wb.wallId ? wa.wallId + '|' + wb.wallId : wb.wallId + '|' + wa.wallId;
        if (seen[key]) continue;
        seen[key] = true;
        pairs.push(hit);
      }
    }
    return pairs;
  }

  function sharedWallOverlap(wa, wb, gap, minOverlap, ppf, angTol) {
    var dxa = wa.b.x - wa.a.x;
    var dya = wa.b.y - wa.a.y;
    var lenA = Math.hypot(dxa, dya);
    if (lenA < minOverlap) return null;
    var dxb = wb.b.x - wb.a.x;
    var dyb = wb.b.y - wb.a.y;
    var lenB = Math.hypot(dxb, dyb);
    if (lenB < minOverlap) return null;
    var angA = Math.atan2(dya, dxa);
    var angB = Math.atan2(dyb, dxb);
    var delta = Math.abs(Math.atan2(Math.sin(angA - angB), Math.cos(angA - angB)));
    if (delta > angTol && Math.abs(delta - Math.PI) > angTol) return null;
    function lineDist(p, a, b) {
      var dx = b.x - a.x;
      var dy = b.y - a.y;
      var ls = dx * dx + dy * dy;
      if (ls < 1e-12) return dist(p, a);
      var t = ((p.x - a.x) * dx + (p.y - a.y) * dy) / ls;
      return dist(p, { x: a.x + t * dx, y: a.y + t * dy });
    }
    if (Math.max(lineDist(wb.a, wa.a, wa.b), lineDist(wb.b, wa.a, wa.b)) > gap) return null;
    var ux = dxa / lenA;
    var uy = dya / lenA;
    var tb0 = (wb.a.x - wa.a.x) * ux + (wb.a.y - wa.a.y) * uy;
    var tb1 = (wb.b.x - wa.a.x) * ux + (wb.b.y - wa.a.y) * uy;
    var overlapStart = Math.max(0, Math.min(tb0, tb1));
    var overlapEnd = Math.min(lenA, Math.max(tb0, tb1));
    var overlapPx = overlapEnd - overlapStart;
    if (overlapPx < minOverlap) return null;
    return {
      a: wa,
      b: wb,
      overlapPx: overlapPx,
      overlapLf: Math.round((overlapPx / ppf) * 100) / 100
    };
  }

  function linkedVerticesFor(polygonId, vertexIndex) {
    var links = [];
    var gap = Math.max(6, ((pixelsPerFoot() || 12) / 12) * 2);
    var entry = findPolygonEntryById(polygonId);
    if (!entry) return links;
    var v = entry.polygon.vertices[vertexIndex];
    if (!v) return links;
    detectSharedWalls().forEach(function (pair) {
      var other = null;
      var mine = null;
      if (pair.a.polygonId === polygonId) { mine = pair.a; other = pair.b; }
      else if (pair.b.polygonId === polygonId) { mine = pair.b; other = pair.a; }
      if (!mine || !other) return;
      if (vertexIndex !== mine.start && vertexIndex !== mine.end) return;
      var otherEntry = findPolygonEntryById(other.polygonId);
      if (!otherEntry) return;
      [other.start, other.end].forEach(function (idx) {
        var ov = otherEntry.polygon.vertices[idx];
        if (ov && dist(v, ov) <= gap) {
          links.push({ polygonId: other.polygonId, index: idx });
        }
      });
    });
    return links;
  }

  function applyLinkedVertexMove(polygonId, vertexIndex, newPos) {
    linkedVerticesFor(polygonId, vertexIndex).forEach(function (link) {
      var other = findPolygonEntryById(link.polygonId);
      if (!other || !other.polygon.vertices[link.index]) return;
      other.polygon.vertices[link.index] = { x: newPos.x, y: newPos.y };
    });
  }

  function formatMetricQty(value) {
    return fmt(value, 2);
  }

  function pointInPolygon(point, vertices) {
    var inside = false;
    var n = vertices.length;
    if (n < 3) return false;
    for (var i = 0, j = n - 1; i < n; j = i++) {
      var xi = vertices[i].x;
      var yi = vertices[i].y;
      var xj = vertices[j].x;
      var yj = vertices[j].y;
      var intersect = ((yi > point.y) !== (yj > point.y))
        && (point.x < ((xj - xi) * (point.y - yi)) / (yj - yi + 1e-12) + xi);
      if (intersect) inside = !inside;
    }
    return inside;
  }

  function distanceToSegment(point, a, b) {
    var dx = b.x - a.x;
    var dy = b.y - a.y;
    if (Math.abs(dx) < 1e-9 && Math.abs(dy) < 1e-9) return dist(point, a);
    var t = ((point.x - a.x) * dx + (point.y - a.y) * dy) / (dx * dx + dy * dy);
    t = Math.max(0, Math.min(1, t));
    return dist(point, { x: a.x + t * dx, y: a.y + t * dy });
  }

  function polygonAtPoint(pos) {
    var hit = null;
    var bestDist = Infinity;
    allPolygons().forEach(function (entry) {
      var poly = entry.polygon;
      var verts = poly.vertices;
      if (verts.length < 2) return;
      if (poly.closed && verts.length >= 3 && pointInPolygon(pos, verts)) {
        hit = entry;
        bestDist = 0;
        return;
      }
      var wallTotal = poly.closed ? verts.length : verts.length - 1;
      for (var i = 0; i < wallTotal; i++) {
        var a = verts[i];
        var b = verts[(i + 1) % verts.length];
        var d = distanceToSegment(pos, a, b);
        if (d <= hitTolerance(WALL_HIT) && d < bestDist) {
          bestDist = d;
          hit = entry;
        }
      }
    });
    return hit;
  }

  function activatePolygon(groupId, polygonId) {
    if (state.activeGroupId === groupId && state.activePolygonId === polygonId) return;
    state.activeGroupId = groupId;
    state.activePolygonId = polygonId;
    clearWallSelection();
    clearVertexSelection();
    updateGroupChip();
    redrawAll();
  }

  function deleteActivePolygon() {
    if (geometryLocked()) {
      warnGeometryLocked();
      return;
    }
    var group = activeGroup();
    var poly = activePolygon();
    if (!group || !poly) return;
    pushUndoSnapshot();
    if (group.polygons.length === 1 && state.groups.length === 1 && !poly.closed && !poly.vertices.length) {
      discardLastUndoSnapshot();
      return;
    }
    removeObjectsForPolygon(poly.id);
    group.polygons = group.polygons.filter(function (p) { return p.id !== poly.id; });
    if (!group.polygons.length) {
      state.groups = state.groups.filter(function (g) { return g.id !== group.id; });
    }
    var next = allPolygons()[0];
    if (next) {
      state.activeGroupId = next.group.id;
      state.activePolygonId = next.polygon.id;
    } else {
      state.activeGroupId = null;
      state.activePolygonId = null;
      ensureInitialCanvas();
    }
    clearWallSelection();
    clearVertexSelection();
    if (!allPolygons().length) {
      ensureInitialCanvas();
    }
    redrawAll();
  }

  function serializeCanvas() {
    var groups = [];
    state.groups.forEach(function (group) {
      var polygons = [];
      (group.polygons || []).forEach(function (poly) {
        if (!poly.closed || poly.vertices.length < 3) return;
        var item = {
          id: poly.id,
          vertices: poly.vertices.map(function (v) {
            return { x: Math.round(v.x * 1000) / 1000, y: Math.round(v.y * 1000) / 1000 };
          })
        };
        var name = polygonName(poly);
        if (name) item.name = name.slice(0, AREA_NAME_MAX);
        polygons.push(item);
      });
      if (polygons.length) {
        groups.push({
          id: group.id,
          color: group.color,
          polygons: polygons
        });
      }
    });
    var objects = serializeObjects();
    var thickness = wallThicknessIn();
    var hasFree = objects.some(function (obj) { return isFreeObject(obj); });
    var payload = {
      v: (thickness > 0 || hasFree) ? 4 : (objects.length ? 3 : 2),
      groups: groups
    };
    if (objects.length) payload.objects = objects;
    if (thickness > 0) payload.wall_thickness_in = thickness;
    var bg = captureBackgroundLayoutForSave();
    if (bg) payload.background = bg;
    payload.editor = serializeEditorPrefs();
    return payload;
  }

  function defaultEditorPrefs() {
    return {
      layers_locked: false,
      dimensions_mode: DIM_MODES.all,
      unit_system: 'imperial',
      measure_unit: MEASURE_UNITS.feet_inch_frac,
      angle_snap: true
    };
  }

  function serializeEditorPrefs() {
    var editor = {
      layers_locked: !!state.layersLocked,
      dimensions_mode: DIM_MODES[state.dimensionsMode] ? state.dimensionsMode : DIM_MODES.all,
      unit_system: isMetricDisplay() ? 'metric' : 'imperial',
      measure_unit: state.measureUnit || MEASURE_UNITS.feet_inch_frac,
      angle_snap: state.angleSnap !== false
    };
    if (state.templateKind) editor.template = state.templateKind;
    return editor;
  }

  function applyEditorPrefs(editor, silent) {
    var prefs = Object.assign({}, defaultEditorPrefs(), editor && typeof editor === 'object' ? editor : {});
    if (prefs.measure_unit && MEASURE_UNITS[prefs.measure_unit]) {
      setMeasureUnit(prefs.measure_unit, silent);
    } else if (prefs.unit_system) {
      setUnitSystem(prefs.unit_system, silent);
    }
    setDimensionsMode(prefs.dimensions_mode, silent);
    setLayersLocked(!!prefs.layers_locked, silent !== false);
    setAngleSnap(prefs.angle_snap !== false, silent !== false);
    state.templateKind = prefs.template || null;
  }

  function serializeObjects() {
    return cloneObjects(state.objects).filter(function (obj) {
      if (isFreeObject(obj)) {
        return obj.x != null && obj.y != null;
      }
      return !!resolveWall(obj.wall_id);
    }).map(function (obj) {
      obj.width = Math.round((obj.width || 0) * 1000) / 1000;
      if (isFreeObject(obj)) {
        delete obj.wall_id;
        delete obj.offset;
        delete obj.swing;
        delete obj.hinge;
        delete obj.height;
        delete obj.sill_height;
      } else {
        if (obj.offset != null) obj.offset = Math.round(obj.offset * 1000) / 1000;
        if (obj.height != null) obj.height = Math.round(obj.height * 1000) / 1000;
        if (obj.sill_height != null) obj.sill_height = Math.round(obj.sill_height * 1000) / 1000;
        delete obj.x;
        delete obj.y;
        delete obj.depth;
        delete obj.rotation;
      }
      if (obj.x != null) obj.x = Math.round(obj.x * 1000) / 1000;
      if (obj.y != null) obj.y = Math.round(obj.y * 1000) / 1000;
      if (obj.depth != null) obj.depth = Math.round(obj.depth * 1000) / 1000;
      if (obj.rotation != null) obj.rotation = Math.round(obj.rotation * 1000) / 1000;
      return obj;
    });
  }

  function captureBackgroundLayoutForSave() {
    syncBackgroundLayoutFromKonva();
    if (!state.bgLayout) return null;
    return {
      x: Math.round(state.bgLayout.x * 1000) / 1000,
      y: Math.round(state.bgLayout.y * 1000) / 1000,
      width: Math.round(state.bgLayout.width * 1000) / 1000,
      height: Math.round(state.bgLayout.height * 1000) / 1000
    };
  }

  function syncBackgroundLayoutFromKonva() {
    if (!state.bgImage) return;
    state.bgLayout = {
      x: state.bgImage.x(),
      y: state.bgImage.y(),
      width: state.bgImage.width(),
      height: state.bgImage.height()
    };
  }

  function applyBackgroundLayoutToKonva() {
    if (!state.bgImage || !state.bgLayout) return;
    state.bgImage.position({ x: state.bgLayout.x, y: state.bgLayout.y });
    state.bgImage.width(state.bgLayout.width);
    state.bgImage.height(state.bgLayout.height);
    flushKonva();
  }

  function hydrateBackgroundLayout(bg, scaleFromW, scaleFromH, scaleToW, scaleToH) {
    if (!bg || bg.x == null || bg.y == null || !bg.width || !bg.height) {
      state.bgLayout = null;
      return;
    }
    if (!scaleFromW || !scaleFromH || scaleFromW < 1 || scaleFromH < 1) {
      state.bgLayout = {
        x: parseFloat(bg.x),
        y: parseFloat(bg.y),
        width: parseFloat(bg.width),
        height: parseFloat(bg.height)
      };
      return;
    }
    var s = canvasScaleFactor(scaleFromW, scaleFromH, scaleToW, scaleToH);
    state.bgLayout = {
      x: parseFloat(bg.x) * s,
      y: parseFloat(bg.y) * s,
      width: parseFloat(bg.width) * s,
      height: parseFloat(bg.height) * s
    };
  }

  function scaleBgLayoutState(fromW, fromH, toW, toH) {
    if (!state.bgLayout || !fromW || fromW < 1) return;
    var s = canvasScaleFactor(fromW, fromH, toW, toH);
    if (s === 1) return;
    state.bgLayout = {
      x: state.bgLayout.x * s,
      y: state.bgLayout.y * s,
      width: state.bgLayout.width * s,
      height: state.bgLayout.height * s
    };
  }

  function normalizeCanvasInput(raw) {
    if (!raw) return { v: 2, groups: [] };
    if (Array.isArray(raw) && raw.length && raw[0] && typeof raw[0].x === 'number') {
      return {
        v: 2,
        groups: [{
          id: 'g1',
          color: DEFAULT_GROUP_COLOR,
          polygons: [{ id: 'p1', vertices: raw }]
        }]
      };
    }
    if ((raw.v === 2 || raw.v === 3 || raw.v === 4) && Array.isArray(raw.groups)) {
      var out = { v: raw.v >= 4 ? 4 : (raw.v === 3 ? 3 : 2), groups: raw.groups };
      if (raw.background) out.background = raw.background;
      if (Array.isArray(raw.objects)) out.objects = raw.objects;
      if (raw.wall_thickness_in != null) out.wall_thickness_in = raw.wall_thickness_in;
      if (raw.editor && typeof raw.editor === 'object') out.editor = raw.editor;
      return out;
    }
    return { v: 2, groups: [] };
  }

  function hydrateCanvas(raw, scaleFromW, scaleFromH, scaleToW, scaleToH) {
    resetCanvasGroups();
    var data = normalizeCanvasInput(raw);
    var maxGroupNum = 0;
    var maxPolyNum = 0;
    data.groups.forEach(function (group, gi) {
      var groupId = group.id || ('g' + (gi + 1));
      var groupNum = parseInt(String(groupId).replace(/\D/g, ''), 10);
      if (groupNum > maxGroupNum) maxGroupNum = groupNum;
      var polygons = [];
      (group.polygons || []).forEach(function (poly, pi) {
        var polyId = poly.id || ('p' + (pi + 1));
        var polyNum = parseInt(String(polyId).replace(/\D/g, ''), 10);
        if (polyNum > maxPolyNum) maxPolyNum = polyNum;
        var verts = poly.vertices || [];
        if (scaleFromW && scaleFromH && scaleToW && scaleToH) {
          verts = scalePoints(verts, scaleFromW, scaleFromH, scaleToW, scaleToH);
        }
        if (verts.length < 3) return;
        polygons.push({
          id: polyId,
          closed: true,
          name: sanitizeAreaName(poly.name),
          vertices: verts.map(function (v) { return { x: v.x, y: v.y }; })
        });
      });
      if (polygons.length) {
        state.groups.push({
          id: groupId,
          color: group.color || paletteColorForIndex(gi),
          polygons: polygons
        });
      }
    });
    state.nextGroupId = Math.max(maxGroupNum + 1, state.groups.length + 1);
    state.nextPolygonId = Math.max(maxPolyNum + 1, 2);
    var first = allPolygons()[0];
    if (first) {
      state.activeGroupId = first.group.id;
      state.activePolygonId = first.polygon.id;
    } else {
      ensureInitialCanvas();
    }
    if (data.background) {
      hydrateBackgroundLayout(data.background, scaleFromW, scaleFromH, scaleToW, scaleToH);
    } else {
      state.bgLayout = null;
    }
    hydrateObjects(data.objects || []);
    setWallThickness(data.wall_thickness_in != null ? data.wall_thickness_in : (activeProject() && activeProject().wall_thickness_in) || 0, true);
    applyEditorPrefs(data.editor && typeof data.editor === 'object' ? data.editor : defaultEditorPrefs(), true);
  }

  function hydrateObjects(rawObjects) {
    state.objects = [];
    state.selectedObjectId = null;
    state.nextObjectId = 1;
    (rawObjects || []).forEach(function (item, idx) {
      if (!item) return;
      var type = objectType(item) || 'door';
      if (FREE_OBJECT_TYPES[type]) {
        if (item.x == null || item.y == null) return;
        var size = defaultFreeSize(type, item.subtype || '');
        state.objects.push({
          id: item.id || ('obj_' + (idx + 1)),
          type: type,
          subtype: item.subtype || '',
          x: parseFloat(item.x),
          y: parseFloat(item.y),
          width: parseFloat(item.width) || size.width,
          depth: parseFloat(item.depth) || size.depth,
          rotation: parseFloat(item.rotation) || 0
        });
        return;
      }
      if (!item.wall_id) return;
      var obj = {
        id: item.id || ('obj_' + (idx + 1)),
        type: type,
        subtype: item.subtype || (type === 'window' ? 'fixed' : 'single'),
        wall_id: item.wall_id,
        width: parseFloat(item.width) || 36,
        offset: parseFloat(item.offset) || 0,
        swing: item.swing || 'left_in',
        hinge: item.hinge || 'left',
        height: item.height != null ? parseFloat(item.height) : undefined,
        sill_height: item.sill_height != null ? parseFloat(item.sill_height) : undefined,
        casing_sides: item.casing_sides != null && String(item.casing_sides).trim() !== ''
          ? parseInt(item.casing_sides, 10)
          : RA.defaultCasingSides(type, item.door_face),
        door_face: item.door_face || (type === 'door' ? 'interior' : undefined)
      };
      var wall = resolveWall(obj.wall_id);
      if (wall) clampObjectToWall(obj, wall);
      state.objects.push(obj);
    });
    syncObjectIdCounter();
  }

  function scaleAllCanvasPoints(fromW, fromH, toW, toH) {
    if (!fromW || !fromH || fromW < 1 || fromH < 1) return;
    state.groups.forEach(function (group) {
      group.polygons.forEach(function (poly) {
        poly.vertices = scalePoints(poly.vertices, fromW, fromH, toW, toH);
      });
    });
  }

  function syncIdCountersFromGroups() {
    var maxGroup = 0;
    var maxPoly = 0;
    state.groups.forEach(function (group) {
      var gn = parseInt(String(group.id).replace(/\D/g, ''), 10);
      if (gn > maxGroup) maxGroup = gn;
      group.polygons.forEach(function (poly) {
        var pn = parseInt(String(poly.id).replace(/\D/g, ''), 10);
        if (pn > maxPoly) maxPoly = pn;
      });
    });
    state.nextGroupId = Math.max(state.nextGroupId, maxGroup + 1);
    state.nextPolygonId = Math.max(state.nextPolygonId, maxPoly + 1);
  }

  function updateGroupChip() {
    if (!els.groupChip) return;
    var group = activeGroup();
    var hasClosed = hasAnyClosedPolygon();
    show(els.groupChip, !!group && hasClosed);
    if (!group || !hasClosed) return;
    if (els.groupChipSwatch) els.groupChipSwatch.style.backgroundColor = group.color;
    if (els.groupChipTotal) {
      var sqft = groupAreaSqFt(group);
      var count = (group.polygons || []).filter(function (p) { return p.closed && p.vertices.length >= 3; }).length;
      els.groupChipTotal.textContent = fmtAreaFromSqFt(sqft) + (count > 1 ? ' (' + count + ' areas)' : '');
    }
  }

  function measureWrapWidth() {
    var wrap = els.canvasWrap;
    if (!wrap) return 0;
    var rect = wrap.getBoundingClientRect();
    var w = Math.floor(rect.width);
    if (w < 1) w = Math.floor(wrap.clientWidth);
    return w > 0 ? w : 0;
  }

  function isNarrowCanvas(width) {
    return (width || 0) > 0 && width <= NARROW_CANVAS_MAX;
  }

  function rawContentBounds() {
    var minX = Infinity;
    var minY = Infinity;
    var maxX = -Infinity;
    var maxY = -Infinity;
    var has = false;
    allPolygons().forEach(function (entry) {
      (entry.polygon.vertices || []).forEach(function (v) {
        if (!isFinite(v.x) || !isFinite(v.y)) return;
        has = true;
        minX = Math.min(minX, v.x);
        minY = Math.min(minY, v.y);
        maxX = Math.max(maxX, v.x);
        maxY = Math.max(maxY, v.y);
      });
    });
    if (!has) return { minX: 0, minY: 0, maxX: 1, maxY: 1, hasContent: false };
    return { minX: minX, minY: minY, maxX: maxX, maxY: maxY, hasContent: true };
  }

  function getViewportDimensions() {
    var wrap = els.canvasWrap;
    var wrapW = measureWrapWidth();
    if (!wrap || wrapW < 1) {
      return { w: state.viewportW || 900, h: state.viewportH || 640 };
    }
    if (isFullPage()) {
      var wrapH = Math.floor(wrap.getBoundingClientRect().height || wrap.clientHeight);
      return {
        w: Math.max(MIN_CANVAS_W, wrapW),
        h: Math.max(1, wrapH || 240)
      };
    }
    var w = wrapW;
    var narrow = isNarrowCanvas(w);
    var maxH = Math.min(
      Math.floor(window.innerHeight * (narrow ? CANVAS_MAX_VH_NARROW : CANVAS_MAX_VH)),
      MAX_CANVAS_H
    );
    if (narrow) {
      var phone = window.innerHeight < 800;
      maxH = Math.min(
        Math.floor(window.innerHeight * (phone ? CANVAS_MAX_VH_PHONE : CANVAS_MAX_VH_NARROW)),
        MAX_CANVAS_H
      );
      var bounds = rawContentBounds();
      var h;
      if (bounds.hasContent) {
        var bw = Math.max(bounds.maxX - bounds.minX, 1);
        var bh = Math.max(bounds.maxY - bounds.minY, 1);
        h = Math.round(w * FIT_WIDTH_RATIO * (bh / bw) + 28);
      } else {
        h = Math.round(w * (phone ? 0.48 : 0.56));
      }
      h = Math.max(MIN_CANVAS_H_NARROW, Math.min(maxH, h));
      return { w: w, h: h };
    }
    var hDesk = Math.max(MIN_CANVAS_H, Math.min(maxH, Math.round(w * CANVAS_HEIGHT_RATIO)));
    return { w: w, h: hDesk };
  }

  function syncDrawableDimensions() {
    var vp = getViewportDimensions();
    state.viewportW = vp.w;
    state.viewportH = vp.h;
    state.canvasW = Math.round(vp.w * DRAW_AREA_FACTOR);
    state.canvasH = Math.round(vp.h * DRAW_AREA_FACTOR);
    syncCanvasWrapSize(vp.w, vp.h);
    return vp;
  }

  function syncCanvasWrapSize(w, h) {
    if (!els.canvasWrap) return;
    if (isFullPage()) return;
    els.canvasWrap.style.height = h + 'px';
  }

  function viewportW() {
    return state.viewportW || Math.round(state.canvasW / DRAW_AREA_FACTOR);
  }

  function viewportH() {
    return state.viewportH || Math.round(state.canvasH / DRAW_AREA_FACTOR);
  }

  function canvasW() {
    return state.canvasW;
  }

  function canvasH() {
    return state.canvasH;
  }

  /** Natural drawing density: one screen pixel at 100% zoom. Extra world beyond this is pan space. */
  function contentW() {
    return viewportW();
  }

  function contentH() {
    return viewportH();
  }

  function scalePoints(points, fromW, fromH, toW, toH) {
    if (!points || !points.length) return [];
    var s = canvasScaleFactor(fromW, fromH, toW, toH);
    return points.map(function (v) {
      return { x: parseFloat(v.x) * s, y: parseFloat(v.y) * s };
    });
  }

  /**
   * One factor for x, y, and pixels-per-foot. Stretching width and height
   * independently (or scaling geometry without scale) changes wall lengths.
   */
  function canvasScaleFactor(fromW, fromH, toW, toH) {
    if (!fromW || fromW < 1 || !toW) return 1;
    var s = toW / fromW;
    if (!isFinite(s) || s <= 0) return 1;
    if (Math.abs(s - 1) < 0.0005) return 1;
    return s;
  }

  function activeRoom() {
    var project = activeProject();
    if (!project || !state.editingRoomId) return null;
    return project.rooms.find(function (r) { return r.id === state.editingRoomId; }) || null;
  }

  function revokeDraftBackground() {
    if (state.draftBackground && state.draftBackground.url) {
      URL.revokeObjectURL(state.draftBackground.url);
    }
    state.draftBackground = null;
  }

  function currentBackgroundUrl() {
    if (state.draftBackground && state.draftBackground.url) return state.draftBackground.url;
    var room = activeRoom();
    if (room && room.background_url) return room.background_url;
    return null;
  }

  function setWorkingPixelsPerFoot(ppf) {
    var n = parseFloat(ppf);
    state.workingPpf = (n && isFinite(n) && n > 0) ? n : null;
  }

  function scaleActivePixelsPerFoot(factor) {
    if (Math.abs(factor - 1) < 0.0005) return;
    var current = pixelsPerFoot();
    if (!current) return;
    setWorkingPixelsPerFoot(current * factor);
    if (!state.editingRoomId) {
      state.draftScale = state.workingPpf;
    }
  }

  function roomSourceCanvas(room, project) {
    return {
      w: room.canvas_width || project.canvas_width || contentW(),
      h: room.canvas_height || project.canvas_height || contentH()
    };
  }

  function canvasExtentsFromRaw(raw) {
    var maxX = 0;
    var maxY = 0;
    var data = normalizeCanvasInput(raw);
    data.groups.forEach(function (group) {
      (group.polygons || []).forEach(function (poly) {
        (poly.vertices || []).forEach(function (v) {
          maxX = Math.max(maxX, parseFloat(v.x) || 0);
          maxY = Math.max(maxY, parseFloat(v.y) || 0);
        });
      });
    });
    return { w: Math.max(maxX, 1), h: Math.max(maxY, 1) };
  }

  /**
   * Map saved coordinates into viewport-density content space.
   */
  function hydrateScaleTarget(raw, sourceW, sourceH) {
    var toW = contentW();
    var toH = contentH();
    var ext = canvasExtentsFromRaw(raw);
    var fromW = Math.max(sourceW || 0, ext.w);
    var fromH = Math.max(sourceH || 0, ext.h);

    if (!fromW || !fromH || fromW < 1 || fromH < 1) {
      return { fromW: toW, fromH: toH, toW: toW, toH: toH };
    }

    if (Math.abs(fromW - toW) < 3 && Math.abs(fromH - toH) < 3) {
      return { fromW: toW, fromH: toH, toW: toW, toH: toH };
    }

    // Oversized canvas metadata but geometry only occupies the content viewport.
    if (fromW > toW * 1.4 && ext.w <= toW * 1.35 && ext.h <= toH * 1.35) {
      return { fromW: toW, fromH: toH, toW: toW, toH: toH };
    }

    return { fromW: fromW, fromH: fromH, toW: toW, toH: toH };
  }

  function applyWorkingScaleFromSaved(savedPpf, target) {
    var base = parseFloat(savedPpf) || 0;
    if (base <= 0 || !target) {
      setWorkingPixelsPerFoot(null);
      return;
    }
    var s = canvasScaleFactor(target.fromW, target.fromH, target.toW, target.toH);
    setWorkingPixelsPerFoot(base * s);
  }

  function confirmAction(message, options) {
    if (window.AdminDialog && window.AdminDialog.confirm) {
      return window.AdminDialog.confirm(message, options);
    }
    return Promise.resolve(window.confirm(message));
  }

  function normalizeVertices(vertices) {
    return (vertices || []).map(function (v) {
      return {
        x: Math.round(v.x * 1000) / 1000,
        y: Math.round(v.y * 1000) / 1000
      };
    });
  }

  function buildWorkSnapshot() {
    return {
      editingRoomId: state.editingRoomId,
      name: (els.roomName && els.roomName.value || '').trim(),
      groups: cloneGroups(state.groups),
      objects: cloneObjects(state.objects),
      activeGroupId: state.activeGroupId,
      activePolygonId: state.activePolygonId,
      scale: pixelsPerFoot() || null,
      backgroundUrl: state.activeBackgroundUrl || null,
      hasDraftBackground: !!(state.draftBackground && state.draftBackground.file),
      draftScale: state.draftScale ? parseFloat(state.draftScale) : null,
      rulers: projectRulers().map(function (r) {
        return {
          id: r.id,
          p1: normalizeVertices([r.p1])[0],
          p2: normalizeVertices([r.p2])[0]
        };
      }),
      bgLayout: state.bgLayout
        ? {
          x: Math.round(state.bgLayout.x * 1000) / 1000,
          y: Math.round(state.bgLayout.y * 1000) / 1000,
          width: Math.round(state.bgLayout.width * 1000) / 1000,
          height: Math.round(state.bgLayout.height * 1000) / 1000
        }
        : null,
      editor: serializeEditorPrefs(),
      finish: finishSnapshot()
    };
  }

  function captureSavedSnapshot() {
    state.savedSnapshot = JSON.stringify(buildWorkSnapshot());
  }

  function hasUnsavedChanges() {
    if (!state.savedSnapshot) return false;
    return state.savedSnapshot !== JSON.stringify(buildWorkSnapshot());
  }

  function promptUnsavedBeforeAction(action, options) {
    options = options || {};
    if (!hasUnsavedChanges()) {
      action();
      return Promise.resolve(true);
    }
    return confirmAction(
      options.message || 'You have unsaved changes. Save them before continuing?',
      {
        title: 'Unsaved changes',
        confirmLabel: options.confirmLabel || 'Save changes',
        cancelLabel: options.cancelLabel || 'Stay on page'
      }
    ).then(function (shouldSave) {
      if (!shouldSave) return false;
      return saveRoom()
        .then(function () {
          action();
          return true;
        })
        .catch(function (err) {
          setFeedback((err && err.message) || 'Could not save changes.', true);
          return false;
        });
    });
  }

  function saveErrorMessage(data, fallback) {
    if (!data) return fallback;
    if (data.message && (!data.errors || typeof data.errors !== 'object')) {
      return data.message;
    }
    if (data.errors && typeof data.errors === 'object') {
      var keys = Object.keys(data.errors);
      for (var i = 0; i < keys.length; i++) {
        var msgs = data.errors[keys[i]];
        if (Array.isArray(msgs) && msgs.length) return msgs[0];
        if (typeof msgs === 'string' && msgs) return msgs;
      }
    }
    return data.message || fallback;
  }

  function readFetchJson(res, fallbackMessage) {
    return RA.readFetchJson(res, fallbackMessage);
  }

  function friendlyNetworkError(err, fallbackMessage) {
    return RA.friendlyNetworkError(err, fallbackMessage);
  }

  function csrf() {
    var input = root.querySelector('#util-ra-csrf');
    return input ? input.value : '';
  }

  function urlFromTemplate(tpl, id) {
    return tpl.replace('__ID__', String(id));
  }

  function quoteRoomUrl(roomId) {
    var tpl = root.getAttribute('data-quote-room-template') || '';
    return tpl ? urlFromTemplate(tpl, roomId) : '';
  }

  function show(el, visible) {
    if (!el) return;
    if (visible) el.removeAttribute('hidden');
    else el.setAttribute('hidden', '');
  }

  function parseJson(attr, fallback) {
    try { return JSON.parse(root.getAttribute(attr)) || fallback; } catch (e) { return fallback; }
  }

  function activeProject() {
    return state.projects.find(function (p) { return p.id === state.activeProjectId; }) || null;
  }

  function projectRulers() {
    var room = activeRoom();
    if (room) {
      if (!room.rulers) room.rulers = [];
      return room.rulers;
    }
    if (!state.draftRulers) state.draftRulers = [];
    return state.draftRulers;
  }

  function findRulerById(id) {
    var rulers = projectRulers();
    for (var i = 0; i < rulers.length; i++) {
      if (rulers[i].id === id) return rulers[i];
    }
    return null;
  }

  function rulerLengthFeet(ruler) {
    var ppf = pixelsPerFoot();
    if (!ppf || !ruler) return null;
    return dist(ruler.p1, ruler.p2) / ppf;
  }

  function rulerLengthLabel(ruler) {
    var feet = rulerLengthFeet(ruler);
    if (feet === null) return 'Scale not set';
    return fmtLengthFromFeet(feet);
  }

  function findRulerAtPoint(pos) {
    if (!pos) return null;
    var rulers = projectRulers();
    var hit = hitTolerance(isCoarsePointer() ? TOUCH_HIT_RADIUS : RULER_HIT);
    var best = null;
    var bestDist = hit + 1;
    for (var i = rulers.length - 1; i >= 0; i--) {
      var ruler = rulers[i];
      var d = pointToSegmentDistance(pos, ruler.p1, ruler.p2);
      if (d <= hit && d < bestDist) {
        best = ruler;
        bestDist = d;
      }
    }
    return best;
  }

  function scaleProjectRulers(oldW, oldH, newW, newH) {
    var rulers = projectRulers();
    if (!rulers.length && !state.rulerDraft.length && !state.rulerPreview) return;
    rulers.forEach(function (ruler) {
      ruler.p1 = scalePoints([ruler.p1], oldW, oldH, newW, newH)[0];
      ruler.p2 = scalePoints([ruler.p2], oldW, oldH, newW, newH)[0];
    });
    if (state.rulerDraft.length) {
      state.rulerDraft = scalePoints(state.rulerDraft, oldW, oldH, newW, newH);
    }
    if (state.rulerPreview) {
      state.rulerPreview = scalePoints([state.rulerPreview], oldW, oldH, newW, newH)[0];
    }
  }

  function setFeedback(msg, isError) {
    if (!els.feedback) return;
    els.feedback.textContent = msg;
    els.feedback.className = 'util-ra-feedback ' + (isError ? 'is-error' : 'is-ok');
    show(els.feedback, !!msg);
    if (msg) setTimeout(function () { show(els.feedback, false); }, 4500);
  }

  function parseFeetInput(raw) {
    return RA.parseLengthInput(raw, MEASURE_UNITS.feet_inch_frac);
  }

  function fmt(n, d) {
    return (Math.round(n * Math.pow(10, d)) / Math.pow(10, d)).toFixed(d);
  }

  function reduceFraction(num, den) {
    function gcd(a, b) { return b ? gcd(b, a % b) : Math.abs(a); }
    var g = gcd(num, den);
    return { num: num / g, den: den / g };
  }

  function formatSixteenthInches(sixteenths) {
    if (!sixteenths) return '';
    var parts = reduceFraction(sixteenths, 16);
    if (parts.num === parts.den) return '1';
    return parts.num + '/' + parts.den;
  }

  function fmtFeetInchesFrac(lenFt) {
    var totalInches = lenFt * INCHES_PER_FOOT;
    var rounded = Math.round(totalInches * 16) / 16;
    if (rounded < 1 / 16) return '0"';
    var feet = Math.floor(rounded / 12);
    var rem = rounded - feet * 12;
    var whole = Math.floor(rem);
    var frac = Math.round((rem - whole) * 16);
    if (frac === 16) { whole += 1; frac = 0; }
    if (whole === 12) { feet += 1; whole = 0; }
    var fracStr = formatSixteenthInches(frac);

    if (feet === 0) {
      if (fracStr === '') return whole + '"';
      if (whole === 0) return fracStr + '"';
      return whole + ' ' + fracStr + '"';
    }
    if (whole === 0 && fracStr === '') return feet + "'";
    if (whole === 0) return feet + "' " + fracStr + '"';
    if (fracStr === '') return feet + "' " + whole + '"';
    return feet + "' " + whole + ' ' + fracStr + '"';
  }

  function fmtFeetDecimal(lenFt) {
    return fmt(lenFt, 2) + ' ft';
  }

  function fmtInchesOnly(lenFt) {
    var inches = lenFt * INCHES_PER_FOOT;
    var decimals = inches >= 100 ? 1 : 2;
    return fmt(inches, decimals) + '"';
  }

  function fmtMeters(lenFt) {
    return fmt(lenFt * FEET_TO_METERS, 2) + ' m';
  }

  function fmtLengthFromFeet(lenFt) {
    if (lenFt == null || !isFinite(lenFt)) return '—';
    switch (state.measureUnit) {
      case MEASURE_UNITS.meter:
        return fmtMeters(lenFt);
      case MEASURE_UNITS.inch:
        return fmtInchesOnly(lenFt);
      case MEASURE_UNITS.feet_decimal:
        return fmtFeetDecimal(lenFt);
      case MEASURE_UNITS.feet_inch_frac:
      default:
        return fmtFeetInchesFrac(lenFt);
    }
  }

  function measureUnitPlaceholder() {
    switch (state.measureUnit) {
      case MEASURE_UNITS.meter:
        return 'e.g. 3.5 m';
      case MEASURE_UNITS.inch:
        return 'e.g. 150"';
      case MEASURE_UNITS.feet_decimal:
        return 'e.g. 12.5 ft';
      default:
        return 'e.g. 12\' 6 1/2"';
    }
  }

  function updateMeasureUnitPlaceholders() {
    var ph = measureUnitPlaceholder();
    if (els.wallLength) els.wallLength.placeholder = ph;
    raAll('[data-ra-known-length]').forEach(function (el) { el.placeholder = ph; });
    if (els.typeLength) els.typeLength.placeholder = isMetricDisplay() ? '3.66 m' : (state.measureUnit === MEASURE_UNITS.inch ? '150"' : (state.measureUnit === MEASURE_UNITS.feet_decimal ? '12.5' : "12'6"));
    if (els.typeRectW) els.typeRectW.placeholder = isMetricDisplay() ? '3.66' : '12';
    if (els.typeRectL) els.typeRectL.placeholder = isMetricDisplay() ? '3.05' : '10';
    if (els.wallHeight) els.wallHeight.placeholder = isMetricDisplay() ? '2.44' : '8';
    if (els.ceilingHeight) els.ceilingHeight.placeholder = isMetricDisplay() ? '2.44' : '8';
  }

  function isMetricDisplay() {
    return state.measureUnit === MEASURE_UNITS.meter;
  }

  function setMeasureUnit(unit, silent) {
    if (!MEASURE_UNITS[unit]) return;
    if (unit !== MEASURE_UNITS.meter) state.imperialUnit = unit;
    state.measureUnit = unit;
    raAll('[data-ra-measure-unit]').forEach(function (sel) {
      if (unit === MEASURE_UNITS.meter) {
        sel.value = state.imperialUnit || MEASURE_UNITS.feet_inch_frac;
      } else {
        sel.value = unit;
      }
    });
    syncUnitSystemUi();
    updateMeasureUnitPlaceholders();
    updateLiveUnitLabels();
    syncWallHeightInput();
    if (!silent) {
      redrawLabels();
      redrawRulers();
      refreshWallLengthInput();
      updateLiveArea();
      renderTypedWallsList();
    }
    if (state.selectedRulerId !== null && els.rulerLength) {
      var ruler = findRulerById(state.selectedRulerId);
      if (ruler) els.rulerLength.textContent = rulerLengthLabel(ruler);
    }
  }

  function syncUnitSystemUi() {
    var metric = isMetricDisplay();
    raAll('[data-ra-unit-system]').forEach(function (btn) {
      var want = btn.getAttribute('data-ra-unit-system') === 'metric';
      var on = want === metric;
      btn.classList.toggle('is-active', on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }

  function setUnitSystem(system, silent) {
    if (system === 'metric') {
      setMeasureUnit(MEASURE_UNITS.meter, silent);
      return;
    }
    setMeasureUnit(state.imperialUnit && state.imperialUnit !== MEASURE_UNITS.meter
      ? state.imperialUnit
      : MEASURE_UNITS.feet_inch_frac, silent);
  }

  function updateLiveUnitLabels() {
    var area = isMetricDisplay() ? 'm²' : 'sq ft';
    var linear = isMetricDisplay() ? 'm' : 'lf';
    raAll('[data-ra-area-unit]').forEach(function (el) { el.textContent = area; });
    raAll('[data-ra-linear-unit]').forEach(function (el) { el.textContent = linear; });
    raAll('[data-ra-wall-area-label]').forEach(function (el) {
      el.textContent = 'Net wall area';
    });
    if (els.floorBoxUnit) {
      els.floorBoxUnit.textContent = isMetricDisplay() ? 'm²' : 'sf';
    } else if (els.floorBox) {
      var boxUnit = els.floorBox.parentNode && els.floorBox.parentNode.querySelector('.util-ra-live-area__height-unit');
      if (boxUnit) boxUnit.textContent = isMetricDisplay() ? 'm²' : 'sf';
    }
    if (els.wallHeight) {
      var unitEl = els.wallHeight.parentNode && els.wallHeight.parentNode.querySelector('.util-ra-live-area__height-unit');
      if (unitEl) unitEl.textContent = isMetricDisplay() ? 'm' : 'ft';
    }
    if (els.ceilingHeight) {
      var ceilUnit = els.ceilingHeight.parentNode && els.ceilingHeight.parentNode.querySelector('.util-ra-live-area__height-unit');
      if (ceilUnit) ceilUnit.textContent = isMetricDisplay() ? 'm' : 'ft';
    }
    syncFinishInputs(false);
    raAll('[data-ra-measure-unit-wrap]').forEach(function (el) {
      el.hidden = isMetricDisplay();
    });
  }

  function refreshWallLengthInput() {
    if (state.selectedWallIndex === null || !els.wallLength || !pixelsPerFoot()) return;
    var lenFt = wallLengthFeet(state.selectedWallIndex);
    if (lenFt !== null) els.wallLength.value = fmtLengthFromFeet(lenFt);
  }

  function parseMetersInput(raw) {
    return RA.parseLengthInput(raw, MEASURE_UNITS.meter);
  }

  function parseInchesOnlyInput(raw) {
    return RA.parseLengthInput(raw, MEASURE_UNITS.inch);
  }

  function parseFeetDecimalInput(raw) {
    return RA.parseLengthInput(raw, MEASURE_UNITS.feet_decimal);
  }

  function parseMmInput(raw) {
    return RA.parseLengthInput(raw, 'mm');
  }

  function detectExplicitLengthUnit(raw) {
    return RA.detectExplicitUnit(raw);
  }

  function parseLengthInput(raw) {
    return RA.parseLengthInput(raw, state.measureUnit);
  }

  function geometryLocked() {
    return !!state.layersLocked;
  }

  function warnGeometryLocked() {
    setFeedback('Unlock layers to move walls or corners. Doors, windows, and notes stay editable.', true);
  }

  function shouldAngleSnap(e) {
    if (state.altHeld || (e && e.evt && e.evt.altKey)) return false;
    if (state.angleSnap) return true;
    return !!(state.shiftHeld || (e && e.evt && e.evt.shiftKey));
  }

  function fmtAreaFromSqFt(sqft) {
    if (sqft == null || !isFinite(sqft)) return '—';
    if (isMetricDisplay()) return fmt(sqft * SQFT_TO_SQM, 2) + ' m²';
    return fmt(sqft, 2) + ' sq ft';
  }

  function formatLinearQty(feet) {
    if (feet == null || !isFinite(feet)) return '—';
    if (isMetricDisplay()) return fmt(feet * FEET_TO_METERS, 2);
    return fmt(feet, 2);
  }

  function formatAreaQty(sqft) {
    if (sqft == null || !isFinite(sqft)) return '—';
    if (isMetricDisplay()) return fmt(sqft * SQFT_TO_SQM, 2);
    return fmt(sqft, 2);
  }

  function shoelaceArea(vertices) {
    if (vertices.length < 3) return 0;
    var sum = 0;
    for (var i = 0; i < vertices.length; i++) {
      var j = (i + 1) % vertices.length;
      sum += vertices[i].x * vertices[j].y - vertices[j].x * vertices[i].y;
    }
    return Math.abs(sum) / 2;
  }

  function direction(p1, p2, p3) {
    var cross = (p3.x - p1.x) * (p2.y - p1.y) - (p2.x - p1.x) * (p3.y - p1.y);
    if (Math.abs(cross) < 1e-9) return 0;
    return cross > 0 ? 1 : -1;
  }

  function onSegment(p1, p2, p3) {
    return Math.min(p1.x, p2.x) <= p3.x && p3.x <= Math.max(p1.x, p2.x)
      && Math.min(p1.y, p2.y) <= p3.y && p3.y <= Math.max(p1.y, p2.y);
  }

  function segmentsIntersect(p1, p2, p3, p4) {
    var d1 = direction(p3, p4, p1);
    var d2 = direction(p3, p4, p2);
    var d3 = direction(p1, p2, p3);
    var d4 = direction(p1, p2, p4);
    if (((d1 > 0 && d2 < 0) || (d1 < 0 && d2 > 0)) && ((d3 > 0 && d4 < 0) || (d3 < 0 && d4 > 0))) return true;
    if (d1 === 0 && onSegment(p3, p4, p1)) return true;
    if (d2 === 0 && onSegment(p3, p4, p2)) return true;
    if (d3 === 0 && onSegment(p1, p2, p3)) return true;
    if (d4 === 0 && onSegment(p1, p2, p4)) return true;
    return false;
  }

  function isSimplePolygon(vertices) {
    var n = vertices.length;
    if (n < 3) return false;
    for (var i = 0; i < n; i++) {
      var a1 = vertices[i];
      var a2 = vertices[(i + 1) % n];
      for (var j = i + 1; j < n; j++) {
        if (j === i + 1) continue;
        if (i === 0 && j === n - 1) continue;
        var b1 = vertices[j];
        var b2 = vertices[(j + 1) % n];
        if (segmentsIntersect(a1, a2, b1, b2)) return false;
      }
    }
    return true;
  }

  function isSimplePolyline(vertices) {
    var n = vertices.length;
    if (n < 2) return true;
    for (var i = 0; i < n - 1; i++) {
      var a1 = vertices[i];
      var a2 = vertices[i + 1];
      for (var j = i + 1; j < n - 1; j++) {
        if (j === i + 1) continue;
        var b1 = vertices[j];
        var b2 = vertices[j + 1];
        if (segmentsIntersect(a1, a2, b1, b2)) return false;
      }
    }
    return true;
  }

  function isValidShape(vertices, closed) {
    if (closed) return isSimplePolygon(vertices);
    return isSimplePolyline(vertices);
  }

  function snapshotState() {
    return {
      groups: cloneGroups(state.groups),
      objects: cloneObjects(state.objects),
      selectedObjectId: state.selectedObjectId,
      activeGroupId: state.activeGroupId,
      activePolygonId: state.activePolygonId,
      scalePixelsPerFoot: captureScaleSnapshot(),
      wallThicknessIn: wallThicknessIn(),
      finish: finishSnapshot()
    };
  }

  function captureScaleSnapshot() {
    var ppf = pixelsPerFoot();
    return ppf > 0 ? ppf : null;
  }

  function restoreScaleSnapshot(ppf) {
    if (ppf == null || !isFinite(ppf) || ppf <= 0) return;
    setWorkingPixelsPerFoot(ppf);
    if (!state.editingRoomId) {
      state.draftScale = ppf;
    }
  }

  function pushUndoSnapshot() {
    state.undoStack.push(snapshotState());
    if (state.undoStack.length > UNDO_MAX) state.undoStack.shift();
    state.redoStack = [];
    updateToolbar();
  }

  function discardLastUndoSnapshot() {
    if (!state.undoStack.length) return;
    state.undoStack.pop();
    updateToolbar();
  }

  function clearUndoStack() {
    state.undoStack = [];
    state.redoStack = [];
    updateToolbar();
  }

  function restoreUndoSnapshot(snap) {
    if (snap.groups) {
      state.groups = cloneGroups(snap.groups);
      state.activeGroupId = snap.activeGroupId || null;
      state.activePolygonId = snap.activePolygonId || null;
      syncIdCountersFromGroups();
      if (!activePolygon() && allPolygons().length) {
        var first = allPolygons()[0];
        state.activeGroupId = first.group.id;
        state.activePolygonId = first.polygon.id;
      }
    } else if (snap.vertices) {
      ensureInitialCanvas();
      setActiveVertices(snap.vertices);
      setActiveClosed(!!snap.closed);
    }
    state.draggingVertex = false;
    state.draggingWall = false;
    state.draggingShape = false;
    state.shapePointerDown = null;
    state.didShapeDrag = false;
    state.wallDragIndex = null;
    state.wallDragPointerStart = null;
    state.wallDragOrigA = null;
    state.wallDragOrigB = null;
    state.didWallDrag = false;
    state.didVertexDrag = false;
    state.vertexDragFrom = null;
    state.vertexDragOrigins = null;
    if (snap.scalePixelsPerFoot != null) {
      restoreScaleSnapshot(snap.scalePixelsPerFoot);
    }
    if (snap.objects) {
      state.objects = cloneObjects(snap.objects);
      syncObjectIdCounter();
    }
    state.selectedObjectId = snap.selectedObjectId || null;
    if (state.selectedObjectId && !selectedObject()) state.selectedObjectId = null;
    if (snap.wallThicknessIn != null) {
      setWallThickness(snap.wallThicknessIn, true);
    }
    if (snap.finish) applyFinishSnapshot(snap.finish);
    state.selectedVertexIndices = [];
    clearWallSelection();
    clearVertexSelection();
    resetWallClickState();
    syncObjectPropertiesPanel();
  }

  function pixelsPerFoot() {
    if (state.workingPpf && isFinite(state.workingPpf) && state.workingPpf > 0) {
      return parseFloat(state.workingPpf);
    }
    var room = activeRoom();
    if (room && room.scale_pixels_per_foot) {
      return parseFloat(room.scale_pixels_per_foot);
    }
    if (!state.editingRoomId && state.draftScale) {
      return parseFloat(state.draftScale);
    }
    var project = activeProject();
    return project && project.scale_pixels_per_foot ? parseFloat(project.scale_pixels_per_foot) : 0;
  }

  function areaSqFt(vertices) {
    var ppf = pixelsPerFoot();
    if (!ppf || vertices.length < 3) return null;
    if (!isSimplePolygon(vertices)) return null;
    var footVerts = verticesInFeet(vertices);
    if (!footVerts) return null;
    return Math.round(shoelaceArea(footVerts) * 100) / 100;
  }

  function dist(a, b) {
    return Math.hypot(b.x - a.x, b.y - a.y);
  }

  function angleDelta(a, b) {
    var d = Math.abs(a - b) % (Math.PI * 2);
    return d > Math.PI ? Math.PI * 2 - d : d;
  }

  function angleSnapPoint(raw, prev) {
    if (!prev || !raw) return raw;
    var mag = snapPointMagnetic(raw, prev, 'incoming');
    return mag.snapped ? { x: mag.x, y: mag.y } : raw;
  }

  function snapPoint(raw, prev, step) {
    if (!prev || step === undefined) return raw;
    var dx = raw.x - prev.x;
    var dy = raw.y - prev.y;
    var len = Math.hypot(dx, dy);
    if (len < 1e-6) return raw;
    var angle = Math.atan2(dy, dx);
    var snapped = Math.round(angle / step) * step;
    return {
      x: prev.x + Math.cos(snapped) * len,
      y: prev.y + Math.sin(snapped) * len
    };
  }

  function snapPointMagnetic(raw, anchor, mode) {
    var dx;
    var dy;
    if (mode === 'incoming') {
      dx = raw.x - anchor.x;
      dy = raw.y - anchor.y;
    } else {
      dx = anchor.x - raw.x;
      dy = anchor.y - raw.y;
    }
    var len = Math.hypot(dx, dy);
    if (len < 1e-6) {
      return { x: raw.x, y: raw.y, snapped: false };
    }

    var angle = Math.atan2(dy, dx);
    var snappedAngle = Math.round(angle / SHIFT_SNAP_STEP) * SHIFT_SNAP_STEP;
    var diff = angleDelta(angle, snappedAngle);
    var threshold = SHIFT_SNAP_THRESHOLD_DEG * Math.PI / 180;

    if (diff > threshold) {
      return { x: raw.x, y: raw.y, snapped: false };
    }

    if (mode === 'incoming') {
      return {
        x: anchor.x + Math.cos(snappedAngle) * len,
        y: anchor.y + Math.sin(snappedAngle) * len,
        snapped: true
      };
    }
    return {
      x: anchor.x - Math.cos(snappedAngle) * len,
      y: anchor.y - Math.sin(snappedAngle) * len,
      snapped: true
    };
  }

  function getVertexPrev(idx) {
    var n = activeVertices().length;
    if (idx > 0) return activeVertices()[idx - 1];
    if (isActiveClosed() && n > 1) return activeVertices()[n - 1];
    return null;
  }

  function getVertexNext(idx) {
    var n = activeVertices().length;
    if (idx < n - 1) return activeVertices()[idx + 1];
    if (isActiveClosed() && n > 1) return activeVertices()[0];
    return null;
  }

  function snapVertexPositionShift(idx, raw) {
    var prev = getVertexPrev(idx);
    var next = getVertexNext(idx);
    var best = raw;
    var bestOffset = Infinity;
    var found = false;

    if (prev) {
      var incoming = snapPointMagnetic(raw, prev, 'incoming');
      if (incoming.snapped) {
        var offset = dist(raw, incoming);
        if (offset < bestOffset) {
          best = { x: incoming.x, y: incoming.y };
          bestOffset = offset;
          found = true;
        }
      }
    }

    if (next) {
      var outgoing = snapPointMagnetic(raw, next, 'outgoing');
      if (outgoing.snapped) {
        var outOffset = dist(raw, outgoing);
        if (!found || outOffset < bestOffset) {
          best = { x: outgoing.x, y: outgoing.y };
          found = true;
        }
      }
    }

    return clampToCanvas(found ? best : raw);
  }

  function alignmentThreshold() {
    return Math.max(uiSize(5), 4);
  }

  function collectAlignmentGuides(idx, raw) {
    if (!raw || state.altHeld) return { point: raw, guides: [] };
    var thresh = alignmentThreshold();
    var xHits = [];
    var yHits = [];
    allPolygons().forEach(function (entry) {
      (entry.polygon.vertices || []).forEach(function (v, vi) {
        if (entry.polygon.id === (activePolygon() && activePolygon().id) && vi === idx) return;
        if (Math.abs(v.x - raw.x) <= thresh) xHits.push(v.x);
        if (Math.abs(v.y - raw.y) <= thresh) yHits.push(v.y);
      });
    });
    var point = { x: raw.x, y: raw.y };
    var guides = [];
    if (xHits.length) {
      xHits.sort(function (a, b) { return Math.abs(a - raw.x) - Math.abs(b - raw.x); });
      point.x = xHits[0];
      guides.push({ type: 'v', x: point.x });
    }
    if (yHits.length) {
      yHits.sort(function (a, b) { return Math.abs(a - raw.y) - Math.abs(b - raw.y); });
      point.y = yHits[0];
      guides.push({ type: 'h', y: point.y });
    }
    return { point: point, guides: guides };
  }

  function snapVertexToGuides(idx, raw) {
    var aligned = collectAlignmentGuides(idx, raw);
    state.alignGuides = aligned.guides;
    return clampToCanvas(aligned.point);
  }

  function redrawAlignmentGuides() {
    var layer = state.layers.preview;
    if (!layer) return;
    var guides = state.alignGuides || [];
    var hint = state.openingHint;
    if (!guides.length && !hint && !state.drawPreview) {
      return;
    }
    guides.forEach(function (g) {
      if (g.type === 'v') {
        layer.add(new Konva.Line({
          points: [g.x, 0, g.x, canvasH()],
          stroke: isAdminDark() ? 'rgba(125, 211, 252, 0.55)' : 'rgba(2, 71, 104, 0.35)',
          strokeWidth: uiSize(1),
          dash: [uiSize(6), uiSize(4)],
          listening: false
        }));
      } else {
        layer.add(new Konva.Line({
          points: [0, g.y, canvasW(), g.y],
          stroke: isAdminDark() ? 'rgba(125, 211, 252, 0.55)' : 'rgba(2, 71, 104, 0.35)',
          strokeWidth: uiSize(1),
          dash: [uiSize(6), uiSize(4)],
          listening: false
        }));
      }
    });
    if (hint && hint.point && hint.text) {
      var kLabel = new Konva.Label({
        x: hint.point.x,
        y: hint.point.y,
        listening: false
      });
      kLabel.add(new Konva.Tag({
        fill: canvasTagFill(true),
        stroke: '#f57f17',
        strokeWidth: uiSize(1),
        cornerRadius: uiSize(4),
        pointerDirection: 'down',
        pointerWidth: uiSize(8),
        pointerHeight: uiSize(6)
      }));
      kLabel.add(new Konva.Text({
        text: hint.text,
        fontSize: uiSize(11),
        fontFamily: 'Open Sans, sans-serif',
        fill: '#5d4037',
        padding: uiSize(4)
      }));
      kLabel.offsetX(kLabel.width() / 2);
      layer.add(kLabel);
    }
  }

  function clearLiveGuides() {
    state.alignGuides = null;
    state.openingHint = null;
    if (state.layers.preview && !state.drawPreview) {
      state.layers.preview.destroyChildren();
      flushKonva();
    }
  }

  function openingCornerHint(obj) {
    if (!obj || isFreeObject(obj)) return null;
    var place = objectPlacement(obj);
    if (!place || !place.wall) return null;
    var startIn = Math.max(0, place.offset || 0);
    var endIn = Math.max(0, place.lengthIn - (place.offset || 0) - (place.width || 0));
    var fromStart = startIn <= endIn;
    var inches = fromStart ? startIn : endIn;
    var corner = fromStart ? place.wall.a : place.wall.b;
    return {
      point: { x: place.mid.x, y: place.mid.y },
      text: fmtLengthFromFeet(inches / INCHES_PER_FOOT) + (fromStart ? ' from start' : ' from end'),
      corner: corner
    };
  }

  function fmtFeetInches(lenFt) {
    return fmtLengthFromFeet(lenFt);
  }

  function wallCount() {
    var n = activeVertices().length;
    if (n < 2) return 0;
    return isActiveClosed() ? n : n - 1;
  }

  function wallEndIndex(wallIndex) {
    var n = activeVertices().length;
    if (isActiveClosed()) return (wallIndex + 1) % n;
    return wallIndex + 1;
  }

  function wallLengthFeet(wallIndex) {
    var ppf = pixelsPerFoot();
    if (!ppf) return null;
    var a = activeVertices()[wallIndex];
    var b = activeVertices()[wallEndIndex(wallIndex)];
    return dist(a, b) / ppf;
  }

  function pointToSegmentDistance(p, a, b) {
    var dx = b.x - a.x;
    var dy = b.y - a.y;
    var lenSq = dx * dx + dy * dy;
    if (lenSq < 1e-9) return dist(p, a);
    var t = ((p.x - a.x) * dx + (p.y - a.y) * dy) / lenSq;
    t = Math.max(0, Math.min(1, t));
    var proj = { x: a.x + t * dx, y: a.y + t * dy };
    return dist(p, proj);
  }

  function findVertexAtPoint(pos) {
    if (!pos) return -1;
    var hit = hitTolerance(touchHitRadius());
    for (var i = 0; i < activeVertices().length; i++) {
      if (dist(pos, activeVertices()[i]) <= hit) return i;
    }
    return -1;
  }

  function clampToCanvas(p) {
    return {
      x: Math.max(0, Math.min(canvasW(), p.x)),
      y: Math.max(0, Math.min(canvasH(), p.y))
    };
  }

  function snapVertexPosition(idx, raw) {
    var next = raw;
    if (shouldAngleSnap()) {
      next = snapVertexPositionShift(idx, raw);
    }
    return snapVertexToGuides(idx, next);
  }

  /** Move one or more selected corners; group drags keep relative spacing (uniform delta). */
  function applyVertexGroupDrag(anchorIndex, rawPos, finalize) {
    var origins = state.vertexDragOrigins;
    if (!origins || state.selectedVertexIndices.length <= 1) {
      var single = snapVertexPosition(anchorIndex, rawPos);
      activeVertices()[anchorIndex] = { x: single.x, y: single.y };
      var poly = activePolygon();
      if (poly) applyLinkedVertexMove(poly.id, anchorIndex, single);
      return single;
    }

    var anchorOrig = origins[anchorIndex];
    var raw = clampToCanvas(rawPos);
    var dx = raw.x - anchorOrig.x;
    var dy = raw.y - anchorOrig.y;

    if (finalize && pixelsPerFoot() && !state.calibrating && !state.rulerMode) {
      var step = gridStepPx();
      dx = Math.round(dx / step) * step;
      dy = Math.round(dy / step) * step;
    } else if (finalize && state.shiftHeld && pixelsPerFoot() > 0) {
      var snapStep = gridStepPx();
      dx = Math.round(dx / snapStep) * snapStep;
      dy = Math.round(dy / snapStep) * snapStep;
    }

    var poly = activePolygon();
    state.selectedVertexIndices.forEach(function (i) {
      var o = origins[i];
      var next = clampToCanvas({ x: o.x + dx, y: o.y + dy });
      activeVertices()[i] = next;
      if (poly) applyLinkedVertexMove(poly.id, i, next);
    });
    return activeVertices()[anchorIndex];
  }

  function syncSelectedVertexCircles() {
    var layer = state.layers.vertices;
    if (!layer) return;
    var circles = layer.getChildren();
    state.selectedVertexIndices.forEach(function (i) {
      if (circles[i]) circles[i].position(activeVertices()[i]);
    });
  }

  function deleteSelectedWall() {
    if (geometryLocked()) {
      warnGeometryLocked();
      return;
    }
    if (state.selectedWallIndex === null) return;
    var n = activeVertices().length;
    if (wallCount() === 0) return;

    pushUndoSnapshot();
    var wallIndex = state.selectedWallIndex;
    var removeIdx = wallEndIndex(wallIndex);

    activeVertices().splice(removeIdx, 1);
    rematchAllObjects();
    clearWallSelection();

    if (isActiveClosed() && activeVertices().length < 3) {
      setActiveClosed(false);
    }
    if (activeVertices().length === 0) {
      setActiveClosed(false);
    }

    redrawAll();
    setFeedback('Wall removed.', false);
  }

  function isVertexSelected(vertexIndex) {
    return state.selectedVertexIndices.indexOf(vertexIndex) >= 0;
  }

  function cancelPendingWallClick() {
    if (state.wallClickTimer) {
      clearTimeout(state.wallClickTimer);
      state.wallClickTimer = null;
    }
  }

  function resetWallClickState() {
    cancelPendingWallClick();
    state.lastWallClick = null;
    state.wallPointerDown = null;
  }

  function shouldDebounceWallSplit(wallIndex) {
    var now = Date.now();
    if (state.lastWallSplit
        && state.lastWallSplit.wallIndex === wallIndex
        && now - state.lastWallSplit.time < WALL_SPLIT_DEBOUNCE_MS) {
      return true;
    }
    state.lastWallSplit = { wallIndex: wallIndex, time: now };
    return false;
  }

  function isShiftKey(e) {
    return state.shiftHeld || !!(e && e.evt && e.evt.shiftKey);
  }

  function wallVertexIndices(wallIndex) {
    return [wallIndex, wallEndIndex(wallIndex)];
  }

  function handleWallTap(wallIndex) {
    if (!canDragWalls() || wallIndex < 0 || wallIndex >= wallCount()) return;
    cancelPendingWallClick();
    var now = Date.now();
    if (state.lastWallClick
        && state.lastWallClick.wallIndex === wallIndex
        && now - state.lastWallClick.time < WALL_DOUBLE_CLICK_MS) {
      state.lastWallClick = null;
      clearWallSelection();
      splitWallAtPointer(wallIndex);
      return;
    }
    if (state.shiftHeld) {
      state.lastWallClick = null;
      selectVertices(wallVertexIndices(wallIndex), { mode: 'toggle' });
      return;
    }
    selectWall(wallIndex);
    state.lastWallClick = { wallIndex: wallIndex, time: now };
  }

  function clearVertexSelection() {
    if (state.selectedVertexIndices.length === 0 && els.vertexEdit && els.vertexEdit.hasAttribute('hidden')) return;
    state.selectedVertexIndices = [];
    state.vertexDragOrigins = null;
    show(els.vertexEdit, false);
    if (els.canvasWrap) els.canvasWrap.classList.remove('is-vertex-edit');
    if (state.layers.vertices) {
      redrawVertices();
      updateToolbar();
    }
  }

  function selectVertices(vertexIndices, options) {
    options = options || {};
    var indices = (vertexIndices || []).filter(function (idx) {
      return idx >= 0 && idx < activeVertices().length;
    });
    if (options.mode === 'toggle') {
      indices.forEach(function (idx) {
        var at = state.selectedVertexIndices.indexOf(idx);
        if (at >= 0) state.selectedVertexIndices.splice(at, 1);
        else state.selectedVertexIndices.push(idx);
      });
    } else {
      state.selectedVertexIndices = indices.slice();
    }
    if (state.selectedVertexIndices.length > 0) {
      clearRulerSelection();
      clearWallSelection();
      clearObjectSelection();
      show(els.vertexEdit, true);
      if (els.canvasWrap) els.canvasWrap.classList.add('is-vertex-edit');
    } else {
      show(els.vertexEdit, false);
      if (els.canvasWrap) els.canvasWrap.classList.remove('is-vertex-edit');
    }
    redrawVertices();
    updateToolbar();
  }

  function toggleVertexSelection(vertexIndex) {
    selectVertices([vertexIndex], { mode: 'toggle' });
  }

  function deleteSelectedVertex() {
    if (geometryLocked()) {
      warnGeometryLocked();
      return;
    }
    if (state.selectedVertexIndices.length === 0) return;
    if (activeVertices().length - state.selectedVertexIndices.length < 2) {
      setFeedback('Need at least two corners to keep a wall segment.', true);
      return;
    }

    var indices = state.selectedVertexIndices.slice().sort(function (a, b) { return b - a; });
    var copy = activeVertices().map(function (v) { return { x: v.x, y: v.y }; });
    indices.forEach(function (idx) {
      copy.splice(idx, 1);
    });
    var closed = isActiveClosed();
    if (closed && copy.length < 3) closed = false;
    if (!isValidShape(copy, closed)) {
      setFeedback('Removing those corners would make the shape cross itself.', true);
      return;
    }

    pushUndoSnapshot();
    indices.forEach(function (idx) {
      activeVertices().splice(idx, 1);
    });
    rematchAllObjects();
    if (isActiveClosed() && activeVertices().length < 3) {
      setActiveClosed(false);
    }
    if (activeVertices().length === 0) {
      setActiveClosed(false);
    }
    clearVertexSelection();
    redrawAll();
    setFeedback(indices.length > 1 ? 'Corners removed.' : 'Corner removed.', false);
  }

  function findWallAtPoint(pos) {
    if (!pos || wallCount() === 0) return -1;
    var best = -1;
    var wallHit = hitTolerance(touchHitRadius());
    var bestDist = wallHit + 1;
    for (var i = 0; i < wallCount(); i++) {
      var a = activeVertices()[i];
      var b = activeVertices()[wallEndIndex(i)];
      var d = pointToSegmentDistance(pos, a, b);
      if (d <= wallHit && d < bestDist) {
        bestDist = d;
        best = i;
      }
    }
    return best;
  }

  function wallDragCursorFor() {
    return 'move';
  }

  function canDragWalls() {
    return !geometryLocked()
      && wallCount() > 0 && isActiveClosed() && !isPanOrPinchActive() && !state.rulerMode && !state.calibrating
      && !state.armedCatalogKey && !state.placingCatalog;
  }

  function canDragShapes() {
    return !geometryLocked()
      && !isDrawingOpenShape()
      && !isPanOrPinchActive()
      && !state.rulerMode
      && !state.calibrating
      && !state.bgMoveMode
      && !state.panning
      && !state.armedCatalogKey
      && !state.placingCatalog;
  }

  function polygonInteriorAtPoint(pos) {
    if (!pos) return null;
    var hit = null;
    allPolygons().forEach(function (entry) {
      var poly = entry.polygon;
      if (!poly.closed || poly.vertices.length < 3) return;
      if (pointInPolygon(pos, poly.vertices)) hit = entry;
    });
    return hit;
  }

  function clampedShapeDelta(verts, dx, dy) {
    var minX = Infinity;
    var minY = Infinity;
    var maxX = -Infinity;
    var maxY = -Infinity;
    verts.forEach(function (v) {
      if (v.x < minX) minX = v.x;
      if (v.y < minY) minY = v.y;
      if (v.x > maxX) maxX = v.x;
      if (v.y > maxY) maxY = v.y;
    });
    return {
      x: Math.max(-minX, Math.min(canvasW() - maxX, dx)),
      y: Math.max(-minY, Math.min(canvasH() - maxY, dy))
    };
  }

  function shapeBeingDragged() {
    var pending = state.shapePointerDown;
    if (!pending) return null;
    var group = findGroupById(pending.groupId);
    var poly = findPolygonInGroup(group, pending.polygonId);
    if (!group || !poly) return null;
    return { group: group, polygon: poly };
  }

  function onShapePointerDown() {
    if (!canDragShapes()) return false;
    var pos = getPointerPos();
    if (!pos) return false;
    if (findVertexAtPoint(pos) >= 0) return false;
    if (isActiveClosed() && findWallAtPoint(pos) >= 0) return false;
    var hit = polygonInteriorAtPoint(pos);
    if (!hit) return false;
    clearWallSelection();
    clearVertexSelection();
    clearRulerSelection();
    state.shapePointerDown = {
      groupId: hit.group.id,
      polygonId: hit.polygon.id,
      x: pos.x,
      y: pos.y,
      origVerts: copyVertices(hit.polygon.vertices)
    };
    state.didShapeDrag = false;
    return true;
  }

  function tryStartShapeDragFromPending(pos) {
    if (!state.shapePointerDown || state.draggingShape || !pos) return;
    var pending = state.shapePointerDown;
    if (Math.hypot(pos.x - pending.x, pos.y - pending.y) < uiSize(4)) return;
    state.draggingShape = true;
    state.didShapeDrag = false;
    pushUndoSnapshot();
    activatePolygon(pending.groupId, pending.polygonId);
    if (els.canvasWrap) els.canvasWrap.classList.add('is-shape-drag');
    updateCanvasCursor();
    moveShapeDrag(pos);
  }

  function moveShapeDrag(pos) {
    if (!state.draggingShape || !state.shapePointerDown || !pos) return;
    var entry = shapeBeingDragged();
    if (!entry) return;
    var pending = state.shapePointerDown;
    var dx = pos.x - pending.x;
    var dy = pos.y - pending.y;
    if (state.shiftHeld && pixelsPerFoot() > 0) {
      var step = gridStepPx();
      dx = Math.round(dx / step) * step;
      dy = Math.round(dy / step) * step;
    }
    var delta = clampedShapeDelta(pending.origVerts, dx, dy);
    if (Math.hypot(delta.x, delta.y) > 0.5 / uiScale()) {
      state.didShapeDrag = true;
    }
    entry.polygon.vertices = pending.origVerts.map(function (v) {
      return { x: v.x + delta.x, y: v.y + delta.y };
    });
    redrawPolygonLine();
    redrawLabels();
    redrawWalls();
    redrawVertices();
    updateLiveArea();
  }

  function finishShapePointerUp(pos) {
    if (state.draggingShape) {
      finishShapeDrag(pos);
      state.shapePointerDown = null;
      return;
    }
    state.shapePointerDown = null;
  }

  function finishShapeDrag(pos) {
    if (!state.draggingShape) return;
    if (pos) moveShapeDrag(pos);
    var pending = state.shapePointerDown;
    var entry = shapeBeingDragged();
    var hadDrag = state.didShapeDrag;
    if (entry && pending && pending.origVerts && !isValidShape(entry.polygon.vertices, true)) {
      entry.polygon.vertices = copyVertices(pending.origVerts);
      if (hadDrag) discardLastUndoSnapshot();
      state.didShapeDrag = false;
      setFeedback('That position would make the shape cross itself.', true);
    } else if (!hadDrag) {
      if (entry && pending && pending.origVerts) {
        entry.polygon.vertices = copyVertices(pending.origVerts);
      }
      discardLastUndoSnapshot();
    }
    state.draggingShape = false;
    state.shapePointerDown = null;
    if (els.canvasWrap) els.canvasWrap.classList.remove('is-shape-drag');
    updateCanvasCursor();
    redrawAll();
  }

  function refreshSelectedWallLengthInput() {
    if (state.selectedWallIndex !== null && els.wallLength && pixelsPerFoot()) {
      var lenFt = wallLengthFeet(state.selectedWallIndex);
      if (lenFt !== null) els.wallLength.value = fmtFeetInches(lenFt);
    }
  }

  function cancelWallLongPress() {
    if (state.wallLongPressTimer) {
      clearTimeout(state.wallLongPressTimer);
      state.wallLongPressTimer = null;
    }
  }

  function onWallPointerDown(wallIndex) {
    if (!canDragWalls() || wallIndex < 0 || wallIndex >= wallCount()) return;
    var pos = getPointerPos();
    if (!pos) return;
    cancelPendingWallClick();
    cancelWallLongPress();
    clearRulerSelection();
    var a = activeVertices()[wallIndex];
    var b = activeVertices()[wallEndIndex(wallIndex)];
    state.wallPointerDown = {
      wallIndex: wallIndex,
      x: pos.x,
      y: pos.y,
      origA: { x: a.x, y: a.y },
      origB: { x: b.x, y: b.y }
    };
    state.didWallDrag = false;
    if (isCoarsePointer()) {
      state.wallLongPressTimer = setTimeout(function () {
        var pending = state.wallPointerDown;
        if (!pending || state.draggingWall || state.pinching) return;
        var idx = pending.wallIndex;
        var splitPos = { x: pending.x, y: pending.y };
        state.wallPointerDown = null;
        state.didWallDrag = true;
        cancelWallLongPress();
        splitWallAtPoint(idx, splitPos);
      }, WALL_LONG_PRESS_MS);
    }
  }

  function tryStartWallDragFromPending(pos) {
    if (!state.wallPointerDown || state.draggingWall || !pos) return;
    var pending = state.wallPointerDown;
    if (Math.hypot(pos.x - pending.x, pos.y - pending.y) < uiSize(4)) return;
    cancelWallLongPress();
    cancelPendingWallClick();
    state.draggingWall = true;
    state.didWallDrag = false;
    state.wallDragIndex = pending.wallIndex;
    state.wallDragPointerStart = { x: pending.x, y: pending.y };
    state.wallDragOrigA = pending.origA;
    state.wallDragOrigB = pending.origB;
    pushUndoSnapshot();
    selectWall(pending.wallIndex);
    updateCanvasCursor();
    moveWallDrag(pos);
  }

  function finishWallPointerUp(pos) {
    cancelWallLongPress();
    if (state.draggingWall) {
      finishWallDrag(pos);
      state.wallPointerDown = null;
      return;
    }
    if (!state.wallPointerDown) return;
    var wallIndex = state.wallPointerDown.wallIndex;
    state.wallPointerDown = null;
    if (state.didWallDrag) return;
    handleWallTap(wallIndex);
  }

  function moveWallDrag(pos) {
    if (!state.draggingWall || state.wallDragIndex === null || !pos || !state.wallDragPointerStart) return;
    if (!state.wallDragOrigA || !state.wallDragOrigB) return;
    var ox = pos.x - state.wallDragPointerStart.x;
    var oy = pos.y - state.wallDragPointerStart.y;
    if (state.shiftHeld && pixelsPerFoot() > 0) {
      var step = gridStepPx();
      ox = Math.round(ox / step) * step;
      oy = Math.round(oy / step) * step;
    }
    if (Math.hypot(ox, oy) > 0.5 / uiScale()) {
      state.didWallDrag = true;
    }
    var wallIndex = state.wallDragIndex;
    var endIdx = wallEndIndex(wallIndex);
    var nextA = clampToCanvas({
      x: state.wallDragOrigA.x + ox,
      y: state.wallDragOrigA.y + oy
    });
    var nextB = clampToCanvas({
      x: state.wallDragOrigB.x + ox,
      y: state.wallDragOrigB.y + oy
    });
    activeVertices()[wallIndex] = nextA;
    activeVertices()[endIdx] = nextB;
    var dragPoly = activePolygon();
    if (dragPoly) {
      applyLinkedVertexMove(dragPoly.id, wallIndex, nextA);
      applyLinkedVertexMove(dragPoly.id, endIdx, nextB);
    }
    redrawPolygonLine();
    redrawLabels();
    redrawWalls();
    redrawVertices();
    updateLiveArea();
    refreshSelectedWallLengthInput();
  }

  function finishWallDrag(pos) {
    if (!state.draggingWall) return;
    if (pos) moveWallDrag(pos);
    var wallIndex = state.wallDragIndex;
    var endIdx = wallEndIndex(wallIndex);
    var origA = state.wallDragOrigA;
    var origB = state.wallDragOrigB;
    var hadWallDrag = state.didWallDrag;
    if (origA && origB && !isValidShape(activeVertices(), isActiveClosed())) {
      activeVertices()[wallIndex] = origA;
      activeVertices()[endIdx] = origB;
      if (hadWallDrag) discardLastUndoSnapshot();
      state.didWallDrag = false;
      setFeedback('That wall position would make the shape cross itself.', true);
    }
    state.draggingWall = false;
    state.wallDragIndex = null;
    state.wallDragPointerStart = null;
    state.wallDragOrigA = null;
    state.wallDragOrigB = null;
    updateCanvasCursor();
    redrawAll();
  }

  function bindWallDrag(node, wallIndex) {
    node.on('mousedown touchstart', function (e) {
      if (!canDragWalls()) return;
      if (e.evt && e.evt.button !== undefined && e.evt.button !== 0) return;
      e.cancelBubble = true;
      onWallPointerDown(wallIndex);
    });
    node.on('mouseenter', function () {
      if (!canDragWalls() || !state.stage) return;
      if (state.draggingWall || state.draggingVertex || state.spaceHeld) return;
      state.stage.container().style.cursor = 'move';
    });
    node.on('mouseleave', function () {
      if (state.stage && !state.draggingWall) updateCanvasCursor();
    });
  }

  function wallInsertIndex(wallIndex) {
    var n = activeVertices().length;
    if (!isActiveClosed()) return wallIndex + 1;
    return wallIndex === n - 1 ? n : wallIndex + 1;
  }

  function projectPointOnSegment(p, a, b) {
    var dx = b.x - a.x;
    var dy = b.y - a.y;
    var lenSq = dx * dx + dy * dy;
    if (lenSq < 1e-9) return null;
    var len = Math.sqrt(lenSq);
    var minT = hitTolerance(VERTEX_HIT) / len;
    if (minT >= 0.5) return null;
    var maxT = 1 - minT;
    var t = ((p.x - a.x) * dx + (p.y - a.y) * dy) / lenSq;
    t = Math.max(minT, Math.min(maxT, t));
    return { x: a.x + t * dx, y: a.y + t * dy };
  }

  function insertVertexOnWall(wallIndex, rawPos) {
    if (wallIndex < 0 || wallIndex >= wallCount()) return -1;
    var a = activeVertices()[wallIndex];
    var b = activeVertices()[wallEndIndex(wallIndex)];
    var projected = projectPointOnSegment(rawPos, a, b);
    if (!projected) {
      setFeedback('Wall is too short to add a corner here.', true);
      return -1;
    }
    var point = clampToCanvas(projected);
    var insertIdx = wallInsertIndex(wallIndex);
    activeVertices().splice(insertIdx, 0, point);
    if (!isValidShape(activeVertices(), isActiveClosed())) {
      activeVertices().splice(insertIdx, 1);
      setFeedback('That corner would make the shape cross itself.', true);
      return -1;
    }
    rematchAllObjects();
    return insertIdx;
  }

  function startVertexDragAt(vertexIndex) {
    var layer = state.layers.vertices;
    if (!layer) return;
    var circles = layer.getChildren();
    if (vertexIndex < 0 || vertexIndex >= circles.length) return;
    var circle = circles[vertexIndex];
    if (circle && typeof circle.startDrag === 'function') {
      circle.startDrag();
    }
  }

  function splitWallAtPoint(wallIndex, pos) {
    if (!canDragWalls()) return;
    if (shouldDebounceWallSplit(wallIndex)) return;
    if (state.draggingWall) {
      finishWallDrag(getPointerPos());
      state.didWallDrag = false;
    }
    if (!pos) return;
    pushUndoSnapshot();
    var newIdx = insertVertexOnWall(wallIndex, pos);
    if (newIdx < 0) {
      discardLastUndoSnapshot();
      return;
    }
    clearWallSelection();
    redrawAll();
    setFeedback('Corner added — drag to shape the wall.', false);
    requestAnimationFrame(function () {
      startVertexDragAt(newIdx);
    });
  }

  function splitWallAtPointer(wallIndex) {
    splitWallAtPoint(wallIndex, getPointerPos());
  }

  function splitSelectedWall() {
    if (geometryLocked()) {
      warnGeometryLocked();
      return;
    }
    if (state.selectedWallIndex === null) return;
    var idx = state.selectedWallIndex;
    var a = activeVertices()[idx];
    var b = activeVertices()[wallEndIndex(idx)];
    if (!a || !b) return;
    splitWallAtPoint(idx, { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 });
  }

  function bindWallSplit(node, wallIndex) {
    node.on('dblclick dbltap', function (e) {
      if (!canDragWalls()) return;
      if (e.evt) {
        e.evt.preventDefault();
      }
      e.cancelBubble = true;
      resetWallClickState();
      if (state.draggingWall) {
        finishWallDrag(getPointerPos());
        state.didWallDrag = false;
      }
      clearWallSelection();
      splitWallAtPointer(wallIndex);
    });
  }

  function clearWallSelection() {
    if (state.selectedWallIndex === null && els.wallEdit && els.wallEdit.hasAttribute('hidden')) return;
    state.selectedWallIndex = null;
    show(els.wallEdit, false);
    if (els.canvasWrap) els.canvasWrap.classList.remove('is-wall-edit');
    if (els.wallLength) els.wallLength.value = '';
    if (els.wallScaleProject) els.wallScaleProject.checked = false;
    if (els.wallScaleProject) els.wallScaleProject.checked = false;
    if (state.layers.draw) {
      redrawLabels();
      redrawWalls();
      updateToolbar();
    }
  }

  function clearRulerSelection() {
    if (state.selectedRulerId === null && els.rulerEdit && els.rulerEdit.hasAttribute('hidden')) return;
    state.selectedRulerId = null;
    show(els.rulerEdit, false);
    if (els.canvasWrap) els.canvasWrap.classList.remove('is-ruler-edit');
    if (els.rulerLength) els.rulerLength.textContent = '—';
    redrawRulers();
    updateToolbar();
  }

  function selectRuler(rulerId) {
    var ruler = findRulerById(rulerId);
    if (!ruler) return;
    clearWallSelection();
    clearVertexSelection();
    clearObjectSelection();
    state.selectedRulerId = rulerId;
    show(els.rulerEdit, true);
    if (els.canvasWrap) els.canvasWrap.classList.add('is-ruler-edit');
    if (els.rulerLength) els.rulerLength.textContent = rulerLengthLabel(ruler);
    redrawRulers();
    updateToolbar();
  }

  function deleteSelectedRuler() {
    if (state.selectedRulerId === null) return;
    var rulers = projectRulers();
    var idx = rulers.findIndex(function (r) { return r.id === state.selectedRulerId; });
    if (idx < 0) {
      clearRulerSelection();
      return;
    }
    rulers.splice(idx, 1);
    clearRulerSelection();
    redrawRulers();
    setFeedback('Ruler deleted.', false);
  }

  function stopRulerMode() {
    state.rulerMode = false;
    state.rulerDraft = [];
    state.rulerPreview = null;
    raAll('[data-ra-ruler-toggle]').forEach(function (btn) { btn.classList.remove('is-active'); });
    redrawRulers();
    redrawWalls();
    redrawLabels();
    redrawVertices();
  }

  function toggleRulerMode() {
    if (state.rulerMode) {
      stopRulerMode();
      setFeedback('Ruler mode off. Click a ruler to select it; drag its corners to adjust.', false);
      return;
    }

    if (state.calibrating) stopCalibrationMode();
    stopBgMoveMode();
    clearRulerSelection();
    clearWallSelection();
    state.rulerMode = true;
    state.rulerDraft = [];
    state.rulerPreview = null;
    raAll('[data-ra-ruler-toggle]').forEach(function (btn) { btn.classList.add('is-active'); });
    redrawAll();
    var ppf = pixelsPerFoot();
    if (ppf > 0) {
      setFeedback('Click two points to place a ruler. Create as many as you need. Turn Ruler off to select and delete.', false);
    } else {
      setFeedback('Scale is not set — rulers will show lengths after you calibrate.', false);
    }
  }

  function placeRulerPoint(pos, shiftSnap) {
    if (state.rulerDraft.length >= 2) {
      state.rulerDraft = [];
    }

    var point = pos;
    if (state.rulerDraft.length === 1) {
      var prev = state.rulerDraft[0];
      if (shiftSnap) point = angleSnapPoint(pos, prev);
    }
    point = clampToCanvas(point);
    state.rulerDraft.push(point);
    state.rulerPreview = null;

    if (state.rulerDraft.length < 2) {
      redrawRulers();
      return;
    }

    var rulers = projectRulers();
    var ruler = {
      id: state.nextRulerId++,
      p1: { x: state.rulerDraft[0].x, y: state.rulerDraft[0].y },
      p2: { x: state.rulerDraft[1].x, y: state.rulerDraft[1].y }
    };
    rulers.push(ruler);
    state.rulerDraft = [];
    redrawRulers();
    setFeedback('Ruler placed: ' + rulerLengthLabel(ruler) + '. Click two more points for another.', false);
  }

  function cancelRulerDraft() {
    if (!state.rulerMode || state.rulerDraft.length === 0) return false;
    state.rulerDraft = [];
    state.rulerPreview = null;
    redrawRulers();
    return true;
  }

  function selectWall(wallIndex) {
    if (wallIndex < 0 || wallIndex >= wallCount()) return;
    clearRulerSelection();
    clearVertexSelection();
    clearObjectSelection();
    state.selectedWallIndex = wallIndex;
    show(els.wallEdit, true);
    if (els.canvasWrap) els.canvasWrap.classList.add('is-wall-edit');
    var ppf = pixelsPerFoot();
    if (ppf && els.wallLength) {
      var lenFt = wallLengthFeet(wallIndex);
      els.wallLength.value = lenFt !== null ? fmtFeetInches(lenFt) : '';
      els.wallLength.disabled = false;
      if (!isCoarsePointer()) {
        els.wallLength.focus();
        els.wallLength.select();
      }
    } else if (els.wallLength) {
      els.wallLength.value = '';
      els.wallLength.disabled = true;
    }
    if (els.wallApply) els.wallApply.disabled = !ppf;
    updateToolbar();
    redrawLabels();
    redrawWalls();
  }

  function applyWallLength() {
    if (geometryLocked()) {
      warnGeometryLocked();
      return;
    }
    if (state.selectedWallIndex === null) return;
    if (!pixelsPerFoot()) {
      setFeedback('Calibrate scale before editing wall lengths.', true);
      return;
    }

    var wallIndex = state.selectedWallIndex;
    var newFeet;
    try {
      newFeet = parseLengthInput(els.wallLength.value);
    } catch (e) {
      setFeedback(e.message, true);
      return;
    }

    if (els.wallScaleProject && els.wallScaleProject.checked) {
      applyWallLengthScaleProject(wallIndex, newFeet);
      return;
    }

    var ppf = pixelsPerFoot();
    var newPx = newFeet * ppf;
    var proposed = bestWallResizeVertices(activeVertices(), isActiveClosed(), wallIndex, newPx);
    if (!proposed) {
      setFeedback('That wall has no length to adjust.', true);
      return;
    }

    pushUndoSnapshot();
    var previous = copyVertices(activeVertices());
    var i;
    for (i = 0; i < proposed.length; i++) {
      activeVertices()[i] = clampToCanvas(proposed[i]);
    }
    if (!isValidShape(activeVertices(), isActiveClosed())) {
      for (i = 0; i < previous.length; i++) {
        activeVertices()[i] = previous[i];
      }
      discardLastUndoSnapshot();
      setFeedback('That length would make the shape cross itself.', true);
      return;
    }

    rematchAllObjects();
    redrawAll();
    els.wallLength.value = fmtLengthFromFeet(newFeet);
    setFeedback('Wall length updated.', false);
  }

  function copyVertices(verts) {
    return verts.map(function (v) {
      return { x: v.x, y: v.y };
    });
  }

  function interiorCornerAngle(prev, vertex, next) {
    var ax = prev.x - vertex.x;
    var ay = prev.y - vertex.y;
    var bx = next.x - vertex.x;
    var by = next.y - vertex.y;
    var la = Math.hypot(ax, ay);
    var lb = Math.hypot(bx, by);
    if (la < 1e-9 || lb < 1e-9) return Math.PI;
    var cos = (ax * bx + ay * by) / (la * lb);
    return Math.acos(Math.max(-1, Math.min(1, cos)));
  }

  function polygonRightAngleError(verts, closed) {
    if (!closed || verts.length < 3) return 0;
    var n = verts.length;
    var err = 0;
    for (var i = 0; i < n; i++) {
      var prev = verts[(i - 1 + n) % n];
      var next = verts[(i + 1) % n];
      err += Math.abs(interiorCornerAngle(prev, verts[i], next) - Math.PI / 2);
    }
    return err;
  }

  function otherWallsLengthError(original, candidate, closed, skipWallIndex) {
    var n = original.length;
    var wallN = closed ? n : Math.max(0, n - 1);
    var err = 0;
    for (var i = 0; i < wallN; i++) {
      if (i === skipWallIndex) continue;
      var b0 = original[closed ? (i + 1) % n : i + 1];
      var b1 = candidate[closed ? (i + 1) % n : i + 1];
      err += Math.abs(dist(candidate[i], b1) - dist(original[i], b0));
    }
    return err;
  }

  function snapUnitToRightAngle(ux, uy) {
    var angle = Math.atan2(uy, ux);
    var snapped = Math.round(angle / (Math.PI / 2)) * (Math.PI / 2);
    if (angleDelta(angle, snapped) <= SHIFT_SNAP_THRESHOLD_DEG * Math.PI / 180) {
      return { x: Math.cos(snapped), y: Math.sin(snapped) };
    }
    return { x: ux, y: uy };
  }

  function translateVertexCopy(verts, indices, dx, dy) {
    var out = copyVertices(verts);
    var seen = {};
    for (var i = 0; i < indices.length; i++) {
      var idx = indices[i];
      if (idx == null || idx < 0 || idx >= out.length || seen[idx]) continue;
      seen[idx] = true;
      out[idx] = { x: out[idx].x + dx, y: out[idx].y + dy };
    }
    return out;
  }

  function scoreWallResizeCandidate(original, candidate, closed, wallIndex) {
    var originMoved = dist(original[0], candidate[0]);
    var angleErr = polygonRightAngleError(candidate, closed);
    var lengthErr = otherWallsLengthError(original, candidate, closed, wallIndex);
    var travel = 0;
    for (var i = 0; i < original.length; i++) {
      travel += dist(original[i], candidate[i]);
    }
    return angleErr * 40 + lengthErr * 0.02 + (originMoved > 0.5 ? 12 : 0) + travel * 0.001;
  }

  /**
   * Resize wall wallIndex to newPx. The old code always kept vertices[i]
   * fixed and moved vertices[i+1], so a clockwise room grew the top to the
   * right and the bottom to the left, shearing 90° corners. Pick the
   * translation (one end, or that end plus the adjacent side) that keeps
   * corners closest to square and prefers not to move the origin vertex.
   */
  function bestWallResizeVertices(verts, closed, wallIndex, newPx) {
    var n = verts.length;
    if (wallIndex < 0 || n < 2) return null;
    var endIdx = closed ? (wallIndex + 1) % n : wallIndex + 1;
    if (endIdx < 0 || endIdx >= n) return null;
    var start = verts[wallIndex];
    var end = verts[endIdx];
    var currentPx = dist(start, end);
    if (currentPx < 1e-6) return null;

    var ux = (end.x - start.x) / currentPx;
    var uy = (end.y - start.y) / currentPx;
    var unit = snapUnitToRightAngle(ux, uy);
    ux = unit.x;
    uy = unit.y;

    var bPrime = { x: start.x + ux * newPx, y: start.y + uy * newPx };
    var aPrime = { x: end.x - ux * newPx, y: end.y - uy * newPx };
    var dBx = bPrime.x - end.x;
    var dBy = bPrime.y - end.y;
    var dAx = aPrime.x - start.x;
    var dAy = aPrime.y - start.y;

    var nextIdx = closed ? (endIdx + 1) % n : (endIdx + 1 < n ? endIdx + 1 : null);
    var prevIdx = closed ? ((wallIndex - 1 + n) % n) : (wallIndex > 0 ? wallIndex - 1 : null);
    if (nextIdx === wallIndex) nextIdx = null;
    if (prevIdx === endIdx) prevIdx = null;

    var options = [
      translateVertexCopy(verts, [endIdx], dBx, dBy),
      translateVertexCopy(verts, [wallIndex], dAx, dAy)
    ];
    if (nextIdx != null) {
      options.push(translateVertexCopy(verts, [endIdx, nextIdx], dBx, dBy));
    }
    if (prevIdx != null) {
      options.push(translateVertexCopy(verts, [wallIndex, prevIdx], dAx, dAy));
    }

    var best = null;
    var bestScore = Infinity;
    for (var i = 0; i < options.length; i++) {
      var cand = options[i];
      if (!isValidShape(cand, closed)) continue;
      var score = scoreWallResizeCandidate(verts, cand, closed, wallIndex);
      if (score < bestScore) {
        bestScore = score;
        best = cand;
      }
    }
    return best || options[0] || null;
  }

  function applyWallLengthScaleProject(wallIndex, newFeet) {
    var currentFeet = wallLengthFeet(wallIndex);
    if (currentFeet === null || currentFeet < 1e-6) {
      setFeedback('Wall has no length to scale from.', true);
      return;
    }
    if (newFeet < 1e-6) {
      setFeedback('Enter a positive wall length.', true);
      return;
    }
    if (Math.abs(newFeet - currentFeet) < 1 / 192) {
      setFeedback('Length unchanged.', false);
      return;
    }

    var factor = currentFeet / newFeet;
    pushUndoSnapshot();
    scaleActivePixelsPerFoot(factor);
    rematchAllObjects();
    updateScaleUI();
    if (els.wallLength) {
      var updatedFeet = wallLengthFeet(wallIndex);
      els.wallLength.value = updatedFeet !== null ? fmtLengthFromFeet(updatedFeet) : fmtLengthFromFeet(newFeet);
    }
    setFeedback('Project scale updated — all measurements rescaled from this wall.', false);
  }

  function gridStepPx() {
    var ppf = pixelsPerFoot();
    return ppf > 0 ? ppf : GRID_PX;
  }

  /** Snap a length in feet to the nearest 1/16" (matches construction precision). */
  function roundToSixteenthFoot(lenFt) {
    return Math.round(lenFt * 16) / 16;
  }

  function verticesInFeet(vertices) {
    var ppf = pixelsPerFoot();
    if (!ppf) return null;
    return vertices.map(function (v) {
      return {
        x: roundToSixteenthFoot(v.x / ppf),
        y: roundToSixteenthFoot(v.y / ppf)
      };
    });
  }

  function flushKonva() {
    if (state.konvaLayer) state.konvaLayer.batchDraw();
  }

  function bumpEditorToken() {
    state.editorToken += 1;
    return state.editorToken;
  }

  function resetCalibrationUI() {
    stopCalibrationMode();
  }

  function resetEditor() {
    bumpEditorToken();
    resetCanvasGroups();
    ensureInitialCanvas();
    state.editingRoomId = null;
    state.selectedWallIndex = null;
    state.selectedVertexIndices = [];
    state.vertexDragOrigins = null;
    state.draggingVertex = false;
    state.draggingWall = false;
    state.draggingShape = false;
    state.shapePointerDown = null;
    state.didShapeDrag = false;
    state.wallDragIndex = null;
    state.wallDragPointerStart = null;
    state.wallDragOrigA = null;
    state.wallDragOrigB = null;
    state.wallPointerDown = null;
    cancelPendingWallClick();
    state.didWallDrag = false;
    state.didVertexDrag = false;
    state.vertexDragFrom = null;
    state.draftScale = null;
    setWorkingPixelsPerFoot(null);
    state.draftRulers = [];
    revokeDraftBackground();
    state.bgLayout = null;
    stopBgMoveMode();
    resetCalibrationUI();
    clearWallSelection();
    clearVertexSelection();
    clearRulerSelection();
    clearUndoStack();
    if (els.roomName) els.roomName.value = '';
    setWallHeight(DEFAULT_WALL_HEIGHT_FT);
    resetFinishSettings();
    applyEditorPrefs(defaultEditorPrefs(), true);
    show(els.liveArea, false);
    if (state.stage) {
      state.lastUiScale = 0;
      resetViewport();
      loadBackground(null);
      redrawAll();
    }
    updateEditUI();
    updateToolbar();
    captureSavedSnapshot();
  }

  function uiScale() {
    return Math.max(state.viewport.scale, 0.001);
  }

  /**
   * Size in world units for a control that should read as `base` CSS pixels at
   * every zoom. Counter-scale against the stage so badges, labels, and handles
   * stay constant in screen pixels.
   */
  function uiSize(base) {
    return base / uiScale();
  }

  function hitTolerance(base) {
    return uiSize(base);
  }

  function isCoarsePointer() {
    try {
      if (window.matchMedia && window.matchMedia('(pointer: coarse)').matches) return true;
    } catch (err) { /* ignore */ }
    return window.innerWidth <= NARROW_CANVAS_MAX;
  }

  function touchHitRadius() {
    return isCoarsePointer() ? TOUCH_HIT_RADIUS : VERTEX_HIT;
  }

  function wallHitStroke() {
    return isCoarsePointer() ? TOUCH_HIT_STROKE : WALL_HIT * 2;
  }

  function objectHitStroke() {
    return isCoarsePointer() ? TOUCH_HIT_STROKE : 22;
  }

  function objectHitRadius() {
    return isCoarsePointer() ? TOUCH_HIT_RADIUS : 18;
  }

  function isPanOrPinchActive() {
    return !!(state.spaceHeld || state.pinching);
  }

  function isTypingTarget(el) {
    if (!el) return false;
    if (el.isContentEditable) return true;
    var tag = (el.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea' || tag === 'select') return true;
    return !!(el.closest && el.closest('input, textarea, select, [contenteditable="true"], [contenteditable=""]'));
  }

  function canEmptyCanvasPan() {
    return !isDrawingOpenShape()
      && !state.calibrating
      && !state.rulerMode
      && !state.armedCatalogKey
      && !state.placingCatalog
      && !state.bgMoveMode
      && !state.pinching;
  }

  function pointerHitsInteractive(pos) {
    if (!pos) return false;
    if (findVertexAtPoint(pos) >= 0) return true;
    if (isActiveClosed() && findWallAtPoint(pos) >= 0) return true;
    if (findObjectAtPoint(pos)) return true;
    if (findRulerAtPoint(pos)) return true;
    if (polygonInteriorAtPoint(pos)) return true;
    return false;
  }

  function updateShapeHoverCursor() {
    if (!canDragShapes() || !state.stage || state.shapePointerDown || state.draggingShape) return;
    var pos = getPointerPos();
    if (!pos || findVertexAtPoint(pos) >= 0 || (isActiveClosed() && findWallAtPoint(pos) >= 0) || !polygonInteriorAtPoint(pos)) {
      updateCanvasCursor();
      return;
    }
    state.stage.container().style.cursor = 'move';
  }

  function updateCanvasCursor() {
    if (!state.stage) return;
    var el = state.stage.container();
    if (state.panning || state.pinching) {
      el.style.cursor = 'grabbing';
    } else if (state.spaceHeld) {
      el.style.cursor = 'grab';
    } else if (state.draggingVertex) {
      el.style.cursor = 'grabbing';
    } else if (state.draggingShape) {
      el.style.cursor = 'grabbing';
    } else if (state.draggingWall && state.wallDragIndex !== null) {
      el.style.cursor = wallDragCursorFor();
    } else if (state.draggingRuler) {
      el.style.cursor = 'grabbing';
    } else if (state.draggingBg) {
      el.style.cursor = 'grabbing';
    } else if (state.bgMoveMode) {
      el.style.cursor = 'grab';
    } else {
      el.style.cursor = '';
    }
  }

  function naturalWorldScale() {
    return 1;
  }

  function zoomPercentFromScale(scale) {
    return Math.round((scale / naturalWorldScale()) * 100);
  }

  function scaleFromZoomPercent(percent) {
    return (percent / 100) * naturalWorldScale();
  }

  function minZoomPercent() {
    var vw = viewportW();
    var vh = viewportH();
    var pad = 20;
    var cw = canvasW();
    var ch = canvasH();
    if (!vw || !vh || !cw || !ch) return MIN_ZOOM_PERCENT;
    var fitWorldScale = Math.min((vw - pad) / cw, (vh - pad) / ch);
    return Math.max(MIN_ZOOM_PERCENT, zoomPercentFromScale(fitWorldScale));
  }

  function clampZoomPercent(percent) {
    return Math.max(minZoomPercent(), Math.min(MAX_ZOOM_PERCENT, Math.round(percent)));
  }

  function clampWorldScale(scale) {
    return scaleFromZoomPercent(clampZoomPercent(zoomPercentFromScale(scale)));
  }

  function setWorldScale(scale, screenX, screenY) {
    var nextScale = clampWorldScale(scale);
    if (screenX != null && screenY != null && state.viewport.scale > 0) {
      var oldScale = state.viewport.scale;
      var worldX = (screenX - state.viewport.x) / oldScale;
      var worldY = (screenY - state.viewport.y) / oldScale;
      state.viewport.scale = nextScale;
      state.viewport.x = screenX - worldX * nextScale;
      state.viewport.y = screenY - worldY * nextScale;
    } else {
      state.viewport.scale = nextScale;
    }
    state.viewport.zoomPercent = zoomPercentFromScale(state.viewport.scale);
    applyViewport();
  }

  function setZoomPercent(percent, screenX, screenY) {
    setWorldScale(scaleFromZoomPercent(clampZoomPercent(percent)), screenX, screenY);
  }

  function zoomIn() {
    setZoomPercent(state.viewport.zoomPercent * ZOOM_STEP, viewportW() / 2, viewportH() / 2);
  }

  function zoomOut() {
    setZoomPercent(state.viewport.zoomPercent / ZOOM_STEP, viewportW() / 2, viewportH() / 2);
  }

  function viewportContentBounds() {
    var minX = Infinity;
    var minY = Infinity;
    var maxX = -Infinity;
    var maxY = -Infinity;
    var has = false;

    function grow(x, y) {
      if (!isFinite(x) || !isFinite(y)) return;
      has = true;
      minX = Math.min(minX, x);
      minY = Math.min(minY, y);
      maxX = Math.max(maxX, x);
      maxY = Math.max(maxY, y);
    }

    allPolygons().forEach(function (entry) {
      entry.polygon.vertices.forEach(function (v) {
        grow(v.x, v.y);
      });
    });

    state.calibPoints.forEach(function (p) {
      grow(p.x, p.y);
    });

    projectRulers().forEach(function (ruler) {
      if (ruler.p1) grow(ruler.p1.x, ruler.p1.y);
      if (ruler.p2) grow(ruler.p2.x, ruler.p2.y);
    });

    if (state.bgImage) {
      var bx = state.bgImage.x();
      var by = state.bgImage.y();
      grow(bx, by);
      grow(bx + state.bgImage.width(), by + state.bgImage.height());
    }

    if (!has) {
      return { minX: 0, minY: 0, maxX: contentW(), maxY: contentH(), hasContent: false };
    }

    return {
      minX: minX - FIT_LABEL_PAD,
      minY: minY - FIT_LABEL_PAD,
      maxX: maxX + FIT_LABEL_PAD,
      maxY: maxY + FIT_LABEL_PAD,
      hasContent: true
    };
  }

  function stageViewSize() {
    var wrapW = measureWrapWidth();
    var vw = wrapW > 0 ? wrapW : viewportW();
    var vh = viewportH();
    if (state.stage) {
      if (state.stage.width() > 1) vw = state.stage.width();
      if (state.stage.height() > 1) vh = state.stage.height();
    }
    if (els.canvasWrap) {
      var rect = els.canvasWrap.getBoundingClientRect();
      if (rect.width > 1) vw = Math.floor(rect.width);
      if (rect.height > 1) vh = Math.floor(rect.height);
    }
    return { w: Math.max(1, vw), h: Math.max(1, vh) };
  }

  function clampViewportPan() {
    var s = state.viewport.scale;
    var view = stageViewSize();
    var vw = view.w;
    var vh = view.h;
    var bounds = rawContentBounds();
    if (bounds.hasContent) {
      var cx = (bounds.minX + bounds.maxX) / 2;
      var cy = (bounds.minY + bounds.maxY) / 2;
      var bw = (bounds.maxX - bounds.minX) * s;
      var bh = (bounds.maxY - bounds.minY) * s;
      var margin = 8;
      if (bw <= vw) {
        state.viewport.x = vw / 2 - cx * s;
      } else {
        var minX = vw - margin - bounds.maxX * s;
        var maxX = margin - bounds.minX * s;
        state.viewport.x = Math.max(minX, Math.min(maxX, state.viewport.x));
      }
      if (bh <= vh) {
        state.viewport.y = vh / 2 - cy * s;
      } else {
        var minY = vh - margin - bounds.maxY * s;
        var maxY = margin - bounds.minY * s;
        state.viewport.y = Math.max(minY, Math.min(maxY, state.viewport.y));
      }
      return;
    }
    var cw = canvasW() * s;
    var ch = canvasH() * s;
    if (cw <= vw) {
      state.viewport.x = (vw - cw) / 2;
    } else {
      state.viewport.x = Math.max(vw - cw, Math.min(0, state.viewport.x));
    }
    if (ch <= vh) {
      state.viewport.y = (vh - ch) / 2;
    } else {
      state.viewport.y = Math.max(vh - ch, Math.min(0, state.viewport.y));
    }
  }

  function applyViewport() {
    if (!state.worldGroup) return;
    clampViewportPan();
    state.worldGroup.scale({ x: state.viewport.scale, y: state.viewport.scale });
    state.worldGroup.position({ x: state.viewport.x, y: state.viewport.y });
    if (Math.abs(state.viewport.scale - state.lastUiScale) > 0.0001) {
      state.lastUiScale = state.viewport.scale;
      redrawZoomUi();
    } else {
      flushKonva();
    }
    updateZoomUI();
  }

  function redrawZoomUi() {
    drawGrid();
    redrawPolygonLine();
    redrawVertices();
    redrawLabels();
    redrawWalls();
    redrawObjects();
    redrawCalib();
    redrawRulers();
    redrawDrawPreview();
  }

  function panToWorldPoint(x, y) {
    var s = state.viewport.scale;
    state.viewport.x = viewportW() / 2 - x * s;
    state.viewport.y = viewportH() / 2 - y * s;
    applyViewport();
  }

  function panToContentCenter() {
    var bounds = viewportContentBounds();
    if (!bounds.hasContent) return;
    panToWorldPoint((bounds.minX + bounds.maxX) / 2, (bounds.minY + bounds.maxY) / 2);
  }

  function resetViewport() {
    state.viewport.zoomPercent = 100;
    state.viewport.scale = naturalWorldScale();
    state.viewport.x = 0;
    state.viewport.y = 0;
    state.lastUiScale = 0;
    applyViewport();
    updateCanvasCursor();
  }

  function refreshViewportAfterContentChange() {
    if (viewportContentBounds().hasContent) {
      fitViewport();
    } else {
      resetViewport();
    }
  }

  function fitViewport() {
    if (!state.stage) return;
    var view = stageViewSize();
    var vw = view.w;
    var vh = view.h;
    var bounds = isNarrowCanvas(vw) ? rawContentBounds() : viewportContentBounds();
    if (!bounds.hasContent) bounds = viewportContentBounds();
    var bw = Math.max(bounds.maxX - bounds.minX, 1);
    var bh = Math.max(bounds.maxY - bounds.minY, 1);
    var scale;
    if (isNarrowCanvas(vw)) {
      scale = (vw * FIT_WIDTH_RATIO) / bw;
      if (bh * scale > vh * 0.92) scale = (vh * 0.92) / bh;
    } else {
      var pad = ZOOM_FIT_PAD;
      scale = Math.min((vw - pad * 2) / bw, (vh - pad * 2) / bh);
    }
    scale = clampWorldScale(scale);

    var cx = (bounds.minX + bounds.maxX) / 2;
    var cy = (bounds.minY + bounds.maxY) / 2;
    state.viewport.scale = scale;
    state.viewport.zoomPercent = zoomPercentFromScale(scale);
    state.viewport.x = vw / 2 - cx * scale;
    state.viewport.y = vh / 2 - cy * scale;
    state.lastUiScale = 0;
    applyViewport();
    updateCanvasCursor();
    redrawLabels();
  }

  function refitCanvasToLayout() {
    if (!state.stage || !els.canvasWrap) return;
    resizeStage();
    if (viewportContentBounds().hasContent) fitViewport();
  }

  function updateZoomUI() {
    raAll('[data-ra-zoom-label]').forEach(function (el) {
      el.textContent = state.viewport.zoomPercent + '%';
    });
  }

  function zoomAt(screenX, screenY, newScale) {
    setWorldScale(newScale, screenX, screenY);
  }

  function zoomBy(factor) {
    if (!state.stage) return;
    setZoomPercent(state.viewport.zoomPercent * factor, viewportW() / 2, viewportH() / 2);
  }

  function stageToWorld(pos) {
    if (!state.worldGroup || !pos) return null;
    var transform = state.worldGroup.getAbsoluteTransform().copy().invert();
    return transform.point(pos);
  }

  function bindViewportEvents() {
    if (!els.canvasWrap || els.canvasWrap._raViewportBound) return;
    els.canvasWrap._raViewportBound = true;

    if (isFullPage() && typeof ResizeObserver !== 'undefined') {
      new ResizeObserver(function () {
        scheduleStageResize();
      }).observe(els.canvasWrap);
    }

    els.canvasWrap.addEventListener('wheel', function (e) {
      if (!state.stage) return;
      e.preventDefault();
      var rect = state.stage.container().getBoundingClientRect();
      var x = e.clientX - rect.left;
      var y = e.clientY - rect.top;
      if (Math.abs(e.deltaX) > Math.abs(e.deltaY) * 1.4 && !e.ctrlKey && !e.metaKey) {
        state.viewport.x -= e.deltaX;
        state.viewport.y -= e.deltaY;
        applyViewport();
        return;
      }
      var intensity = e.ctrlKey || e.metaKey ? Math.min(Math.abs(e.deltaY) / 80, 1) : 1;
      var dir = e.deltaY < 0 ? 1 : -1;
      var factor = dir > 0 ? Math.pow(ZOOM_STEP, intensity) : 1 / Math.pow(ZOOM_STEP, intensity);
      setZoomPercent(state.viewport.zoomPercent * factor, x, y);
    }, { passive: false });

    document.addEventListener('keydown', function (e) {
      if (e.code === 'ShiftLeft' || e.code === 'ShiftRight') {
        state.shiftHeld = true;
        updateDrawPreviewFromPointer();
        return;
      }
      if (e.code === 'AltLeft' || e.code === 'AltRight') {
        state.altHeld = true;
        updateDrawPreviewFromPointer();
        return;
      }
      if (e.code !== 'Space' || e.repeat) return;
      if (isTypingTarget(e.target)) return;
      e.preventDefault();
      state.spaceHeld = true;
      if (els.canvasWrap) els.canvasWrap.classList.add('is-pan-ready');
      updateCanvasCursor();
      redrawVertices();
    });

    window.addEventListener('blur', function () {
      state.altHeld = false;
      state.shiftHeld = false;
      updateDrawPreviewFromPointer();
    });
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) {
        state.altHeld = false;
        state.shiftHeld = false;
      }
    });

    document.addEventListener('keyup', function (e) {
      if (e.code === 'ShiftLeft' || e.code === 'ShiftRight') {
        state.shiftHeld = false;
        updateDrawPreviewFromPointer();
        return;
      }
      if (e.code === 'AltLeft' || e.code === 'AltRight') {
        state.altHeld = false;
        updateDrawPreviewFromPointer();
        return;
      }
      if (e.code !== 'Space') return;
      state.spaceHeld = false;
      state.panning = false;
      state.lastPanScreen = null;
      if (els.canvasWrap) els.canvasWrap.classList.remove('is-pan-ready', 'is-panning');
      updateCanvasCursor();
      redrawVertices();
    });

    document.addEventListener('mousemove', function (e) {
      if (!state.stage || (!state.shapePointerDown && !state.draggingShape)) return;
      var rect = state.stage.container().getBoundingClientRect();
      var pos = stageToWorld({ x: e.clientX - rect.left, y: e.clientY - rect.top });
      if (state.shapePointerDown && !state.draggingShape) {
        tryStartShapeDragFromPending(pos);
      }
      if (state.draggingShape) moveShapeDrag(pos);
    });

    document.addEventListener('mouseup', function () {
      if (state.shapePointerDown || state.draggingShape) {
        finishShapePointerUp(getPointerPos());
      }
      if (state.wallPointerDown || state.draggingWall) {
        finishWallPointerUp(getPointerPos());
      }
      if (!state.panning) return;
      state.panning = false;
      state.emptyPanFromTouch = false;
      state.lastPanScreen = null;
      if (els.canvasWrap) els.canvasWrap.classList.remove('is-panning');
      updateCanvasCursor();
    });
  }

  function screenPosFromClient(clientX, clientY) {
    if (!state.stage) return { x: 0, y: 0 };
    var rect = state.stage.container().getBoundingClientRect();
    return { x: clientX - rect.left, y: clientY - rect.top };
  }

  function pinchDistance(t0, t1) {
    return Math.hypot(t1.clientX - t0.clientX, t1.clientY - t0.clientY);
  }

  function pinchCenterScreen(t0, t1) {
    return screenPosFromClient((t0.clientX + t1.clientX) / 2, (t0.clientY + t1.clientY) / 2);
  }

  function abortActivePointerGestures() {
    cancelWallLongPress();
    if (state.draggingVertex && state.layers.vertices) {
      state.layers.vertices.getChildren().forEach(function (node) {
        if (node && typeof node.stopDrag === 'function' && node.isDragging && node.isDragging()) {
          node.stopDrag();
        }
      });
    }
    state.draggingVertex = false;
    state.vertexDragFrom = null;
    state.draggingWall = false;
    state.wallPointerDown = null;
    state.wallDragIndex = null;
    state.draggingShape = false;
    state.shapePointerDown = null;
    state.draggingObject = false;
    state.objectPointerDown = null;
    state.draggingRuler = false;
    state.panning = false;
    state.lastPanScreen = null;
    state.emptyPanFromTouch = false;
    if (els.canvasWrap) els.canvasWrap.classList.remove('is-panning', 'is-shape-drag');
  }

  function startPinch(touches) {
    abortActivePointerGestures();
    state.pinching = true;
    state.didPan = true;
    state.pinch = {
      startDist: pinchDistance(touches[0], touches[1]),
      startScale: state.viewport.scale,
      lastCenter: pinchCenterScreen(touches[0], touches[1])
    };
    if (els.canvasWrap) els.canvasWrap.classList.add('is-panning');
    updateCanvasCursor();
    redrawVertices();
    redrawWalls();
    redrawObjects();
  }

  function updatePinch(touches) {
    if (!state.pinch || touches.length < 2) return;
    var center = pinchCenterScreen(touches[0], touches[1]);
    var dist = pinchDistance(touches[0], touches[1]);
    var last = state.pinch.lastCenter;
    state.viewport.x += center.x - last.x;
    state.viewport.y += center.y - last.y;
    state.pinch.lastCenter = center;
    var scale = state.pinch.startScale * (dist / Math.max(state.pinch.startDist, 1));
    setWorldScale(scale, center.x, center.y);
  }

  function endPinch() {
    if (!state.pinching) return;
    state.pinching = false;
    state.pinch = null;
    if (els.canvasWrap) els.canvasWrap.classList.remove('is-panning');
    updateCanvasCursor();
    redrawVertices();
    redrawWalls();
    redrawObjects();
  }

  function bindCanvasTouchGestures() {
    if (!els.canvasWrap || els.canvasWrap._raTouchGesturesBound) return;
    els.canvasWrap._raTouchGesturesBound = true;

    els.canvasWrap.addEventListener('touchstart', function (e) {
      if (!state.stage || e.touches.length < 2) return;
      e.preventDefault();
      e.stopPropagation();
      startPinch(e.touches);
    }, { passive: false, capture: true });

    els.canvasWrap.addEventListener('touchmove', function (e) {
      if (!state.stage) return;
      if (e.touches.length >= 2) {
        e.preventDefault();
        e.stopPropagation();
        if (!state.pinching) startPinch(e.touches);
        else updatePinch(e.touches);
        return;
      }
      if (state.pinching) {
        e.preventDefault();
        e.stopPropagation();
      }
    }, { passive: false, capture: true });

    function onTouchEnd(e) {
      if (!state.pinching) return;
      e.preventDefault();
      e.stopPropagation();
      if (e.touches.length >= 2) {
        updatePinch(e.touches);
        return;
      }
      endPinch();
    }

    els.canvasWrap.addEventListener('touchend', onTouchEnd, { passive: false, capture: true });
    els.canvasWrap.addEventListener('touchcancel', onTouchEnd, { passive: false, capture: true });
  }

  function resizeStage() {
    if (!state.stage || !els.canvasWrap) return;
    var vp = getViewportDimensions();
    if (vp.w === state.viewportW && vp.h === state.viewportH) {
      if (viewportContentBounds().hasContent) fitViewport();
      return;
    }

    if (isFullPage()) {
      syncDrawableDimensions();
      state.stage.width(state.viewportW);
      state.stage.height(state.viewportH);
      drawGrid();
      redrawAll();
      if (viewportContentBounds().hasContent) {
        fitViewport();
      } else {
        applyViewport();
      }
      return;
    }

    var oldContentW = state.viewportW;
    var oldContentH = state.viewportH;
    var oldWorldW = state.canvasW;
    var oldWorldH = state.canvasH;

    syncDrawableDimensions();

    if (oldContentW > 0 && oldContentH > 0) {
      var factor = canvasScaleFactor(oldContentW, oldContentH, contentW(), contentH());
      if (state.groups.length && allPolygons().some(function (e) { return e.polygon.vertices.length; })) {
        scaleAllCanvasPoints(oldContentW, oldContentH, contentW(), contentH());
        clearUndoStack();
      }
      if (state.calibPoints.length) {
        state.calibPoints = scalePoints(state.calibPoints, oldContentW, oldContentH, contentW(), contentH());
      }
      scaleProjectRulers(oldContentW, oldContentH, contentW(), contentH());
      scaleBgLayoutState(oldContentW, oldContentH, contentW(), contentH());
      if (Math.abs(factor - 1) > 0.0005) {
        scaleActivePixelsPerFoot(factor);
      }
    }

    state.stage.width(state.viewportW);
    state.stage.height(state.viewportH);
    if (state.bgImage) {
      if (oldContentW > 0 && oldContentH > 0) {
        applyBackgroundLayoutToKonva();
      } else {
        layoutBackgroundImage(true);
      }
    }
    drawGrid();
    redrawAll();
    if (viewportContentBounds().hasContent) {
      fitViewport();
    } else if (oldWorldW !== state.canvasW || oldWorldH !== state.canvasH) {
      applyViewport();
    }
  }

  function scheduleStageResize() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(resizeStage, 120);
  }

  function initStage() {
    var container = document.getElementById('ra-konva-container');
    if (!container) return;

    if (state.stage) {
      state.stage.destroy();
    }

    syncDrawableDimensions();

    if (typeof Konva.pixelRatio === 'number') {
      Konva.pixelRatio = Math.max(window.devicePixelRatio || 1, 1);
    }

    state.stage = new Konva.Stage({
      container: 'ra-konva-container',
      width: state.viewportW,
      height: state.viewportH
    });

    state.worldGroup = new Konva.Group();
    state.konvaLayer = new Konva.Layer();
    state.stage.add(state.konvaLayer);
    state.konvaLayer.add(state.worldGroup);

    state.layers.bg = new Konva.Group();
    state.layers.grid = new Konva.Group();
    state.layers.draw = new Konva.Group();
    state.layers.finish = new Konva.Group({ listening: false });
    state.layers.objects = new Konva.Group();
    state.layers.vertices = new Konva.Group();
    state.layers.walls = new Konva.Group();
    state.layers.labels = new Konva.Group();
    state.layers.preview = new Konva.Group({ listening: false });
    state.layers.calib = new Konva.Group();
    state.layers.rulers = new Konva.Group();

    state.worldGroup.add(state.layers.bg);
    state.worldGroup.add(state.layers.grid);
    state.worldGroup.add(state.layers.draw);
    state.worldGroup.add(state.layers.finish);
    state.worldGroup.add(state.layers.objects);
    state.worldGroup.add(state.layers.walls);
    state.worldGroup.add(state.layers.vertices);
    state.worldGroup.add(state.layers.labels);
    state.worldGroup.add(state.layers.preview);
    state.worldGroup.add(state.layers.calib);
    state.worldGroup.add(state.layers.rulers);

    resetViewport();
    drawGrid();
    redrawAll();

    state.stage.on('mousedown touchstart', function (e) {
      if (state.pinching) return;
      var evt = e.evt;
      var isMiddle = evt && evt.button === 1;
      var isSpacePan = evt && evt.button === 0 && state.spaceHeld;
      var isPrimary = !evt || evt.button === undefined || evt.button === 0;
      var emptyPan = isPrimary && canEmptyCanvasPan() && !pointerHitsInteractive(getPointerPos());
      if (!isMiddle && !isSpacePan && !emptyPan) {
        if (isPrimary) onShapePointerDown();
        return;
      }
      if (evt && evt.preventDefault) evt.preventDefault();
      state.panning = true;
      state.emptyPanFromTouch = emptyPan && !(isMiddle || isSpacePan);
      state.didPan = false;
      state.lastPanScreen = state.stage.getPointerPosition();
      if (els.canvasWrap) els.canvasWrap.classList.add('is-panning');
      updateCanvasCursor();
    });

    state.stage.on('mousemove touchmove', function () {
      if (state.pinching) return;
      var pos = getPointerPos();
      if (state.shapePointerDown && !state.draggingShape) {
        tryStartShapeDragFromPending(pos);
      }
      if (state.draggingShape) {
        moveShapeDrag(pos);
        return;
      }
      if (state.wallPointerDown && !state.draggingWall) {
        tryStartWallDragFromPending(pos);
      }
      if (state.draggingWall) {
        moveWallDrag(pos);
        return;
      }
      if (!state.panning || !state.lastPanScreen) return;
      var pos = state.stage.getPointerPosition();
      if (!pos) return;
      var dx = pos.x - state.lastPanScreen.x;
      var dy = pos.y - state.lastPanScreen.y;
      if (Math.abs(dx) > 1 || Math.abs(dy) > 1) state.didPan = true;
      state.viewport.x += dx;
      state.viewport.y += dy;
      state.lastPanScreen = pos;
      applyViewport();
    });

    state.stage.on('mousemove touchmove', function () {
      if (state.pinching) return;
      if (state.objectPointerDown && !state.draggingObject) {
        var movePos = getPointerPos();
        if (movePos && state.objectPointerDown.start && dist(movePos, state.objectPointerDown.start) > 4) {
          pushUndoSnapshot();
          state.draggingObject = true;
          selectObject(state.objectPointerDown.id);
        }
      }
      if (state.draggingObject && state.objectPointerDown) {
        var obj = state.objects.find(function (o) { return o.id === state.objectPointerDown.id; });
        if (obj) moveObjectAlongWall(obj, getPointerPos());
        return;
      }
      if (state.panning || !state.rulerMode || state.rulerDraft.length !== 1) {
        if (!state.rulerMode && state.rulerPreview) {
          state.rulerPreview = null;
          redrawRulers();
        }
        if (!state.panning && !state.draggingWall && !state.draggingVertex && !state.draggingShape && !state.draggingObject) {
          updateDrawPreviewFromPointer();
          updateShapeHoverCursor();
        }
        return;
      }
      var pos = getPointerPos();
      if (!pos) return;
      var preview = pos;
      if (shouldAngleSnap()) preview = angleSnapPoint(pos, state.rulerDraft[0]);
      state.rulerPreview = clampToCanvas(preview);
      redrawRulers();
    });

    state.stage.on('mouseup touchend', function () {
      if (state.pinching) return;
      if (state.draggingObject) {
        state.draggingObject = false;
        state.objectPointerDown = null;
        clearLiveGuides();
        rematchAllObjects();
        redrawAll();
        return;
      }
      state.objectPointerDown = null;
      finishShapePointerUp(getPointerPos());
      finishWallPointerUp(getPointerPos());
      if (state.draggingWall || state.draggingShape) return;
      if (!state.panning) return;
      state.panning = false;
      state.emptyPanFromTouch = false;
      state.lastPanScreen = null;
      if (els.canvasWrap) els.canvasWrap.classList.remove('is-panning');
      updateCanvasCursor();
    });

    state.stage.on('mouseleave', function () {
      clearDrawPreview();
    });

    state.stage.on('click tap', onStageClick);
    state.stage.on('dblclick dbltap', onStageDblClick);

    bindViewportEvents();
    bindCanvasTouchGestures();

    drawGrid();
    redrawAll();
  }

  function drawGrid() {
    var layer = state.layers.grid;
    if (!layer) return;
    layer.destroyChildren();
    var step = gridStepPx();
    var w = canvasW();
    var h = canvasH();
    var minor = uiSize(0.5);
    var major = uiSize(1);
    for (var x = 0; x <= w; x += step) {
      layer.add(new Konva.Line({
        points: [x, 0, x, h],
        stroke: gridStrokeColor(),
        strokeWidth: x % (step * 5) === 0 ? major : minor,
        listening: false
      }));
    }
    for (var y = 0; y <= h; y += step) {
      layer.add(new Konva.Line({
        points: [0, y, w, y],
        stroke: gridStrokeColor(),
        strokeWidth: y % (step * 5) === 0 ? major : minor,
        listening: false
      }));
    }
    flushKonva();
  }

  function syncBackgroundUI() {
    var hasBg = !!state.activeBackgroundUrl || !!state.bgImage;
    show(els.bgRemove, hasBg);
    show(els.bgDeleteCanvas, hasBg);
    show(els.bgShowWrap, hasBg);
    show(els.bgMoveToggle, hasBg);
    if (els.bgMoveToggle) {
      els.bgMoveToggle.disabled = !hasBg || geometryLocked();
      els.bgMoveToggle.setAttribute('aria-disabled', (!hasBg || geometryLocked()) ? 'true' : 'false');
    }
    if (els.bgShowImage) {
      els.bgShowImage.checked = state.bgImageVisible;
    }
    if (!hasBg || geometryLocked()) stopBgMoveMode();
    applyBackgroundImageVisibility();
  }

  function applyBackgroundImageVisibility() {
    if (!state.layers.bg) return;
    var hasBg = !!state.activeBackgroundUrl || !!state.bgImage;
    state.layers.bg.visible(hasBg && state.bgImageVisible);
    flushKonva();
  }

  function setBackgroundImageVisible(visible) {
    state.bgImageVisible = !!visible;
    if (els.bgShowImage) els.bgShowImage.checked = state.bgImageVisible;
    applyBackgroundImageVisibility();
  }

  function layoutBackgroundImage(forceCenter) {
    if (!state.bgImage || !state.bgNaturalW || !state.bgNaturalH) return;
    if (!forceCenter && state.bgLayout) {
      applyBackgroundLayoutToKonva();
      return;
    }
    var cw = contentW();
    var ch = contentH();
    var scale = Math.min(cw / state.bgNaturalW, ch / state.bgNaturalH);
    var w = state.bgNaturalW * scale;
    var h = state.bgNaturalH * scale;
    state.bgLayout = {
      x: (cw - w) / 2,
      y: (ch - h) / 2,
      width: w,
      height: h
    };
    applyBackgroundLayoutToKonva();
  }

  function scaleBackgroundLayout(fromW, fromH, toW, toH) {
    scaleBgLayoutState(fromW, fromH, toW, toH);
    applyBackgroundLayoutToKonva();
  }

  function bindBackgroundImageEvents(kImg) {
    if (kImg._raBgBound) return;
    kImg._raBgBound = true;
    kImg.on('dragstart', function () {
      if (!state.bgMoveMode) return;
      state.draggingBg = true;
      if (els.canvasWrap) els.canvasWrap.classList.add('is-bg-dragging');
      updateCanvasCursor();
    });
    kImg.on('dragmove', function () {
      if (!state.bgMoveMode) return;
      var pos = clampBgPosition(kImg.x(), kImg.y(), kImg.width(), kImg.height());
      kImg.position(pos);
    });
    kImg.on('dragend', function () {
      if (!state.draggingBg) return;
      state.draggingBg = false;
      if (els.canvasWrap) els.canvasWrap.classList.remove('is-bg-dragging');
      syncBackgroundLayoutFromKonva();
      updateCanvasCursor();
    });
  }

  function clampBgPosition(x, y, w, h) {
    var margin = 40;
    return {
      x: Math.max(-w + margin, Math.min(canvasW() - margin, x)),
      y: Math.max(-h + margin, Math.min(canvasH() - margin, y))
    };
  }

  function updateBackgroundInteraction() {
    if (!state.bgImage) return;
    var canMove = state.bgMoveMode && !state.spaceHeld && !state.panning;
    state.bgImage.draggable(canMove);
    state.bgImage.listening(canMove);
    flushKonva();
  }

  function stopBgMoveMode() {
    state.bgMoveMode = false;
    state.draggingBg = false;
    if (els.bgMoveToggle) els.bgMoveToggle.classList.remove('is-active');
    if (els.canvasWrap) {
      els.canvasWrap.classList.remove('is-bg-move', 'is-bg-dragging');
    }
    updateBackgroundInteraction();
    updateCanvasCursor();
  }

  function toggleBgMoveMode() {
    if (!state.bgImage) return;
    if (geometryLocked()) {
      stopBgMoveMode();
      setFeedback('Unlock layers to move the floor plan.', true);
      return;
    }
    if (state.bgMoveMode) {
      stopBgMoveMode();
      setFeedback('Image move mode off.', false);
      return;
    }
    stopRulerMode();
    if (state.calibrating) {
      state.calibrating = false;
      state.calibPoints = [];
      resetCalibrationUI();
    }
    clearWallSelection();
    clearVertexSelection();
    state.bgMoveMode = true;
    if (els.bgMoveToggle) els.bgMoveToggle.classList.add('is-active');
    if (els.canvasWrap) els.canvasWrap.classList.add('is-bg-move');
    updateBackgroundInteraction();
    setFeedback('Drag the floor plan to reposition it. Click Move image again when done.', false);
  }

  function ensureStage() {
    if (!state.stage || !state.konvaLayer || !state.konvaLayer.getStage()) {
      initStage();
    }
  }

  function loadBackground(url) {
    ensureStage();
    var layer = state.layers.bg;
    if (!layer) return;

    var token = bumpEditorToken();
    layer.destroyChildren();
    state.bgImage = null;
    state.bgNaturalW = 0;
    state.bgNaturalH = 0;
    state.activeBackgroundUrl = null;
    state.bgLayout = null;
    stopBgMoveMode();
    if (!url) {
      flushKonva();
      syncBackgroundUI();
      return;
    }

    state.activeBackgroundUrl = url;
    state.bgImageVisible = true;

    var img = new Image();
    if (url.indexOf('blob:') !== 0 && url.indexOf('data:') !== 0) {
      img.crossOrigin = 'anonymous';
    }
    img.onload = function () {
      if (token !== state.editorToken) return;
      if (state.activeBackgroundUrl !== url) return;
      if (!state.layers.bg) return;
      state.bgNaturalW = img.naturalWidth || img.width;
      state.bgNaturalH = img.naturalHeight || img.height;
      var kImg = new Konva.Image({
        image: img,
        listening: false,
        opacity: 0.85
      });
      state.layers.bg.add(kImg);
      state.bgImage = kImg;
      bindBackgroundImageEvents(kImg);
      if (state.bgLayout) {
        applyBackgroundLayoutToKonva();
      } else {
        layoutBackgroundImage(true);
      }
      updateBackgroundInteraction();
      applyBackgroundImageVisibility();
      syncBackgroundUI();
    };
    img.onerror = function () {
      if (token !== state.editorToken) return;
      setFeedback('Could not load floor plan image.', true);
      syncBackgroundUI();
    };
    img.src = url.indexOf('blob:') === 0 || url.indexOf('data:') === 0
      ? url
      : url + (url.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now();
    syncBackgroundUI();
  }

  function redrawFlooringDirection() {
    var layer = state.layers.finish;
    if (!layer) return;
    layer.destroyChildren();
    if (!hasAnyClosedPolygon() || !pixelsPerFoot()) {
      flushKonva();
      return;
    }
    var deg = flooringDirectionDeg();
    var rad = deg * Math.PI / 180;
    var nx = -Math.sin(rad);
    var ny = Math.cos(rad);
    var stroke = isAdminDark() ? 'rgba(125, 211, 252, 0.62)' : 'rgba(2, 71, 104, 0.42)';
    var step = Math.max(14, (pixelsPerFoot() || 12) * 0.9);
    allClosedPolygons().forEach(function (entry) {
      var verts = entry.polygon.vertices || [];
      if (verts.length < 3) return;
      var minX = verts[0].x;
      var maxX = verts[0].x;
      var minY = verts[0].y;
      var maxY = verts[0].y;
      verts.forEach(function (v) {
        if (v.x < minX) minX = v.x;
        if (v.x > maxX) maxX = v.x;
        if (v.y < minY) minY = v.y;
        if (v.y > maxY) maxY = v.y;
      });
      var group = new Konva.Group({
        listening: false,
        clipFunc: function (ctx) {
          ctx.beginPath();
          verts.forEach(function (v, i) {
            if (i === 0) ctx.moveTo(v.x, v.y);
            else ctx.lineTo(v.x, v.y);
          });
          ctx.closePath();
        }
      });
      var cx = (minX + maxX) / 2;
      var cy = (minY + maxY) / 2;
      var span = Math.hypot(maxX - minX, maxY - minY) + step * 2;
      var count = Math.ceil(span / step);
      for (var i = -count; i <= count; i++) {
        var ox = cx + nx * i * step;
        var oy = cy + ny * i * step;
        var dx = Math.cos(rad) * span;
        var dy = Math.sin(rad) * span;
        group.add(new Konva.Line({
          points: [ox - dx, oy - dy, ox + dx, oy + dy],
          stroke: stroke,
          strokeWidth: uiSize(1),
          listening: false
        }));
      }
      var arrowLen = Math.min(maxX - minX, maxY - minY) * 0.28;
      if (arrowLen > 12) {
        var ax = Math.cos(rad) * arrowLen / 2;
        var ay = Math.sin(rad) * arrowLen / 2;
        group.add(new Konva.Arrow({
          points: [cx - ax, cy - ay, cx + ax, cy + ay],
          stroke: isAdminDark() ? '#7dd3fc' : '#024768',
          fill: isAdminDark() ? '#7dd3fc' : '#024768',
          strokeWidth: uiSize(1.5),
          pointerLength: uiSize(7),
          pointerWidth: uiSize(7),
          listening: false,
          opacity: 0.85
        }));
      }
      layer.add(group);
    });
    flushKonva();
  }

  function redrawAll() {
    redrawPolygonLine();
    redrawFlooringDirection();
    redrawObjects();
    redrawVertices();
    redrawLabels();
    redrawWalls();
    redrawCalib();
    redrawRulers();
    redrawDrawPreview();
    updateLiveArea();
    updateToolbar();
  }

  function hexToRgba(hex, alpha) {
    var h = String(hex || DEFAULT_GROUP_COLOR).replace('#', '');
    if (h.length === 3) {
      h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
    }
    var r = parseInt(h.substring(0, 2), 16) || 2;
    var g = parseInt(h.substring(2, 4), 16) || 71;
    var b = parseInt(h.substring(4, 6), 16) || 104;
    return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
  }

  function roomCanvasRaw(room) {
    if (room && room.canvas && room.canvas.groups) return room.canvas;
    if (room && room.vertices) return room.vertices;
    return null;
  }

  function roomHasDrawableData(room) {
    var raw = roomCanvasRaw(room);
    if (!raw) return false;
    return normalizeCanvasInput(raw).groups.length > 0;
  }

  function reloadSavedRoomCanvas(room) {
    var project = activeProject();
    if (!project || !room) return;
    var source = roomSourceCanvas(room, project);
    var target = hydrateScaleTarget(roomCanvasRaw(room), source.w, source.h);
    hydrateCanvas(roomCanvasRaw(room), target.fromW, target.fromH, target.toW, target.toH);
    applyWorkingScaleFromSaved(
      room.scale_pixels_per_foot || project.scale_pixels_per_foot,
      target
    );
    updateScaleUI();
    state.editingRoomId = room.id;
    if (els.roomName) els.roomName.value = room.name;
    clearUndoStack();
    updateEditUI();
    syncTypedWallsFromCanvas();
    renderTypedWallsList();
    updateTypedCloseBar();
    renderPhotos();
    refreshViewportAfterContentChange();
    redrawAll();
    requestAnimationFrame(refitCanvasToLayout);
  }

  function addAreaSameGroup() {
    if (geometryLocked()) {
      warnGeometryLocked();
      return;
    }
    if (!isActiveClosed()) {
      setFeedback('Close the current shape before adding another area.', true);
      return;
    }
    pushUndoSnapshot();
    startNewPolygon(state.activeGroupId);
    redrawAll();
    setFeedback('Draw another area in the same color group.', false);
  }

  function addAreaNewGroup() {
    if (geometryLocked()) {
      warnGeometryLocked();
      return;
    }
    if (!isActiveClosed()) {
      setFeedback('Close the current shape before starting a new color group.', true);
      return;
    }
    pushUndoSnapshot();
    startNewGroup();
    redrawAll();
    setFeedback('Draw an area in a new color group.', false);
  }

  function isDrawingOpenShape() {
    var poly = activePolygon();
    return !!poly && !poly.closed;
  }

  function removeLastDrawVertex() {
    if (geometryLocked()) {
      warnGeometryLocked();
      return false;
    }
    if (!isDrawingOpenShape() || !activeVertices().length) return false;
    pushUndoSnapshot();
    activeVertices().pop();
    clearWallSelection();
    clearVertexSelection();
    redrawAll();
    setFeedback('Last corner removed.', false);
    return true;
  }

  function cancelActiveDrawing() {
    if (!isDrawingOpenShape()) return false;

    var group = activeGroup();
    var poly = activePolygon();
    if (!group || !poly) return false;

    pushUndoSnapshot();
    removeObjectsForPolygon(poly.id);
    group.polygons = group.polygons.filter(function (p) { return p.id !== poly.id; });
    if (!group.polygons.length) {
      state.groups = state.groups.filter(function (g) { return g.id !== group.id; });
    }

    var closedEntries = allClosedPolygons();
    if (closedEntries.length) {
      var target = closedEntries[closedEntries.length - 1];
      state.activeGroupId = target.group.id;
      state.activePolygonId = target.polygon.id;
    } else {
      var any = allPolygons()[0];
      if (any) {
        state.activeGroupId = any.group.id;
        state.activePolygonId = any.polygon.id;
      } else {
        state.activeGroupId = null;
        state.activePolygonId = null;
        ensureInitialCanvas();
      }
    }

    clearWallSelection();
    clearVertexSelection();
    redrawAll();
    setFeedback('Drawing cancelled.', false);
    return true;
  }

  function lastDrawVertex() {
    if (isActiveClosed()) return null;
    var verts = activeVertices();
    if (!verts.length) return null;
    return verts[verts.length - 1];
  }

  function snapDrawPreviewPoint(pos) {
    var prev = lastDrawVertex();
    if (!prev || !pos) return null;
    var point = shouldAngleSnap() ? angleSnapPoint(pos, prev) : pos;
    var verts = activeVertices();
    if (verts.length >= 3) {
      var first = verts[0];
      if (dist(point, first) <= hitTolerance(CLOSE_HIT)) {
        return { x: first.x, y: first.y };
      }
    }
    return clampToCanvas(point);
  }

  function clearDrawPreview() {
    var layer = state.layers.preview;
    var hadPreview = !!state.drawPreview || (layer && layer.getChildren && layer.getChildren().length);
    state.drawPreview = null;
    if (hadPreview) redrawDrawPreview();
  }

  function updateDrawPreviewFromPointer() {
    if (
      state.panning
      || state.rulerMode
      || state.calibrating
      || state.spaceHeld
      || state.draggingWall
      || state.draggingVertex
      || state.bgMoveMode
    ) {
      clearDrawPreview();
      return;
    }
    if (!lastDrawVertex()) {
      clearDrawPreview();
      return;
    }
    var pos = getPointerPos();
    if (!pos) return;
    state.drawPreview = snapDrawPreviewPoint(pos);
    if (state.drawPreview) {
      var last = lastDrawVertex();
      var aligned = collectAlignmentGuides(-1, state.drawPreview);
      state.alignGuides = aligned.guides || [];
      if (last && shouldAngleSnap()) {
        var snapped = angleSnapPoint(state.drawPreview, last);
        if (snapped && (snapped.x !== state.drawPreview.x || snapped.y !== state.drawPreview.y)) {
          state.drawPreview = snapped;
        }
      }
    }
    redrawDrawPreview();
  }

  function redrawDrawPreview() {
    var layer = state.layers.preview;
    if (!layer) return;
    layer.destroyChildren();

    var prev = lastDrawVertex();
    var dest = state.drawPreview;
    if (!prev || !dest || dist(prev, dest) < 1) {
      redrawAlignmentGuides();
      flushKonva();
      return;
    }

    var group = activeGroup();
    var color = group ? group.color : DEFAULT_GROUP_COLOR;
    layer.add(new Konva.Line({
      points: [prev.x, prev.y, dest.x, dest.y],
      stroke: color,
      strokeWidth: uiSize(2),
      dash: [uiSize(8), uiSize(5)],
      opacity: 0.4,
      lineCap: 'round',
      listening: false
    }));
    layer.add(new Konva.Circle({
      x: dest.x,
      y: dest.y,
      radius: uiSize(6),
      fill: hexToRgba(color, 0.2),
      stroke: color,
      strokeWidth: uiSize(1.5),
      opacity: 0.65,
      listening: false
    }));

    var ppf = pixelsPerFoot();
    if (ppf > 0) {
      var kLabel = new Konva.Label({
        x: (prev.x + dest.x) / 2,
        y: (prev.y + dest.y) / 2,
        opacity: 0.88,
        listening: false
      });
      kLabel.add(new Konva.Tag({
        fill: canvasTagFill(false),
        stroke: color,
        strokeWidth: uiSize(1),
        cornerRadius: uiSize(4),
        pointerDirection: 'none'
      }));
      kLabel.add(new Konva.Text({
        text: fmtLengthFromFeet(dist(prev, dest) / ppf),
        fontSize: uiSize(11),
        fontFamily: 'Open Sans, sans-serif',
        fill: color,
        padding: uiSize(4)
      }));
      kLabel.offsetX(kLabel.width() / 2);
      kLabel.offsetY(kLabel.height() / 2);
      layer.add(kLabel);
    }

    redrawAlignmentGuides();
    flushKonva();
  }

  function redrawPolygonLine() {
    var layer = state.layers.draw;
    if (!layer) return;
    layer.destroyChildren();

    var hasAny = false;
    allPolygons().forEach(function (entry) {
      var group = entry.group;
      var poly = entry.polygon;
      var verts = poly.vertices;
      if (!verts.length) return;
      hasAny = true;
      var isActive = group.id === state.activeGroupId && poly.id === state.activePolygonId;
      var opacity = isActive ? 1 : INACTIVE_POLYGON_OPACITY;
      var linePoints = [];
      verts.forEach(function (v) { linePoints.push(v.x, v.y); });
      if (poly.closed && verts.length >= 3) {
        linePoints.push(verts[0].x, verts[0].y);
        var fill = new Konva.Line({
          points: linePoints,
          closed: true,
          fill: canvasPolygonFill(group.color, opacity),
          opacity: opacity,
          listening: !state.draggingShape && !isDrawingOpenShape() && !state.rulerMode && !state.bgMoveMode
        });
        fill.on('mousedown touchstart', function (e) {
          if (e.evt && e.evt.button !== undefined && e.evt.button !== 0) return;
          onShapePointerDown();
        });
        fill.on('mouseenter', function () {
          if (canDragShapes() && state.stage && !state.shapePointerDown && !state.draggingShape) {
            state.stage.container().style.cursor = 'move';
          }
        });
        fill.on('mouseleave', function () {
          if (state.stage && !state.draggingShape && !state.shapePointerDown) updateCanvasCursor();
        });
        layer.add(fill);
      }
      if (poly.closed && verts.length >= 3) {
        drawCutWallStrokes(layer, entry, group.color, opacity, isActive);
      } else if (linePoints.length >= 4 || (!poly.closed && linePoints.length >= 2)) {
        layer.add(new Konva.Line({
          points: linePoints,
          stroke: group.color,
          strokeWidth: uiSize(isActive ? 2.5 : 2),
          opacity: opacity,
          closed: false,
          listening: false
        }));
      }
    });

    if (!hasAny) {
      flushKonva();
      return;
    }

    flushKonva();
  }

  function wallOpeningRanges(wall) {
    var lengthIn = wallLengthInches(wall.a, wall.b);
    if (lengthIn < 1e-6) return [];
    return objectsOnWall(wall.wallId).map(function (obj) {
      var width = Math.max(OBJECT_MIN_WIDTH_IN, obj.width || 0);
      var offset = Math.max(0, obj.offset || 0);
      return {
        t0: Math.max(0, Math.min(1, offset / lengthIn)),
        t1: Math.max(0, Math.min(1, (offset + width) / lengthIn))
      };
    }).filter(function (r) {
      return r.t1 - r.t0 > 0.002;
    }).sort(function (a, b) {
      return a.t0 - b.t0;
    });
  }

  function remainingWallSegments(wall) {
    var ranges = wallOpeningRanges(wall);
    var segs = [];
    var cursor = 0;
    ranges.forEach(function (range) {
      if (range.t0 > cursor + 0.001) {
        segs.push({ t0: cursor, t1: range.t0 });
      }
      cursor = Math.max(cursor, range.t1);
    });
    if (cursor < 0.999) segs.push({ t0: cursor, t1: 1 });
    return segs;
  }

  function pointAlongWall(wall, t) {
    return {
      x: wall.a.x + (wall.b.x - wall.a.x) * t,
      y: wall.a.y + (wall.b.y - wall.a.y) * t
    };
  }

  function sharedWallIdSet() {
    var skip = {};
    (state.sharedPairs || []).forEach(function (pair) {
      if (!pair || !pair.a || !pair.b) return;
      var keep = pair.a.wallId < pair.b.wallId ? pair.a.wallId : pair.b.wallId;
      var drop = keep === pair.a.wallId ? pair.b.wallId : pair.a.wallId;
      skip[drop] = true;
      skip[keep + ':shared'] = true;
    });
    return skip;
  }

  function isSharedWallId(wallId) {
    var skip = sharedWallIdSet();
    return !!(skip[wallId + ':shared'] || skip[wallId]);
  }

  function drawCutWallStrokes(layer, entry, color, opacity, isActive) {
    var verts = entry.polygon.vertices;
    var n = verts.length;
    var thicknessPx = inchesToPixels(wallThicknessIn());
    var skip = sharedWallIdSet();
    for (var i = 0; i < n; i++) {
      var wall = {
        wallId: makeWallId(entry.polygon.id, i, (i + 1) % n),
        polygonId: entry.polygon.id,
        a: verts[i],
        b: verts[(i + 1) % n],
        verts: verts
      };
      if (skip[wall.wallId]) continue;
      var shared = !!skip[wall.wallId + ':shared'];
      remainingWallSegments(wall).forEach(function (seg) {
        var p0 = pointAlongWall(wall, seg.t0);
        var p1 = pointAlongWall(wall, seg.t1);
        if (thicknessPx > 1.5) {
          drawThickWallSeg(layer, { a: p0, b: p1, verts: verts }, color, opacity, isActive, shared, thicknessPx);
        } else {
          layer.add(new Konva.Line({
            points: [p0.x, p0.y, p1.x, p1.y],
            stroke: shared ? (isAdminDark() ? '#94a3b8' : '#64748b') : color,
            strokeWidth: uiSize(isActive ? 2.5 : 2),
            opacity: opacity,
            lineCap: 'butt',
            listening: false
          }));
        }
      });
    }
  }

  function drawThickWallSeg(layer, wall, color, opacity, isActive, shared, thicknessPx) {
    var dx = wall.b.x - wall.a.x;
    var dy = wall.b.y - wall.a.y;
    var len = Math.hypot(dx, dy) || 1;
    var nx = -dy / len;
    var ny = dx / len;
    var hx;
    var hy;
    if (shared) {
      hx = nx * (thicknessPx / 2);
      hy = ny * (thicknessPx / 2);
    } else {
      var inward = wallInwardSign(wall);
      hx = (-nx * inward) * thicknessPx;
      hy = (-ny * inward) * thicknessPx;
      layer.add(new Konva.Line({
        points: [
          wall.a.x, wall.a.y,
          wall.b.x, wall.b.y,
          wall.b.x + hx, wall.b.y + hy,
          wall.a.x + hx, wall.a.y + hy
        ],
        closed: true,
        fill: hexToRgba(color, (isActive ? 0.42 : 0.28) * opacity),
        stroke: color,
        strokeWidth: uiSize(1),
        opacity: opacity,
        listening: false
      }));
      return;
    }
    layer.add(new Konva.Line({
      points: [
        wall.a.x - hx, wall.a.y - hy,
        wall.b.x - hx, wall.b.y - hy,
        wall.b.x + hx, wall.b.y + hy,
        wall.a.x + hx, wall.a.y + hy
      ],
      closed: true,
      fill: isAdminDark() ? 'rgba(148,163,184,0.45)' : 'rgba(100,116,139,0.35)',
      stroke: isAdminDark() ? '#94a3b8' : '#64748b',
      strokeWidth: uiSize(1.2),
      listening: false
    }));
  }

  function destroyFreeTransformer() {
    if (state.transformer) {
      state.transformer.destroy();
      state.transformer = null;
    }
  }

  function redrawObjects() {
    var layer = state.layers.objects;
    if (!layer) return;
    destroyFreeTransformer();
    layer.destroyChildren();
    var selectedFreeNode = null;
    state.objects.forEach(function (obj) {
      var place = objectPlacement(obj);
      if (!place) return;
      var listening = !isPanOrPinchActive() && !state.rulerMode && !state.calibrating && !state.bgMoveMode;
      var group = new Konva.Group({
        id: 'ra-obj-' + obj.id,
        name: 'ra-object',
        x: place.mid.x,
        y: place.mid.y,
        rotation: place.angle * 180 / Math.PI,
        listening: listening,
        draggable: listening && isFreeObject(obj) && !state.pinching
      });
      group.setAttr('objectId', obj.id);
      group.setAttr('objectType', obj.type);
      group.setAttr('baseW', place.widthPx);
      group.setAttr('baseD', place.depthPx || place.widthPx);
      if (isFreeObject(obj)) {
        drawFreeObjectSymbol(group, obj, place);
      } else {
        drawObjectSymbol(group, obj, place);
      }
      group.on('click tap', function (e) {
        e.cancelBubble = true;
        if (state.rulerMode || state.calibrating) return;
        selectObject(obj.id);
      });
      group.on('mousedown touchstart', function (e) {
        if (e.evt && e.evt.button !== undefined && e.evt.button !== 0) return;
        e.cancelBubble = true;
        if (isPanOrPinchActive() || state.rulerMode || state.calibrating) return;
        if (isFreeObject(obj)) return;
        state.objectPointerDown = {
          id: obj.id,
          start: getPointerPos()
        };
      });
      if (isFreeObject(obj)) {
        group.on('dragstart', function (e) {
          e.cancelBubble = true;
          if (!state.draggingObject) pushUndoSnapshot();
          state.draggingObject = true;
          if (state.selectedObjectId !== obj.id) {
            selectObject(obj.id, true);
          }
        });
        group.on('dragmove', function (e) {
          e.cancelBubble = true;
          obj.x = group.x();
          obj.y = group.y();
        });
        group.on('dragend', function (e) {
          e.cancelBubble = true;
          state.draggingObject = false;
          obj.x = group.x();
          obj.y = group.y();
          syncObjectPropertiesPanel();
          redrawAll();
        });
      }
      group.on('mouseenter', function () {
        if (state.stage && !state.draggingObject) state.stage.container().style.cursor = isFreeObject(obj) ? 'move' : 'pointer';
      });
      group.on('mouseleave', function () {
        if (state.stage && !state.draggingObject) updateCanvasCursor();
      });
      layer.add(group);
      if (state.selectedObjectId === obj.id && isFreeObject(obj)) {
        selectedFreeNode = group;
      }
    });
    if (selectedFreeNode) attachFreeTransformer(selectedFreeNode);
    flushKonva();
  }

  function attachFreeTransformer(node) {
    destroyFreeTransformer();
    if (typeof Konva.Transformer !== 'function') return;
    var coarse = isCoarsePointer();
    var tr = new Konva.Transformer({
      nodes: [node],
      rotateEnabled: true,
      enabledAnchors: ['top-left', 'top-right', 'bottom-left', 'bottom-right'],
      boundBoxFunc: function (oldBox, newBox) {
        if (Math.abs(newBox.width) < uiSize(16) || Math.abs(newBox.height) < uiSize(16)) return oldBox;
        return newBox;
      },
      anchorSize: uiSize(coarse ? 18 : 12),
      rotateAnchorOffset: uiSize(coarse ? 28 : 20),
      borderStroke: '#f57f17',
      borderStrokeWidth: uiSize(1),
      anchorStroke: '#f57f17',
      anchorStrokeWidth: uiSize(1),
      anchorFill: '#f9a825',
      rotateAnchorStroke: '#f57f17',
      rotateAnchorFill: '#f9a825'
    });
    tr.on('transformstart', function () {
      if (!state.draggingObject) pushUndoSnapshot();
    });
    tr.on('transformend', function () {
      var obj = selectedObject();
      if (!obj || !isFreeObject(obj)) return;
      var sx = Math.abs(node.scaleX()) || 1;
      var sy = Math.abs(node.scaleY()) || 1;
      var baseW = node.getAttr('baseW') || inchesToPixels(obj.width || 36);
      var baseD = node.getAttr('baseD') || inchesToPixels(obj.depth || 24);
      obj.width = Math.max(OBJECT_MIN_WIDTH_IN, Math.min(OBJECT_MAX_WIDTH_IN, Math.round(pixelsToInches(baseW * sx) * 10) / 10));
      obj.depth = Math.max(OBJECT_MIN_WIDTH_IN, Math.min(OBJECT_MAX_WIDTH_IN, Math.round(pixelsToInches(baseD * sy) * 10) / 10));
      obj.rotation = Math.round(node.rotation() * 10) / 10;
      obj.x = node.x();
      obj.y = node.y();
      node.scaleX(1);
      node.scaleY(1);
      syncObjectPropertiesPanel();
      redrawAll();
    });
    state.layers.objects.add(tr);
    state.transformer = tr;
  }

  function freeObjectFill(obj, selected) {
    if (selected) return 'rgba(249,168,37,0.18)';
    if (obj.type === 'stairs') return isAdminDark() ? 'rgba(56,189,248,0.14)' : 'rgba(2,71,104,0.10)';
    if (obj.type === 'cabinet') return isAdminDark() ? 'rgba(251,191,36,0.14)' : 'rgba(146,64,14,0.10)';
    if (obj.type === 'fixture') return isAdminDark() ? 'rgba(45,212,191,0.14)' : 'rgba(13,148,136,0.10)';
    return isAdminDark() ? 'rgba(196,181,253,0.14)' : 'rgba(91,33,182,0.08)';
  }

  function freeObjectStroke(obj, selected) {
    if (selected) return '#f57f17';
    if (obj.type === 'stairs') return canvasBrandStroke();
    if (obj.type === 'cabinet') return isAdminDark() ? '#fbbf24' : '#92400e';
    if (obj.type === 'fixture') return isAdminDark() ? '#5eead4' : '#0f766e';
    return isAdminDark() ? '#c4b5fd' : '#5b21b6';
  }

  function drawFreeObjectSymbol(group, obj, place) {
    var w = place.widthPx;
    var d = place.depthPx || w;
    var selected = state.selectedObjectId === obj.id;
    var stroke = freeObjectStroke(obj, selected);
    var fill = freeObjectFill(obj, selected);
    var sw = uiSize(1.6);
    group.add(new Konva.Rect({
      x: -w / 2,
      y: -d / 2,
      width: w,
      height: d,
      fill: fill,
      stroke: stroke,
      strokeWidth: sw,
      cornerRadius: uiSize(obj.type === 'furniture' ? 6 : 2),
      listening: true
    }));
    if (obj.type === 'stairs') {
      drawStairsGlyph(group, obj, w, d, stroke, sw);
    } else if (obj.type === 'cabinet' && obj.subtype === 'corner') {
      group.add(new Konva.Line({
        points: [-w / 2, 0, 0, 0, 0, d / 2],
        stroke: stroke,
        strokeWidth: sw,
        listening: false
      }));
    } else if (obj.type === 'cabinet' && obj.subtype === 'island') {
      group.add(new Konva.Rect({
        x: -w / 2 + uiSize(4),
        y: -d / 2 + uiSize(4),
        width: Math.max(4, w - uiSize(8)),
        height: Math.max(4, d - uiSize(8)),
        stroke: stroke,
        strokeWidth: uiSize(1),
        listening: false
      }));
    } else if (obj.type === 'fixture' && obj.subtype === 'toilet') {
      group.add(new Konva.Ellipse({
        x: 0,
        y: d * 0.12,
        radiusX: w * 0.32,
        radiusY: d * 0.28,
        stroke: stroke,
        strokeWidth: uiSize(1.2),
        listening: false
      }));
    } else if (obj.type === 'fixture' && obj.subtype === 'shower') {
      group.add(new Konva.Line({
        points: [-w / 2, -d / 2, w / 2, d / 2, w / 2, -d / 2, -w / 2, d / 2],
        stroke: stroke,
        strokeWidth: uiSize(1),
        listening: false
      }));
    } else if (obj.type === 'fixture' && (obj.subtype === 'tub' || obj.subtype === 'vanity')) {
      group.add(new Konva.Ellipse({
        x: 0,
        y: 0,
        radiusX: w * 0.28,
        radiusY: d * 0.22,
        stroke: stroke,
        strokeWidth: uiSize(1.2),
        listening: false
      }));
    } else if (obj.type === 'furniture' && obj.subtype === 'sofa') {
      group.add(new Konva.Rect({
        x: -w / 2,
        y: -d / 2,
        width: w,
        height: Math.max(uiSize(6), d * 0.28),
        fill: selected ? 'rgba(249,168,37,0.22)' : (isAdminDark() ? 'rgba(196,181,253,0.2)' : 'rgba(91,33,182,0.12)'),
        listening: false
      }));
    } else if (obj.type === 'furniture' && obj.subtype === 'bed') {
      group.add(new Konva.Rect({
        x: -w / 2 + uiSize(3),
        y: -d / 2 + uiSize(3),
        width: w - uiSize(6),
        height: Math.max(uiSize(8), d * 0.22),
        stroke: stroke,
        strokeWidth: uiSize(1),
        listening: false
      }));
    }
    var label = freeObjectShortLabel(obj);
    if (label) {
      var caption = new Konva.Text({
        x: 0,
        y: 0,
        text: label,
        fontSize: uiSize(10),
        fontFamily: 'system-ui, sans-serif',
        fill: isAdminDark() ? '#f8fafc' : '#1e293b',
        align: 'center',
        listening: false
      });
      var tw = caption.width();
      var th = caption.height();
      var pad = uiSize(6);
      var cosA = Math.cos(place.angle);
      var sinA = Math.sin(place.angle);
      var screenW = Math.abs(w * cosA) + Math.abs(d * sinA);
      var screenH = Math.abs(w * sinA) + Math.abs(d * cosA);
      if (tw + pad <= screenW && th + pad <= screenH) {
        caption.offsetX(tw / 2);
        caption.offsetY(th / 2);
        caption.rotation(-place.angle * 180 / Math.PI);
        group.add(caption);
      }
    }
    if (selected && isCoarsePointer()) {
      var handleR = uiSize(10);
      [[-w / 2, -d / 2], [w / 2, -d / 2], [w / 2, d / 2], [-w / 2, d / 2]].forEach(function (pt) {
        group.add(new Konva.Circle({
          x: pt[0],
          y: pt[1],
          radius: handleR,
          fill: '#f9a825',
          stroke: '#f57f17',
          strokeWidth: uiSize(2),
          listening: false
        }));
      });
    }
  }

  function drawStairsGlyph(group, obj, w, d, stroke, sw) {
    var subtype = obj.subtype || 'straight';
    if (subtype === 'l') {
      group.add(new Konva.Line({
        points: [-w / 2, d / 2, -w / 2, -d / 6, w / 2, -d / 6],
        stroke: stroke,
        strokeWidth: sw,
        listening: false
      }));
      var i;
      for (i = 1; i <= 4; i++) {
        var ty = d / 2 - (d * 0.66) * (i / 5);
        group.add(new Konva.Line({
          points: [-w / 2, ty, -w / 6, ty],
          stroke: stroke,
          strokeWidth: uiSize(1),
          listening: false
        }));
      }
      return;
    }
    if (subtype === 'u') {
      group.add(new Konva.Line({
        points: [-w / 2, d / 2, -w / 2, -d / 2, w / 2, -d / 2, w / 2, d / 2],
        stroke: stroke,
        strokeWidth: sw,
        listening: false
      }));
      return;
    }
    var steps = Math.max(3, Math.min(10, Math.round(d / Math.max(inchesToPixels(10), 8))));
    var s;
    for (s = 1; s < steps; s++) {
      var y = -d / 2 + (d * s) / steps;
      group.add(new Konva.Line({
        points: [-w / 2, y, w / 2, y],
        stroke: stroke,
        strokeWidth: uiSize(1),
        listening: false
      }));
    }
  }

  function freeObjectShortLabel(obj) {
    if (obj.type === 'stairs') {
      if (obj.subtype === 'l') return 'L stair';
      if (obj.subtype === 'u') return 'U stair';
      return 'Stairs';
    }
    if (obj.type === 'cabinet') {
      if (obj.subtype === 'upper') return 'Upper';
      if (obj.subtype === 'corner') return 'Corner';
      if (obj.subtype === 'island') return 'Island';
      return 'Base';
    }
    if (obj.type === 'fixture') {
      if (obj.subtype === 'tub') return 'Tub';
      if (obj.subtype === 'shower') return 'Shower';
      if (obj.subtype === 'vanity') return 'Vanity';
      return 'Toilet';
    }
    if (obj.subtype === 'bed') return 'Bed';
    if (obj.subtype === 'dining_table') return 'Table';
    return 'Sofa';
  }

  function drawObjectSymbol(group, obj, place) {
    var w = place.widthPx;
    var selected = state.selectedObjectId === obj.id;
    var stroke = selected ? '#f57f17' : (place.wall.color || canvasBrandStroke());
    var inward = place.inward;
    var leaf = Math.max(uiSize(18), w);
    var swingIn = (obj.swing || 'left_in').indexOf('out') < 0;
    var side = (swingIn ? 1 : -1) * inward;
    var hingeRight = (obj.hinge || 'left') === 'right' || (obj.swing || '').indexOf('right') === 0;
    var hingeX = hingeRight ? w / 2 : -w / 2;

    group.add(new Konva.Line({
      points: [-w / 2, 0, w / 2, 0],
      stroke: 'rgba(0,0,0,0.001)',
      strokeWidth: uiSize(objectHitStroke()),
      listening: true
    }));
    var handleR = uiSize(isCoarsePointer() ? TOUCH_HIT_RADIUS : 8);
    [-w / 2, w / 2].forEach(function (hx) {
      group.add(new Konva.Circle({
        x: hx,
        y: 0,
        radius: handleR,
        fill: 'rgba(0,0,0,0.001)',
        listening: true
      }));
      if (selected && isCoarsePointer()) {
        group.add(new Konva.Circle({
          x: hx,
          y: 0,
          radius: uiSize(10),
          fill: '#f9a825',
          stroke: '#f57f17',
          strokeWidth: uiSize(2),
          listening: false
        }));
      }
    });

    if (obj.type === 'window') {
      group.add(new Konva.Line({
        points: [-w / 2, -uiSize(4), w / 2, -uiSize(4), w / 2, uiSize(4), -w / 2, uiSize(4)],
        closed: true,
        stroke: stroke,
        strokeWidth: uiSize(1.6),
        fill: selected ? 'rgba(249,168,37,0.18)' : 'rgba(255,255,255,0.55)',
        listening: false
      }));
      group.add(new Konva.Line({
        points: [-w / 2, 0, w / 2, 0],
        stroke: stroke,
        strokeWidth: uiSize(1.2),
        listening: false
      }));
      return;
    }

    if (obj.type === 'opening' || obj.subtype === 'archway') {
      group.add(new Konva.Line({
        points: [-w / 2, -uiSize(6), -w / 2, uiSize(6)],
        stroke: stroke,
        strokeWidth: uiSize(2),
        listening: false
      }));
      group.add(new Konva.Line({
        points: [w / 2, -uiSize(6), w / 2, uiSize(6)],
        stroke: stroke,
        strokeWidth: uiSize(2),
        listening: false
      }));
      return;
    }

    if (obj.subtype === 'sliding' || obj.subtype === 'pocket') {
      group.add(new Konva.Rect({
        x: -w / 2,
        y: -uiSize(5) * inward,
        width: w * 0.55,
        height: uiSize(10),
        stroke: stroke,
        strokeWidth: uiSize(1.5),
        fill: selected ? 'rgba(249,168,37,0.2)' : hexToRgba(stroke, 0.12),
        listening: false
      }));
      group.add(new Konva.Line({
        points: [-w / 2, 0, w / 2, 0],
        stroke: stroke,
        strokeWidth: uiSize(1.4),
        dash: [uiSize(6), uiSize(4)],
        listening: false
      }));
      return;
    }

    if (obj.subtype === 'bifold') {
      var fold = w / 4;
      group.add(new Konva.Line({
        points: [-w / 2, 0, -fold, uiSize(10) * inward, 0, 0, fold, uiSize(10) * inward, w / 2, 0],
        stroke: stroke,
        strokeWidth: uiSize(1.6),
        listening: false
      }));
      return;
    }

    if (obj.subtype === 'double') {
      group.add(new Konva.Arc({
        x: -w / 2,
        y: 0,
        innerRadius: 0,
        outerRadius: w / 2,
        angle: 90,
        rotation: side > 0 ? 0 : -90,
        stroke: stroke,
        strokeWidth: uiSize(1.4),
        listening: false
      }));
      group.add(new Konva.Arc({
        x: w / 2,
        y: 0,
        innerRadius: 0,
        outerRadius: w / 2,
        angle: 90,
        rotation: side > 0 ? 90 : 180,
        stroke: stroke,
        strokeWidth: uiSize(1.4),
        listening: false
      }));
      group.add(new Konva.Line({
        points: [-w / 2, 0, -w / 2, leaf / 2 * side],
        stroke: stroke,
        strokeWidth: uiSize(2),
        listening: false
      }));
      group.add(new Konva.Line({
        points: [w / 2, 0, w / 2, leaf / 2 * side],
        stroke: stroke,
        strokeWidth: uiSize(2),
        listening: false
      }));
      return;
    }

    var arcRotation = 0;
    if (hingeRight) {
      arcRotation = side > 0 ? 90 : 180;
    } else {
      arcRotation = side > 0 ? 0 : -90;
    }
    group.add(new Konva.Arc({
      x: hingeX,
      y: 0,
      innerRadius: 0,
      outerRadius: w,
      angle: 90,
      rotation: arcRotation,
      stroke: stroke,
      strokeWidth: uiSize(1.4),
      listening: false
    }));
    group.add(new Konva.Line({
      points: [hingeX, 0, hingeX, w * side],
      stroke: stroke,
      strokeWidth: uiSize(2),
      listening: false
    }));
  }

  function redrawVertices() {
    var layer = state.layers.vertices;
    if (!layer) return;
    layer.destroyChildren();

    if (state.calibrating) {
      flushKonva();
      return;
    }

    var verts = activeVertices();
    verts.forEach(function (v, idx) {
      var isFirst = idx === 0;
      var isSelected = isVertexSelected(idx);
      var showCloseTarget = isFirst && !isActiveClosed() && verts.length >= 3;
      (function (vertexIndex) {
        var groupColor = activeGroup() ? activeGroup().color : DEFAULT_GROUP_COLOR;
        var visualR = uiSize(isSelected ? (isCoarsePointer() ? 14 : 10) : (showCloseTarget ? CLOSE_HIT / 2 : 8));
        var hitR = uiSize(touchHitRadius());
        var circle = new Konva.Circle({
          x: v.x,
          y: v.y,
          radius: visualR,
          fill: isSelected ? '#f9a825' : (isFirst ? '#c62828' : groupColor),
          stroke: isSelected ? '#f57f17' : '#fff',
          strokeWidth: uiSize(isSelected ? 3 : 2),
          hitStrokeWidth: Math.max(0, (hitR - visualR) * 2),
          draggable: !geometryLocked() && !isPanOrPinchActive() && !state.rulerMode && !state.bgMoveMode,
          listening: !isPanOrPinchActive() && !state.rulerMode && !state.bgMoveMode,
          dragDistance: uiSize(3)
        });
        circle.on('mouseenter', function () {
          if (state.stage && !state.draggingVertex && !state.draggingWall && !isPanOrPinchActive() && !state.rulerMode) {
            state.stage.container().style.cursor = 'move';
          }
        });
        circle.on('mouseleave', function () {
          if (state.stage && !state.draggingVertex && !state.draggingWall) updateCanvasCursor();
        });
        circle.on('dragstart', function (e) {
          if (geometryLocked() || isPanOrPinchActive()) {
            e.target.stopDrag();
            if (geometryLocked()) warnGeometryLocked();
            return;
          }
          var shiftKey = isShiftKey(e);
          if (shiftKey && !isVertexSelected(vertexIndex)) {
            e.target.stopDrag();
            return;
          }
          e.cancelBubble = true;
          state.didVertexDrag = false;
          if (!isVertexSelected(vertexIndex)) {
            clearRulerSelection();
            clearWallSelection();
            state.selectedVertexIndices = [vertexIndex];
            show(els.vertexEdit, true);
            if (els.canvasWrap) els.canvasWrap.classList.add('is-vertex-edit');
            updateToolbar();
          }
          state.vertexDragOrigins = {};
          state.selectedVertexIndices.forEach(function (i) {
            var v = activeVertices()[i];
            state.vertexDragOrigins[i] = { x: v.x, y: v.y };
          });
          state.draggingVertex = true;
          state.vertexDragFrom = {
            x: activeVertices()[vertexIndex].x,
            y: activeVertices()[vertexIndex].y
          };
          pushUndoSnapshot();
          updateCanvasCursor();
        });
        circle.on('click tap', function (e) {
          e.cancelBubble = true;
          if (state.rulerMode) return;
          if (state.didVertexDrag) {
            state.didVertexDrag = false;
            return;
          }
          var shiftKey = isShiftKey(e);
          if (vertexIndex === 0 && !isActiveClosed() && activeVertices().length >= 3 && !state.draggingVertex && !shiftKey) {
            closeShape();
            return;
          }
          if (!state.draggingVertex) {
            if (shiftKey) toggleVertexSelection(vertexIndex);
            else selectVertices([vertexIndex]);
          }
        });
        circle.on('dragmove', function (e) {
          e.cancelBubble = true;
          state.didVertexDrag = true;
          var raw = { x: circle.x(), y: circle.y() };
          applyVertexGroupDrag(vertexIndex, raw, false);
          if (state.selectedVertexIndices.length > 1) {
            syncSelectedVertexCircles();
          } else {
            circle.position(activeVertices()[vertexIndex]);
          }
          redrawPolygonLine();
          redrawLabels();
          redrawWalls();
          redrawDrawPreview();
          updateLiveArea();
          if (state.selectedWallIndex !== null && els.wallLength) {
            var lenFt = wallLengthFeet(state.selectedWallIndex);
            if (lenFt !== null) els.wallLength.value = fmtFeetInches(lenFt);
          }
        });
        circle.on('dragend', function (e) {
          e.cancelBubble = true;
          state.draggingVertex = false;
          updateCanvasCursor();
          var raw = { x: circle.x(), y: circle.y() };
          var dragFrom = state.vertexDragFrom;
          var origins = state.vertexDragOrigins;
          var multi = origins && state.selectedVertexIndices.length > 1;
          state.vertexDragFrom = null;
          state.vertexDragOrigins = null;
          applyVertexGroupDrag(vertexIndex, raw, true);
          clearLiveGuides();
          if (!isValidShape(activeVertices(), isActiveClosed())) {
            var snap = state.undoStack.length ? state.undoStack[state.undoStack.length - 1] : null;
            if (snap) restoreUndoSnapshot(snap);
            setFeedback('That corner position would make the shape cross itself.', true);
          } else if (dragFrom && dist(dragFrom, activeVertices()[vertexIndex]) < 1e-6 && !multi) {
            discardLastUndoSnapshot();
          }
          redrawAll();
        });
        layer.add(circle);
      })(idx);
    });

    flushKonva();
  }

  function redrawLabels() {
    var labelLayer = state.layers.labels;
    if (!labelLayer) return;
    labelLayer.destroyChildren();

    var nameItems = buildAreaNameLabels();
    var occupied = nameItems.map(function (item) { return item.box; });

    if (state.dimensionsMode !== DIM_MODES.off) {
      if (state.dimensionsMode === DIM_MODES.all) {
        drawWallDimensionLabels(labelLayer, occupied);
        drawObjectDimensionLabels(labelLayer, occupied);
        drawOverallDimensionLabel(labelLayer, occupied);
      } else if (state.dimensionsMode === DIM_MODES.main) {
        drawOverallDimensionLabel(labelLayer, occupied);
      } else if (state.dimensionsMode === DIM_MODES.area) {
        if (!nameItems.length) {
          drawOverallDimensionLabel(labelLayer, occupied, true);
        }
      }
    }

    nameItems.forEach(function (item) {
      labelLayer.add(item.node);
    });

    flushKonva();
  }

  function konvaLabelBox(kLabel) {
    var w = kLabel.width();
    var h = kLabel.height();
    var rot = ((kLabel.rotation() % 360) + 360) % 360;
    var cx = kLabel.x();
    var cy = kLabel.y();
    if (Math.abs(rot - 90) < 1 || Math.abs(rot - 270) < 1) {
      return { x: cx - h / 2, y: cy - w / 2, w: h, h: w };
    }
    return { x: cx - w / 2, y: cy - h / 2, w: w, h: h };
  }

  function visibleWorldRect() {
    var view = stageViewSize();
    var s = uiScale();
    return {
      x: -state.viewport.x / s,
      y: -state.viewport.y / s,
      w: view.w / s,
      h: view.h / s
    };
  }

  function inwardWallNormal(a, b, verts) {
    var dx = b.x - a.x;
    var dy = b.y - a.y;
    var wallLen = Math.hypot(dx, dy) || 1;
    var nx = -dy / wallLen;
    var ny = dx / wallLen;
    var midX = (a.x + b.x) / 2;
    var midY = (a.y + b.y) / 2;
    var centroid = polygonCentroid(verts);
    if (centroid) {
      if (nx * (centroid.x - midX) + ny * (centroid.y - midY) < 0) {
        nx = -nx;
        ny = -ny;
      }
    } else if (!pointInPolygon({ x: midX + nx, y: midY + ny }, verts)) {
      nx = -nx;
      ny = -ny;
    }
    return { nx: nx, ny: ny, midX: midX, midY: midY, wallLen: wallLen };
  }

  function labelBoxAt(x, y, w, h) {
    return { x: x - w / 2, y: y - h / 2, w: w, h: h };
  }

  function boxesOverlap(a, b, pad) {
    if (!a || !b) return false;
    var p = pad || 0;
    return !(a.x + a.w + p < b.x || b.x + b.w + p < a.x || a.y + a.h + p < b.y || b.y + b.h + p < a.y);
  }

  function overlapsAnyBox(box, boxes, pad) {
    return (boxes || []).some(function (other) { return boxesOverlap(box, other, pad); });
  }

  function placeDimensionLabel(midX, midY, nx, ny, labelW, labelH, a, b, avoidBoxes) {
    var handleR = uiSize(12);
    var halfW = Math.max(labelW / 2, 1);
    var halfH = Math.max(labelH / 2, 1);
    var wallLen = (a && b) ? (Math.hypot(b.x - a.x, b.y - a.y) || 1) : 1;
    var inset = Math.max(uiSize(12), handleR * 0.7);
    if (wallLen < labelW + handleR * 2) {
      inset = Math.max(uiSize(22) + halfH, handleR + halfH);
    }
    var x = midX + nx * inset;
    var y = midY + ny * inset;
    [a, b].forEach(function (p) {
      if (!p) return;
      var ddx = x - p.x;
      var ddy = y - p.y;
      var d = Math.hypot(ddx, ddy);
      var need = handleR + Math.max(halfW, halfH) * 0.55;
      if (d < 0.01) {
        x += nx * need;
        y += ny * need;
      } else if (d < need) {
        x += (ddx / d) * (need - d);
        y += (ddy / d) * (need - d);
      }
    });
    var tx = -ny;
    var ty = nx;
    var step = 0;
    while (overlapsAnyBox(labelBoxAt(x, y, labelW, labelH), avoidBoxes, uiSize(3)) && step < 8) {
      if (wallLen >= labelW + handleR * 2 && inset > uiSize(8)) {
        inset = Math.max(uiSize(8), inset - uiSize(5));
        x = midX + nx * inset;
        y = midY + ny * inset;
      } else {
        var along = (step % 2 === 0 ? 1 : -1) * uiSize(8) * Math.ceil((step + 1) / 2);
        x = midX + nx * inset + tx * along;
        y = midY + ny * inset + ty * along;
      }
      step += 1;
    }
    var vis = visibleWorldRect();
    var pad = uiSize(4);
    x = Math.max(vis.x + halfW + pad, Math.min(vis.x + vis.w - halfW - pad, x));
    y = Math.max(vis.y + halfH + pad, Math.min(vis.y + vis.h - halfH - pad, y));
    return { x: x, y: y };
  }

  function drawWallDimensionLabels(labelLayer, nameBoxes) {
    var verts = activeVertices();
    var ppf = pixelsPerFoot();
    if (!ppf || verts.length === 0) return;

    var count = wallCount();
    for (var i = 0; i < count; i++) {
      var a = verts[i];
      var b = verts[wallEndIndex(i)];
      var nrm = inwardWallNormal(a, b, verts);
      var lenFt = dist(a, b) / ppf;
      var lenLabel = fmtLengthFromFeet(lenFt);
      var isSelected = state.selectedWallIndex === i;
      (function (wallIndex, wallA, wallB, midX, midY, nx, ny) {
        var kLabel = new Konva.Label({
          x: midX,
          y: midY,
          listening: !state.rulerMode && !state.spaceHeld
        });
        bindWallDrag(kLabel, wallIndex);
        bindWallSplit(kLabel, wallIndex);
        kLabel.add(new Konva.Tag({
          fill: canvasTagFill(isSelected),
          stroke: isSelected ? '#f57f17' : canvasBrandStroke(),
          strokeWidth: uiSize(isSelected ? 2 : 1),
          cornerRadius: uiSize(4),
          pointerDirection: 'none'
        }));
        kLabel.add(new Konva.Text({
          text: lenLabel,
          fontSize: uiSize(11),
          fontFamily: 'Open Sans, sans-serif',
          fill: canvasBrandText(isSelected),
          padding: uiSize(4)
        }));
        var pos = placeDimensionLabel(midX, midY, nx, ny, kLabel.width(), kLabel.height(), wallA, wallB, nameBoxes);
        kLabel.position(pos);
        kLabel.offsetX(kLabel.width() / 2);
        kLabel.offsetY(kLabel.height() / 2);
        var box = konvaLabelBox(kLabel);
        if (overlapsAnyBox(box, nameBoxes, uiSize(2))) {
          return;
        }
        labelLayer.add(kLabel);
        nameBoxes.push(box);
      })(i, a, b, nrm.midX, nrm.midY, nrm.nx, nrm.ny);
    }
  }

  function drawObjectDimensionLabels(labelLayer, nameBoxes) {
    var ppf = pixelsPerFoot();
    if (!ppf) return;
    state.objects.forEach(function (obj) {
      var place = objectPlacement(obj);
      if (!place || isFreeObject(obj)) return;
      var nx = Math.cos(place.angle + Math.PI / 2) * place.inward;
      var ny = Math.sin(place.angle + Math.PI / 2) * place.inward;
      var kLabel = new Konva.Label({
        x: place.mid.x,
        y: place.mid.y,
        listening: false
      });
      kLabel.add(new Konva.Tag({
        fill: canvasTagFill(state.selectedObjectId === obj.id),
        stroke: state.selectedObjectId === obj.id ? '#f57f17' : canvasBrandStroke(),
        strokeWidth: uiSize(1),
        cornerRadius: uiSize(3),
        pointerDirection: 'none'
      }));
      kLabel.add(new Konva.Text({
        text: fmtLengthFromFeet(obj.width / INCHES_PER_FOOT),
        fontSize: uiSize(10),
        fontFamily: 'Open Sans, sans-serif',
        fill: canvasBrandText(state.selectedObjectId === obj.id),
        padding: uiSize(3)
      }));
      var pos = placeDimensionLabel(
        place.mid.x,
        place.mid.y,
        nx,
        ny,
        kLabel.width(),
        kLabel.height(),
        null,
        null,
        nameBoxes
      );
      kLabel.position(pos);
      kLabel.offsetX(kLabel.width() / 2);
      kLabel.offsetY(kLabel.height() / 2);
      var box = konvaLabelBox(kLabel);
      if (overlapsAnyBox(box, nameBoxes, uiSize(2))) {
        return;
      }
      labelLayer.add(kLabel);
      nameBoxes.push(box);
    });
  }

  function drawOverallDimensionLabel(labelLayer, nameBoxes, areaOnly) {
    var verts = activeVertices();
    var ppf = pixelsPerFoot();
    if (!ppf || !isActiveClosed() || verts.length < 3) return;
    var box = polygonAabbSize(verts);
    var area = areaSqFt(verts);
    var text;
    if (areaOnly) {
      text = fmtAreaFromSqFt(area);
    } else {
      text = RA.overallDimensionText(verts, {
        width: box.width / ppf,
        height: box.height / ppf,
        formatLength: fmtLengthFromFeet,
        areaLabel: area != null ? fmtAreaFromSqFt(area) : null
      });
    }
    var c = polygonCentroid(verts) || { x: box.width / 2, y: box.height / 2 };
    var kLabel = new Konva.Label({
      x: c.x,
      y: c.y,
      listening: false
    });
    kLabel.add(new Konva.Tag({
      fill: canvasTagFill(false),
      stroke: canvasBrandStroke(),
      strokeWidth: uiSize(1),
      cornerRadius: uiSize(4),
      pointerDirection: 'none'
    }));
    kLabel.add(new Konva.Text({
      text: text,
      fontSize: uiSize(12),
      fontFamily: 'Open Sans, sans-serif',
      fontStyle: '700',
      fill: canvasAreaLabelText(),
      padding: uiSize(5)
    }));
    kLabel.offsetX(kLabel.width() / 2);
    kLabel.offsetY(kLabel.height() / 2);
    var boxLabel = konvaLabelBox(kLabel);
    var offsets = [0, uiSize(22), -uiSize(22), uiSize(40), -uiSize(40)];
    var placed = false;
    var i;
    for (i = 0; i < offsets.length; i++) {
      kLabel.y(c.y + offsets[i]);
      kLabel.offsetY(kLabel.height() / 2);
      boxLabel = konvaLabelBox(kLabel);
      if (!overlapsAnyBox(boxLabel, nameBoxes, uiSize(2))) {
        placed = true;
        break;
      }
    }
    if (!placed && (areaOnly || state.dimensionsMode === DIM_MODES.main || state.dimensionsMode === DIM_MODES.area)) {
      var aabb = polygonAabbSize(verts);
      kLabel.y(isFinite(aabb.minY) ? aabb.minY - uiSize(16) : c.y - uiSize(28));
      kLabel.offsetY(kLabel.height() / 2);
      boxLabel = konvaLabelBox(kLabel);
      placed = true;
    }
    if (!placed) return;
    labelLayer.add(kLabel);
    nameBoxes.push(boxLabel);
  }

  function polygonCentroid(verts) {
    var n = verts.length;
    if (!n) return null;
    if (n < 3) {
      var sx = 0;
      var sy = 0;
      verts.forEach(function (v) {
        sx += v.x;
        sy += v.y;
      });
      return { x: sx / n, y: sy / n };
    }
    var area2 = 0;
    var cx = 0;
    var cy = 0;
    for (var i = 0; i < n; i++) {
      var j = (i + 1) % n;
      var cross = verts[i].x * verts[j].y - verts[j].x * verts[i].y;
      area2 += cross;
      cx += (verts[i].x + verts[j].x) * cross;
      cy += (verts[i].y + verts[j].y) * cross;
    }
    if (Math.abs(area2) < 1e-9) {
      var ax = 0;
      var ay = 0;
      verts.forEach(function (v) {
        ax += v.x;
        ay += v.y;
      });
      return { x: ax / n, y: ay / n };
    }
    return { x: cx / (3 * area2), y: cy / (3 * area2) };
  }

  function polygonRayDistance(origin, dx, dy, verts) {
    var best = Infinity;
    var n = verts.length;
    for (var i = 0; i < n; i++) {
      var a = verts[i];
      var b = verts[(i + 1) % n];
      var ex = b.x - a.x;
      var ey = b.y - a.y;
      var denom = dx * ey - dy * ex;
      if (Math.abs(denom) < 1e-9) continue;
      var t = ((a.x - origin.x) * ey - (a.y - origin.y) * ex) / denom;
      var u = ((a.x - origin.x) * dy - (a.y - origin.y) * dx) / denom;
      if (t > 1e-6 && u >= -1e-6 && u <= 1 + 1e-6 && t < best) {
        best = t;
      }
    }
    return best;
  }

  function polygonAabbSize(verts) {
    var minX = Infinity;
    var minY = Infinity;
    var maxX = -Infinity;
    var maxY = -Infinity;
    verts.forEach(function (v) {
      if (v.x < minX) minX = v.x;
      if (v.y < minY) minY = v.y;
      if (v.x > maxX) maxX = v.x;
      if (v.y > maxY) maxY = v.y;
    });
    return {
      width: Math.max(0, maxX - minX),
      height: Math.max(0, maxY - minY),
      minX: minX,
      minY: minY,
      maxX: maxX,
      maxY: maxY
    };
  }

  function polygonAxisSpan(origin, verts, axisX, axisY) {
    var fwd = polygonRayDistance(origin, axisX, axisY, verts);
    var back = polygonRayDistance(origin, -axisX, -axisY, verts);
    if (isFinite(fwd) && isFinite(back)) return fwd + back;
    var box = polygonAabbSize(verts);
    return axisX ? box.width : box.height;
  }

  function labelOverflow(labelW, labelH, boxW, boxH) {
    return Math.max(0, labelW - boxW) + Math.max(0, labelH - boxH);
  }

  function buildAreaNameLabels() {
    var items = [];
    allPolygons().forEach(function (entry) {
      var poly = entry.polygon;
      if (!poly.closed || poly.vertices.length < 3) return;
      var name = polygonName(poly);
      var sqft = areaSqFt(poly.vertices);
      var roomFallback = (els.roomName && els.roomName.value || '').trim();
      var lines = [];
      if (name) lines.push(name);
      else if (roomFallback) lines.push(roomFallback);
      if (sqft != null) lines.push(fmtAreaFromSqFt(sqft));
      if (!lines.length) return;
      var center = polygonCentroid(poly.vertices);
      if (!center) return;
      var isActive = entry.group.id === state.activeGroupId && poly.id === state.activePolygonId;
      var kLabel = new Konva.Label({
        x: center.x,
        y: center.y,
        opacity: isActive ? 1 : INACTIVE_POLYGON_OPACITY,
        listening: false
      });
      kLabel.add(new Konva.Tag({
        fill: canvasTagFill(false),
        stroke: entry.group.color || DEFAULT_GROUP_COLOR,
        strokeWidth: uiSize(isActive ? 1.5 : 1),
        cornerRadius: uiSize(4),
        pointerDirection: 'none'
      }));
      var slack = uiSize(10);
      var hSpan = Math.max(0, polygonAxisSpan(center, poly.vertices, 1, 0) - slack);
      var vSpan = Math.max(0, polygonAxisSpan(center, poly.vertices, 0, 1) - slack);
      var minSpan = Math.min(hSpan, vSpan);
      var font = uiSize(13);
      var pad = uiSize(6);
      if (minSpan < uiSize(150)) {
        font = uiSize(11);
        pad = uiSize(4);
      }
      if (minSpan < uiSize(110)) {
        font = uiSize(10);
        pad = uiSize(3);
      }
      var textNode = new Konva.Text({
        text: lines.join('\n'),
        fontSize: font,
        fontFamily: 'Open Sans, sans-serif',
        fontStyle: 'bold',
        fill: isAdminDark() ? canvasAreaLabelText() : (entry.group.color || DEFAULT_GROUP_COLOR),
        padding: pad,
        align: 'center'
      });
      var maxNameW = Math.max(uiSize(72), Math.min(uiSize(160), hSpan * 0.55));
      if (textNode.width() > maxNameW) {
        textNode.width(maxNameW);
        textNode.wrap('word');
      }
      kLabel.add(textNode);
      kLabel.offsetX(kLabel.width() / 2);
      kLabel.offsetY(kLabel.height() / 2);

      var lw = kLabel.width();
      var lh = kLabel.height();
      if (labelOverflow(lh, lw, hSpan, vSpan) + 1 < labelOverflow(lw, lh, hSpan, vSpan)) {
        kLabel.rotation(-90);
      }
      items.push({
        node: kLabel,
        box: konvaLabelBox(kLabel)
      });
    });
    return items;
  }

  function redrawWalls() {
    var wallLayer = state.layers.walls;
    if (!wallLayer) return;
    wallLayer.destroyChildren();

    var count = wallCount();
    if (count === 0) {
      flushKonva();
      return;
    }

    for (var i = 0; i < count; i++) {
      var a = activeVertices()[i];
      var b = activeVertices()[wallEndIndex(i)];
      var isSelected = state.selectedWallIndex === i;

      if (isSelected) {
        var activePoly = activePolygon();
        var wall = {
          wallId: activePoly ? makeWallId(activePoly.id, i, wallEndIndex(i)) : '',
          a: a,
          b: b
        };
        remainingWallSegments(wall).forEach(function (seg) {
          var p0 = pointAlongWall(wall, seg.t0);
          var p1 = pointAlongWall(wall, seg.t1);
          wallLayer.add(new Konva.Line({
            points: [p0.x, p0.y, p1.x, p1.y],
            stroke: '#f9a825',
            strokeWidth: uiSize(isCoarsePointer() ? 8 : 5),
            lineCap: 'round',
            listening: false
          }));
        });
      }

      (function (wallIndex) {
        var hitLine = new Konva.Line({
          points: [a.x, a.y, b.x, b.y],
          stroke: 'rgba(0,0,0,0.001)',
          strokeWidth: uiSize(wallHitStroke()),
          lineCap: 'round',
          listening: !isPanOrPinchActive() && !state.rulerMode && isActiveClosed()
        });
        bindWallDrag(hitLine, wallIndex);
        bindWallSplit(hitLine, wallIndex);
        wallLayer.add(hitLine);
      })(i);
    }

    flushKonva();
  }

  function redrawShape() {
    redrawPolygonLine();
    redrawVertices();
    redrawLabels();
  }

  function redrawCalib() {
    var layer = state.layers.calib;
    if (!layer) return;
    layer.destroyChildren();
    if (state.calibPoints.length === 0) {
      flushKonva();
      return;
    }
    state.calibPoints.forEach(function (p, idx) {
      layer.add(new Konva.Circle({
        x: p.x,
        y: p.y,
        radius: uiSize(7),
        fill: idx === 0 ? '#f9a825' : '#2e7d32',
        stroke: '#fff',
        strokeWidth: uiSize(2),
        listening: false
      }));
    });
    if (state.calibPoints.length === 2) {
      layer.add(new Konva.Line({
        points: [state.calibPoints[0].x, state.calibPoints[0].y, state.calibPoints[1].x, state.calibPoints[1].y],
        stroke: '#f9a825',
        strokeWidth: uiSize(2),
        dash: [uiSize(8), uiSize(4)],
        listening: false
      }));
    }
    flushKonva();
  }

  function drawRulerSegment(layer, p1, p2, label, isSelected, rulerId, listening) {
    var stroke = isSelected ? '#f57f17' : '#6a1b9a';
    var line = new Konva.Line({
      points: [p1.x, p1.y, p2.x, p2.y],
      stroke: stroke,
      strokeWidth: uiSize(isSelected ? 3 : 2),
      dash: [uiSize(6), uiSize(4)],
      listening: !!listening,
      hitStrokeWidth: uiSize(isCoarsePointer() ? TOUCH_HIT_STROKE : Math.max(RULER_HIT, 12))
    });

    if (listening && rulerId !== null) {
      line.on('click tap', function (e) {
        e.cancelBubble = true;
        if (state.rulerMode) return;
        selectRuler(rulerId);
      });
      line.on('mouseenter', function () {
        if (state.stage && !state.rulerMode && !state.spaceHeld) {
          state.stage.container().style.cursor = 'pointer';
        }
      });
      line.on('mouseleave', function () {
        if (state.stage) updateCanvasCursor();
      });
    }
    layer.add(line);

    [p1, p2].forEach(function (p, idx) {
      layer.add(new Konva.Circle({
        x: p.x,
        y: p.y,
        radius: uiSize(5),
        fill: idx === 0 ? '#8e24aa' : '#6a1b9a',
        stroke: '#fff',
        strokeWidth: uiSize(1.5),
        listening: false
      }));
    });

    if (!label) return;

    var midX = (p1.x + p2.x) / 2;
    var midY = (p1.y + p2.y) / 2;
    var kLabel = new Konva.Label({
      x: midX,
      y: midY,
      listening: !!listening && rulerId !== null
    });
    if (listening && rulerId !== null) {
      kLabel.on('click tap', function (e) {
        e.cancelBubble = true;
        if (state.rulerMode) return;
        selectRuler(rulerId);
      });
      kLabel.on('mouseenter', function () {
        if (state.stage && !state.rulerMode && !state.spaceHeld) {
          state.stage.container().style.cursor = 'pointer';
        }
      });
      kLabel.on('mouseleave', function () {
        if (state.stage) updateCanvasCursor();
      });
    }
    kLabel.add(new Konva.Tag({
      fill: canvasTagFill(isSelected),
      stroke: isSelected ? '#f57f17' : '#6a1b9a',
      strokeWidth: uiSize(1),
      cornerRadius: uiSize(4),
      pointerDirection: 'none',
      pointerWidth: 0,
      pointerHeight: 0,
      lineJoin: 'round'
    }));
    kLabel.add(new Konva.Text({
      text: label,
      fontFamily: 'system-ui, sans-serif',
      fontSize: uiSize(12),
      fontStyle: 'bold',
      fill: canvasRulerText(isSelected),
      padding: uiSize(5)
    }));
    layer.add(kLabel);
  }

  function addSavedRuler(layer, ruler) {
    var rulerId = ruler.id;
    var isSelected = state.selectedRulerId === rulerId;
    var listening = !state.rulerMode;
    var stroke = isSelected ? '#f57f17' : '#6a1b9a';
    var line = new Konva.Line({
      points: [ruler.p1.x, ruler.p1.y, ruler.p2.x, ruler.p2.y],
      stroke: stroke,
      strokeWidth: uiSize(isSelected ? 3 : 2),
      dash: [uiSize(6), uiSize(4)],
      listening: !!listening,
      hitStrokeWidth: uiSize(isCoarsePointer() ? TOUCH_HIT_STROKE : Math.max(RULER_HIT, 12))
    });

    if (listening) {
      line.on('click tap', function (e) {
        e.cancelBubble = true;
        if (state.rulerMode) return;
        selectRuler(rulerId);
      });
      line.on('mouseenter', function () {
        if (state.stage && !state.rulerMode && !state.spaceHeld && !state.draggingRuler) {
          state.stage.container().style.cursor = 'pointer';
        }
      });
      line.on('mouseleave', function () {
        if (state.stage && !state.draggingRuler) updateCanvasCursor();
      });
    }
    layer.add(line);

    var label = new Konva.Label({
      x: (ruler.p1.x + ruler.p2.x) / 2,
      y: (ruler.p1.y + ruler.p2.y) / 2,
      listening: !!listening
    });
    if (listening) {
      label.on('click tap', function (e) {
        e.cancelBubble = true;
        if (state.rulerMode) return;
        selectRuler(rulerId);
      });
      label.on('mouseenter', function () {
        if (state.stage && !state.rulerMode && !state.spaceHeld && !state.draggingRuler) {
          state.stage.container().style.cursor = 'pointer';
        }
      });
      label.on('mouseleave', function () {
        if (state.stage && !state.draggingRuler) updateCanvasCursor();
      });
    }
    label.add(new Konva.Tag({
      fill: canvasTagFill(isSelected),
      stroke: isSelected ? '#f57f17' : '#6a1b9a',
      strokeWidth: uiSize(1),
      cornerRadius: uiSize(4),
      pointerDirection: 'none',
      pointerWidth: 0,
      pointerHeight: 0,
      lineJoin: 'round'
    }));
    var labelText = new Konva.Text({
      text: rulerLengthLabel(ruler),
      fontFamily: 'system-ui, sans-serif',
      fontSize: uiSize(12),
      fontStyle: 'bold',
      fill: canvasRulerText(isSelected),
      padding: uiSize(5)
    });
    label.add(labelText);
    layer.add(label);

    function syncRulerGraphics() {
      line.points([ruler.p1.x, ruler.p1.y, ruler.p2.x, ruler.p2.y]);
      label.position({
        x: (ruler.p1.x + ruler.p2.x) / 2,
        y: (ruler.p1.y + ruler.p2.y) / 2
      });
      labelText.text(rulerLengthLabel(ruler));
      if (isSelected && state.selectedRulerId === rulerId && els.rulerLength) {
        els.rulerLength.textContent = rulerLengthLabel(ruler);
      }
      flushKonva();
    }

    ['p1', 'p2'].forEach(function (key, idx) {
      var canDrag = isSelected && listening && !state.spaceHeld;
      var circle = new Konva.Circle({
        x: ruler[key].x,
        y: ruler[key].y,
        radius: uiSize(canDrag ? 8 : 5),
        fill: idx === 0 ? '#8e24aa' : '#6a1b9a',
        stroke: '#fff',
        strokeWidth: uiSize(canDrag ? 2 : 1.5),
        hitStrokeWidth: uiSize(isCoarsePointer() ? Math.max(0, (TOUCH_HIT_RADIUS - (canDrag ? 8 : 5)) * 2) : 0),
        draggable: canDrag,
        listening: canDrag,
        dragDistance: uiSize(3)
      });

      if (canDrag) {
        circle.on('dragstart', function (e) {
          if (state.spaceHeld || state.rulerMode) {
            e.target.stopDrag();
            return;
          }
          e.cancelBubble = true;
          state.draggingRuler = true;
          if (state.selectedRulerId !== rulerId) selectRuler(rulerId);
          updateCanvasCursor();
        });
        circle.on('dragmove', function (e) {
          e.cancelBubble = true;
          var pos = clampToCanvas({ x: circle.x(), y: circle.y() });
          var anchor = key === 'p1' ? ruler.p2 : ruler.p1;
          if (state.shiftHeld) {
            pos = clampToCanvas(snapPoint(pos, anchor, SHIFT_SNAP_STEP));
          }
          circle.position(pos);
          ruler[key] = { x: pos.x, y: pos.y };
          syncRulerGraphics();
        });
        circle.on('dragend', function (e) {
          e.cancelBubble = true;
          state.draggingRuler = false;
          updateCanvasCursor();
        });
        circle.on('mouseenter', function () {
          if (state.stage && !state.draggingRuler && !state.spaceHeld && !state.rulerMode) {
            state.stage.container().style.cursor = 'move';
          }
        });
        circle.on('mouseleave', function () {
          if (state.stage && !state.draggingRuler) updateCanvasCursor();
        });
      }

      layer.add(circle);
    });
  }

  function redrawRulers() {
    var layer = state.layers.rulers;
    if (!layer) return;
    layer.destroyChildren();

    projectRulers().forEach(function (ruler) {
      addSavedRuler(layer, ruler);
    });

    if (state.rulerDraft.length === 1) {
      layer.add(new Konva.Circle({
        x: state.rulerDraft[0].x,
        y: state.rulerDraft[0].y,
        radius: uiSize(7),
        fill: '#8e24aa',
        stroke: '#fff',
        strokeWidth: uiSize(2),
        listening: false
      }));
      if (state.rulerPreview) {
        drawRulerSegment(
          layer,
          state.rulerDraft[0],
          state.rulerPreview,
          rulerLengthLabel({ p1: state.rulerDraft[0], p2: state.rulerPreview }),
          false,
          null,
          false
        );
      }
    } else if (state.rulerDraft.length === 2) {
      drawRulerSegment(
        layer,
        state.rulerDraft[0],
        state.rulerDraft[1],
        rulerLengthLabel({ p1: state.rulerDraft[0], p2: state.rulerDraft[1] }),
        false,
        null,
        false
      );
    }

    flushKonva();
  }

  function getPointerPos() {
    var pos = state.stage.getPointerPosition();
    if (!pos) return null;
    return stageToWorld(pos);
  }

  function onStageClick(e) {
    if (state.spaceHeld || state.panning || state.pinching || state.bgMoveMode || state.draggingBg) return;
    var pos = getPointerPos();
    if (!pos) return;
    if (state.didPan) {
      state.didPan = false;
      return;
    }
    if (state.draggingVertex || state.draggingWall || state.draggingShape || state.draggingRuler || state.didWallDrag || state.didShapeDrag) {
      if (state.didWallDrag) state.didWallDrag = false;
      if (state.didShapeDrag) state.didShapeDrag = false;
      return;
    }

    if (state.calibrating) {
      if (state.calibPoints.length >= 2) {
        state.calibPoints = [];
      }
      state.calibPoints.push({ x: pos.x, y: pos.y });
      redrawCalib();
      var ready = state.calibPoints.length === 2;
      raAll('[data-ra-known-length]').forEach(function (el) { el.disabled = !ready; });
      raAll('[data-ra-apply-scale]').forEach(function (el) { el.disabled = !ready; });
      if (ready && els.knownLength) els.knownLength.focus();
      return;
    }

    if (state.rulerMode) {
      placeRulerPoint(pos, shouldAngleSnap(e));
      return;
    }

    var clickedObject = findObjectAtPoint(pos);
    if (clickedObject && !state.placingCatalog) {
      if (state.armedCatalogKey) {
        state.armedCatalogKey = null;
        syncArmedPaletteUi();
        hideObjectGhost();
        if (els.canvasWrap) els.canvasWrap.classList.remove('is-placing-object');
      }
      selectObject(clickedObject.id);
      return;
    }

    if (state.armedCatalogKey || state.placingCatalog) {
      var placed = tryPlaceCatalogItem(state.placingCatalog || catalogItemByKey(state.armedCatalogKey), pos);
      if (placed) return;
    }

    if (clickedObject) {
      selectObject(clickedObject.id);
      return;
    }

    if (state.selectedObjectId) {
      clearObjectSelection();
    }

    var clickedRuler = findRulerAtPoint(pos);
    if (clickedRuler) {
      selectRuler(clickedRuler.id);
      return;
    }

    if (state.selectedRulerId !== null) {
      clearRulerSelection();
    }

    if (findVertexAtPoint(pos) >= 0) return;

    // While drawing an open shape, prioritize new corners over selecting other areas.
    if (!isActiveClosed()) {
      clearVertexSelection();
      clearWallSelection();

      if (activeVertices().length >= 3) {
        var closeFirst = activeVertices()[0];
        if (dist(pos, closeFirst) <= hitTolerance(CLOSE_HIT)) {
          closeShape();
          return;
        }
      }

      if (geometryLocked()) {
        warnGeometryLocked();
        return;
      }
      var drawPoint = pos;
      if (activeVertices().length > 0) {
        var drawPrev = activeVertices()[activeVertices().length - 1];
        if (shouldAngleSnap(e)) {
          drawPoint = angleSnapPoint(pos, drawPrev);
        }
      }
      pushUndoSnapshot();
      activeVertices().push(clampToCanvas(drawPoint));
      syncTypedWallsFromCanvas();
      renderTypedWallsList();
      updateTypedCloseBar();
      redrawAll();
      updateDrawPreviewFromPointer();
      return;
    }

    var polygonHit = polygonAtPoint(pos);
    if (polygonHit) {
      if (polygonHit.group.id !== state.activeGroupId || polygonHit.polygon.id !== state.activePolygonId) {
        activatePolygon(polygonHit.group.id, polygonHit.polygon.id);
        return;
      }
      if (wallCount() > 0) {
        var wallIdx = findWallAtPoint(pos);
        if (wallIdx >= 0) return;
      }
    } else if (wallCount() > 0) {
      var wallIdxInactive = findWallAtPoint(pos);
      if (wallIdxInactive >= 0) return;
    }

    clearVertexSelection();

    if (isActiveClosed()) {
      clearWallSelection();
      return;
    }

    if (activeVertices().length >= 3) {
      var first = activeVertices()[0];
      if (dist(pos, first) <= hitTolerance(CLOSE_HIT)) {
        closeShape();
        return;
      }
    }

    if (geometryLocked()) {
      warnGeometryLocked();
      return;
    }
    var point = pos;
    if (activeVertices().length > 0) {
      var prev = activeVertices()[activeVertices().length - 1];
      if (shouldAngleSnap(e)) {
        point = angleSnapPoint(pos, prev);
      }
    }
    pushUndoSnapshot();
    activeVertices().push(clampToCanvas(point));
    syncTypedWallsFromCanvas();
    renderTypedWallsList();
    updateTypedCloseBar();
    redrawAll();
  }

  function onStageDblClick(e) {
    if (e && e.evt) e.evt.preventDefault();
    resetWallClickState();
    if (state.spaceHeld || state.panning || state.calibrating || state.rulerMode) return;
    if (state.draggingWall) {
      finishWallDrag(getPointerPos());
      state.didWallDrag = false;
    }
    var pos = getPointerPos();
    if (!pos) return;

    if (findVertexAtPoint(pos) >= 0) return;

    if (wallCount() > 0) {
      var wallIdx = findWallAtPoint(pos);
      if (wallIdx >= 0) {
        clearWallSelection();
        splitWallAtPointer(wallIdx);
        return;
      }
    }

    if (!isActiveClosed() && activeVertices().length >= 3) {
      closeShape();
    }
  }

  function closeShape() {
    if (geometryLocked()) {
      warnGeometryLocked();
      return;
    }
    if (activeVertices().length < 3) return;
    if (!isSimplePolygon(activeVertices())) {
      setFeedback('Shape crosses itself — adjust corners before closing.', true);
      return;
    }
    pushUndoSnapshot();
    setActiveClosed(true);
    clearDrawPreview();
    syncTypedWallsFromCanvas();
    renderTypedWallsList();
    updateTypedCloseBar();
    redrawAll();
  }

  function clearDraftLayers() {
    ['draw', 'objects', 'walls', 'vertices', 'labels', 'calib'].forEach(function (name) {
      var layer = state.layers[name];
      if (layer) layer.destroyChildren();
    });
  }

  function resetDraftCanvas() {
    bumpEditorToken();
    resetCanvasGroups();
    ensureInitialCanvas();
    state.editingRoomId = null;
    state.selectedVertexIndices = [];
    state.vertexDragOrigins = null;
    state.draggingVertex = false;
    state.draggingWall = false;
    state.draggingShape = false;
    state.shapePointerDown = null;
    state.didShapeDrag = false;
    state.wallDragIndex = null;
    state.wallDragPointerStart = null;
    state.wallDragOrigA = null;
    state.wallDragOrigB = null;
    state.wallPointerDown = null;
    cancelPendingWallClick();
    state.didWallDrag = false;
    state.didVertexDrag = false;
    stopCalibrationMode();
    state.draftRulers = [];
    setWorkingPixelsPerFoot(null);
    clearWallSelection();
    clearVertexSelection();
    clearRulerSelection();
    clearUndoStack();
    if (els.roomName) els.roomName.value = '';
    setWallHeight(DEFAULT_WALL_HEIGHT_FT);
    resetFinishSettings();
    applyEditorPrefs(defaultEditorPrefs(), true);
    show(els.liveArea, false);
    clearDraftLayers();
    resetTypedWalls();
    renderPhotos();
    updateEditUI();
    redrawAll();
    flushKonva();
    captureSavedSnapshot();
  }

  function clearShape(opts) {
    opts = opts || {};
    if (!opts.force && geometryLocked()) {
      warnGeometryLocked();
      return;
    }
    resetDraftCanvas();
  }

  function beginNewRoom() {
    resetDraftCanvas();
    applyEditorPrefs(defaultEditorPrefs(), true);
    revokeDraftBackground();
    state.bgLayout = null;
    stopBgMoveMode();
    state.draftScale = null;
    setWorkingPixelsPerFoot(null);
    loadBackground(null);
    captureSavedSnapshot();
    if (els.roomName) {
      els.roomName.focus();
    }
    setFeedback('Draw a new room outline, upload its floor plan, enter a name, and click Save room.', false);
  }

  function startNewRoom() {
    if (!hasUnsavedChanges()) {
      beginNewRoom();
      return;
    }
    confirmAction(
      'You have unsaved changes. Save them before starting a new room, or discard them?',
      {
        title: 'Unsaved changes',
        confirmLabel: 'Save changes',
        cancelLabel: 'Discard & continue'
      }
    ).then(function (shouldSave) {
      if (!shouldSave) {
        beginNewRoom();
        return;
      }
      return saveRoom().then(function () {
        beginNewRoom();
      }).catch(function (err) {
        setFeedback((err && err.message) || 'Could not save changes.', true);
      });
    });
  }

  function snapshotGeometryKey(snap) {
    var groups = (snap && snap.groups) || [];
    return JSON.stringify({
      g: groups.map(function (group) {
        return (group.polygons || []).map(function (poly) {
          return { id: poly.id, closed: !!poly.closed, v: poly.vertices };
        });
      }),
      scale: snap ? snap.scalePixelsPerFoot : null,
      thick: snap ? snap.wallThicknessIn : null
    });
  }

  function historyAllowedWhileLocked(snap) {
    if (!snap) return false;
    return snapshotGeometryKey(snap) === snapshotGeometryKey(snapshotState());
  }

  function undoVertex() {
    if (!state.undoStack.length) return;
    if (geometryLocked() && !historyAllowedWhileLocked(state.undoStack[state.undoStack.length - 1])) {
      warnGeometryLocked();
      return;
    }
    if (state.draggingWall) finishWallDrag(getPointerPos());
    state.draggingVertex = false;
    state.vertexDragFrom = null;
    var current = snapshotState();
    var snap = state.undoStack.pop();
    state.redoStack.push(current);
    if (state.redoStack.length > UNDO_MAX) state.redoStack.shift();
    restoreUndoSnapshot(snap);
    syncTypedWallsFromCanvas();
    renderTypedWallsList();
    updateTypedCloseBar();
    redrawAll();
    updateToolbar();
    setFeedback('Undone.', false);
  }

  function redoAction() {
    if (!state.redoStack.length) return;
    if (geometryLocked() && !historyAllowedWhileLocked(state.redoStack[state.redoStack.length - 1])) {
      warnGeometryLocked();
      return;
    }
    if (state.draggingWall) finishWallDrag(getPointerPos());
    state.draggingVertex = false;
    state.vertexDragFrom = null;
    var current = snapshotState();
    var snap = state.redoStack.pop();
    state.undoStack.push(current);
    if (state.undoStack.length > UNDO_MAX) state.undoStack.shift();
    restoreUndoSnapshot(snap);
    syncTypedWallsFromCanvas();
    renderTypedWallsList();
    updateTypedCloseBar();
    redrawAll();
    updateToolbar();
    setFeedback('Redone.', false);
  }

  function updateLiveArea() {
    var activeSqft = isActiveClosed() ? areaSqFt(activeVertices()) : null;
    var grandTotal = hasAnyClosedPolygon() ? canvasTotalAreaSqFt() : null;
    if (grandTotal === null) {
      show(els.liveArea, false);
      setSaveDisabled(true);
      if (els.sheetTotal) els.sheetTotal.textContent = '—';
      updateGroupChip();
      return;
    }
    show(els.liveArea, true);
    var closedCount = 0;
    var groupsWithAreas = 0;
    state.groups.forEach(function (group) {
      var count = (group.polygons || []).filter(function (p) { return p.closed && p.vertices.length >= 3; }).length;
      if (!count) return;
      groupsWithAreas += 1;
      closedCount += count;
    });
    els.liveArea.classList.toggle('util-ra-live-area--single', closedCount <= 1 && groupsWithAreas <= 1);
    if (els.liveActiveShape) {
      els.liveActiveShape.textContent = activeSqft !== null ? formatAreaQty(activeSqft) : '—';
    }
    if (els.liveGrandTotal) {
      els.liveGrandTotal.textContent = formatAreaQty(grandTotal);
    }
    if (els.liveSqft) els.liveSqft.textContent = fmt(grandTotal, 2);
    if (els.liveSqm) els.liveSqm.textContent = fmt(grandTotal * SQFT_TO_SQM, 2);
    if (els.sheetTotal) els.sheetTotal.textContent = fmtAreaFromSqFt(grandTotal);
    var alt = root.querySelector('[data-ra-live-sqft-alt]');
    if (alt) alt.textContent = fmt(grandTotal, 2);
    var metrics = computeWallMetrics();
    if (els.livePerimeter) els.livePerimeter.textContent = formatLinearQty(metrics.perimeter_lf);
    if (els.liveBaseboard) els.liveBaseboard.textContent = formatLinearQty(metrics.baseboard_lf);
    if (els.liveWallArea) els.liveWallArea.textContent = formatAreaQty(metrics.net_wall_area_sqft != null ? metrics.net_wall_area_sqft : metrics.wall_area_sqft);
    if (els.liveCeilingArea) els.liveCeilingArea.textContent = formatAreaQty(metrics.ceiling_area_sqft);
    if (els.liveBoxes) els.liveBoxes.textContent = metrics.flooring_boxes > 0 ? String(metrics.flooring_boxes) : '—';
    if (els.liveCasing) els.liveCasing.textContent = formatLinearQty(metrics.casing_lf || 0);
    renderWallNetList(metrics.walls || []);
    updateSelectedWallNet(metrics.walls || []);
    if (els.liveCounts) {
      var counts = metrics.door_count + (metrics.door_count === 1 ? ' door' : ' doors') +
        ' · ' + metrics.window_count + (metrics.window_count === 1 ? ' window' : ' windows') +
        ' · ' + metrics.opening_count + (metrics.opening_count === 1 ? ' opening' : ' openings');
      if (metrics.stairs_count) {
        counts += ' · ' + metrics.stairs_count + (metrics.stairs_count === 1 ? ' stair' : ' stairs');
      }
      els.liveCounts.textContent = counts;
    }
    if (els.liveFootprint) {
      var showFp = metrics.wall_thickness_in > 0 && Math.abs(metrics.outside_area_sqft - metrics.inside_area_sqft) > 0.009;
      show(els.liveFootprintWrap, showFp);
      els.liveFootprint.textContent = formatAreaQty(metrics.outside_area_sqft);
    }
    if (els.liveFraming) {
      var showFr = metrics.shared_wall_lf > 0.009;
      show(els.liveFramingWrap, showFr);
      els.liveFraming.textContent = formatLinearQty(metrics.framing_lf);
    }
    if (els.liveCabinets) {
      var cab = (metrics.cabinet_base_lf || 0) + (metrics.cabinet_upper_lf || 0);
      show(els.liveCabinetsWrap, cab > 0.009);
      var cabParts = [];
      if (metrics.cabinet_base_lf > 0) cabParts.push(formatLinearQty(metrics.cabinet_base_lf) + ' base');
      if (metrics.cabinet_upper_lf > 0) cabParts.push(formatLinearQty(metrics.cabinet_upper_lf) + ' up');
      els.liveCabinets.textContent = cabParts.join(' · ') || '0';
    }
    syncExportButtons();
    if (els.liveGroupList) {
      els.liveGroupList.innerHTML = '';
      state.groups.forEach(function (group) {
        var count = (group.polygons || []).filter(function (p) { return p.closed && p.vertices.length >= 3; }).length;
        if (!count) return;
        var li = document.createElement('li');
        li.className = 'util-ra-live-area-groups__item';
        li.innerHTML =
          '<span class="util-ra-live-area-groups__swatch" style="background:' + esc(group.color) + '"></span>' +
          '<span class="util-ra-live-area-groups__label">Group total:</span>' +
          '<strong>' + formatAreaQty(groupAreaSqFt(group)) + '</strong> ' + (isMetricDisplay() ? 'm²' : 'sq ft') +
          (count > 1 ? ' <span class="util-ra-live-area-groups__count">(' + count + ' areas)</span>' : '');
        els.liveGroupList.appendChild(li);
      });
    }
    updateGroupChip();
    setSaveDisabled(!pixelsPerFoot());
    redrawFlooringDirection();
  }

  function renderWallNetList(walls) {
    if (!els.wallList) return;
    els.wallList.innerHTML = '';
    if (!walls.length) {
      show(els.wallList, false);
      return;
    }
    walls.forEach(function (wall) {
      var li = document.createElement('li');
      var lengthLabel = fmtLengthFromFeet(wall.length_lf);
      var areaLabel = isMetricDisplay()
        ? formatAreaQty(wall.net_area_sqft) + ' m²'
        : formatAreaQty(wall.net_area_sqft) + ' sq ft';
      li.textContent = wall.label + ' ' + lengthLabel + ' — ';
      var strong = document.createElement('strong');
      strong.textContent = areaLabel;
      li.appendChild(strong);
      els.wallList.appendChild(li);
    });
    show(els.wallList, true);
  }

  function updateSelectedWallNet(walls) {
    if (!els.wallNetArea) return;
    if (state.selectedWallIndex == null) {
      show(els.wallNetArea, false);
      return;
    }
    var wallsList = listClosedWalls();
    var selected = wallsList[state.selectedWallIndex];
    var row = selected && (walls || []).find(function (w) { return w.id === selected.wallId; });
    if (!row) {
      show(els.wallNetArea, false);
      return;
    }
    els.wallNetArea.textContent = 'Net ' + formatAreaQty(row.net_area_sqft);
    show(els.wallNetArea, true);
  }

  function setAllDisabled(sel, disabled) {
    raAll(sel).forEach(function (btn) {
      btn.disabled = !!disabled;
    });
  }

  function updateToolbar() {
    var undoDisabled = state.undoStack.length === 0;
    if (!undoDisabled && geometryLocked()) {
      undoDisabled = !historyAllowedWhileLocked(state.undoStack[state.undoStack.length - 1]);
    }
    var redoDisabled = state.redoStack.length === 0;
    if (!redoDisabled && geometryLocked()) {
      redoDisabled = !historyAllowedWhileLocked(state.redoStack[state.redoStack.length - 1]);
    }
    setAllDisabled('[data-ra-undo-vertex]', undoDisabled);
    setAllDisabled('[data-ra-redo]', redoDisabled);
    if (els.splitWall) els.splitWall.disabled = state.selectedWallIndex === null;
    setAllDisabled('[data-ra-close-shape]', activeVertices().length < 3 || isActiveClosed());
    setAllDisabled('[data-ra-add-area]', !isActiveClosed());
    setAllDisabled('[data-ra-add-area-new-group]', !isActiveClosed());
    if (els.deleteArea) {
      var canDelete = hasAnyClosedPolygon() || activeVertices().length > 0;
      setAllDisabled('[data-ra-delete-area]', !canDelete);
    }
    if (els.deleteWall) {
      els.deleteWall.disabled = state.selectedWallIndex === null;
    }
    if (els.deleteVertex) {
      els.deleteVertex.disabled = state.selectedVertexIndices.length === 0 || activeVertices().length < 2;
    }
    if (els.deleteRuler) {
      els.deleteRuler.disabled = state.selectedRulerId === null;
    }
    if (els.wallApply) {
      els.wallApply.disabled = state.selectedWallIndex === null || !pixelsPerFoot();
    }
    syncAreaNameField();
  }

  function syncAreaNameField() {
    if (!els.areaNameWrap || !els.areaName) return;
    var poly = activePolygon();
    var closed = !!(poly && poly.closed && poly.vertices.length >= 3);
    show(els.areaNameWrap, closed);
    if (!closed) return;
    if (document.activeElement === els.areaName) return;
    els.areaName.value = poly.name || '';
  }

  var areaNameUndoPushed = false;

  function bindAreaNameField() {
    if (!els.areaName) return;
    els.areaName.addEventListener('focus', function () {
      areaNameUndoPushed = false;
    });
    els.areaName.addEventListener('input', function () {
      var poly = activePolygon();
      if (!poly) return;
      if (!areaNameUndoPushed) {
        pushUndoSnapshot();
        areaNameUndoPushed = true;
      }
      poly.name = String(els.areaName.value).slice(0, AREA_NAME_MAX);
      redrawLabels();
    });
    els.areaName.addEventListener('change', function () {
      var poly = activePolygon();
      if (!poly) return;
      poly.name = sanitizeAreaName(els.areaName.value);
      els.areaName.value = poly.name;
      redrawLabels();
    });
  }

  function updateScaleUI() {
    var ppf = pixelsPerFoot();
    if (els.scaleStatus) {
      if (ppf > 0) {
        els.scaleStatus.textContent = isFullPage()
          ? ('Scale set · ' + fmt(ppf, 1) + ' px/ft')
          : ('Scale: ' + fmt(ppf, 2) + ' px per foot (1 grid cell ≈ 1 ft when calibrated).');
        els.scaleStatus.className = 'util-ra-scale-status is-set';
      } else {
        els.scaleStatus.textContent = isFullPage()
          ? 'Scale not set'
          : 'Scale not set — calibrate before saving rooms.';
        els.scaleStatus.className = 'util-ra-scale-status';
      }
    }
    drawGrid();
    redrawPolygonLine();
    redrawVertices();
    redrawLabels();
    redrawWalls();
    redrawRulers();
    if (state.selectedRulerId !== null && els.rulerLength) {
      var selectedRuler = findRulerById(state.selectedRulerId);
      if (selectedRuler) els.rulerLength.textContent = rulerLengthLabel(selectedRuler);
    }
    updateLiveArea();
  }

  function stopCalibrationMode() {
    state.calibrating = false;
    state.calibPoints = [];
    raAll('[data-ra-calibrate-toggle]').forEach(function (btn) { btn.classList.remove('is-active'); });
    raAll('[data-ra-known-length]').forEach(function (el) {
      el.value = '';
      el.disabled = true;
    });
    raAll('[data-ra-apply-scale]').forEach(function (el) { el.disabled = true; });
    redrawCalib();
    redrawVertices();
  }

  function toggleCalibrate() {
    if (state.calibrating) {
      stopCalibrationMode();
      setFeedback('Calibration cancelled.', false);
      return;
    }
    if (geometryLocked()) {
      warnGeometryLocked();
      return;
    }

    if (state.rulerMode) stopRulerMode();
    stopBgMoveMode();
    clearRulerSelection();

    state.calibrating = true;
    state.calibPoints = [];
    raAll('[data-ra-calibrate-toggle]').forEach(function (btn) { btn.classList.add('is-active'); });
    raAll('[data-ra-known-length]').forEach(function (el) {
      el.disabled = true;
      el.value = '';
    });
    raAll('[data-ra-apply-scale]').forEach(function (el) { el.disabled = true; });
    redrawCalib();
    setFeedback('Click two points on a known wall length.', false);
  }

  function applyScale() {
    if (geometryLocked()) {
      warnGeometryLocked();
      return;
    }
    if (state.calibPoints.length !== 2) return;
    try {
      var knownRaw = '';
      raAll('[data-ra-known-length]').forEach(function (el) {
        if (el.value) knownRaw = el.value;
      });
      var feet = parseLengthInput(knownRaw);
      var pxDist = dist(state.calibPoints[0], state.calibPoints[1]);
      if (pxDist < 5) throw new Error('Points are too close — pick a longer reference.');
      var ppf = pxDist / feet;
      var project = activeProject();
      if (!project) return;

      stopCalibrationMode();
      updateScaleUI();

      if (state.editingRoomId) {
        var room = activeRoom();
        if (!room) return;
        setWorkingPixelsPerFoot(ppf);
        fetch(urlFromTemplate(root.getAttribute('data-room-update-template'), room.id), {
          method: 'PUT',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json'
          },
          body: JSON.stringify({
            name: room.name,
            canvas: serializeCanvas(),
            scale_pixels_per_foot: ppf,
            canvas_width: contentW(),
            canvas_height: contentH(),
            wall_height_ft: wallHeightFt()
          })
        })
          .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
          .then(function (result) {
            if (!result.ok) throw new Error(result.data.message || 'Could not save scale.');
            var idx = project.rooms.findIndex(function (r) { return r.id === room.id; });
            if (idx >= 0) project.rooms[idx] = result.data.room;
            updateScaleUI();
            captureSavedSnapshot();
            setFeedback('Scale calibrated for this room. Parsed as ' + RA.describeParsedLength(feet) + '.', false);
          })
          .catch(function (e) {
            setFeedback(e.message, true);
          });
        return;
      }

      state.draftScale = ppf;
      setWorkingPixelsPerFoot(ppf);
      setFeedback('Scale calibrated. Parsed as ' + RA.describeParsedLength(feet) + '. Save the room to keep this scale with it.', false);
    } catch (err) {
      setFeedback(err.message, true);
    }
  }

  function refreshProjectSelect() {
    var sel = els.projectSelect;
    var current = state.activeProjectId;
    sel.innerHTML = '<option value="">— Select a project —</option>';
    state.projects.forEach(function (p) {
      var opt = document.createElement('option');
      opt.value = p.id;
      opt.textContent = p.name;
      sel.appendChild(opt);
    });
    if (current) sel.value = String(current);
  }

  function renderLinked() {
    var project = activeProject();
    if (!project) {
      show(els.linked, false);
      return;
    }
    show(els.linked, true);
    if (project.client_project_id && project.client_project_name) {
      els.linkedLink.href = '/admin/client-projects/' + project.client_project_id;
      els.linkedLink.removeAttribute('hidden');
    } else {
      els.linkedLink.setAttribute('hidden', '');
    }
    els.linkClientProject.value = project.client_project_id ? String(project.client_project_id) : '';
  }

  function clearEditState() {
    state.editingRoomId = null;
    updateEditUI();
  }

  function updateEditUI() {
    var editing = !!state.editingRoomId;
    var saveLabel = editing ? 'Save changes' : 'Save room';
    var saveTip = editing
      ? 'Save changes to this room.'
      : 'Save this room\'s outline, scale, and objects.';
    raAll('[data-ra-save-room]').forEach(function (btn) {
      btn.setAttribute('aria-label', saveLabel);
    });
    raAll('[data-ra-save-tip]').forEach(function (tip) {
      tip.setAttribute('data-tooltip', saveTip);
    });
    syncGuideLinks();
    updateFullpageTitle();
    raAll('[data-ra-cancel-edit]').forEach(function (btn) {
      show(btn, editing);
    });
    syncExportButtons();
    renderRooms();
  }

  function loadRoom(roomId) {
    var project = activeProject();
    if (!project) return;
    var room = project.rooms.find(function (r) { return r.id === roomId; });
    if (!room || !roomHasDrawableData(room)) {
      setFeedback('Could not load that room.', true);
      return;
    }

    var token = bumpEditorToken();
    clearWallSelection();
    clearRulerSelection();
    state.editingRoomId = room.id;
    if (els.roomName) els.roomName.value = room.name;
    setWallHeight(room.wall_height_ft || DEFAULT_WALL_HEIGHT_FT);
    state.ceilingHeightFt = room.ceiling_height_ft != null && Math.abs(room.ceiling_height_ft - wallHeightFt()) > 0.001
      ? room.ceiling_height_ft
      : null;
    state.flooringWastePercent = room.flooring_waste_percent != null ? room.flooring_waste_percent : (RA.DEFAULT_WASTE_PERCENT || 10);
    state.flooringDirection = room.flooring_direction != null ? room.flooring_direction : (RA.DIRECTION_LENGTH || 0);
    state.flooringBoxSqFt = room.flooring_box_sqft != null ? room.flooring_box_sqft : (RA.DEFAULT_BOX_SQFT || 20);
    syncFinishInputs(true);
    updateEditUI();

    ensureStage();
    requestAnimationFrame(function () {
      if (token !== state.editorToken) return;
      if (state.editingRoomId !== roomId) return;

      var currentProject = activeProject();
      if (!currentProject) return;
      var currentRoom = currentProject.rooms.find(function (r) { return r.id === roomId; });
      if (!currentRoom || !roomHasDrawableData(currentRoom)) {
        clearShape({ force: true });
        return;
      }

      resizeStage();
      loadBackground(currentRoom.background_url || null);

      var source = roomSourceCanvas(currentRoom, currentProject);
      var target = hydrateScaleTarget(roomCanvasRaw(currentRoom), source.w, source.h);
      hydrateCanvas(roomCanvasRaw(currentRoom), target.fromW, target.fromH, target.toW, target.toH);
      applyWorkingScaleFromSaved(
        currentRoom.scale_pixels_per_foot || currentProject.scale_pixels_per_foot,
        target
      );
      updateScaleUI();
      clearUndoStack();
      syncTypedWallsFromCanvas();
      renderTypedWallsList();
      updateTypedCloseBar();
      renderPhotos();
      redrawAll();
      fitViewport();
      requestAnimationFrame(refitCanvasToLayout);
      captureSavedSnapshot();
      setFeedback('Editing "' + currentRoom.name + '". Change the shape and click Save changes.', false);
    });
  }

  function cancelEdit() {
    if (!state.editingRoomId) return;
    clearShape({ force: true });
    revokeDraftBackground();
    state.bgLayout = null;
    loadBackground(null);
    captureSavedSnapshot();
    setFeedback('Left this room. The saved plan is unchanged.', false);
  }

  function renderRooms() {
    var project = activeProject();
    var rooms = project ? project.rooms : [];
    show(els.roomsEmpty, rooms.length === 0);
    show(els.roomsList, rooms.length > 0);
    show(els.total, rooms.length > 0);
    els.roomsList.innerHTML = '';

    var total = 0;
    rooms.forEach(function (room) {
      total += parseFloat(room.area_sqft) || 0;
      var li = document.createElement('li');
      li.className = 'util-ra-rooms-item' + (state.editingRoomId === room.id ? ' is-editing' : '');
      var summaries = room.group_summaries || [];
      var breakdownHtml = '';
      if (summaries.length > 1) {
        breakdownHtml = '<ul class="util-ra-rooms-item__groups">';
        summaries.forEach(function (summary) {
          breakdownHtml +=
            '<li class="util-ra-rooms-item__group">' +
              '<span class="util-ra-rooms-item__group-swatch" style="background:' + esc(summary.color) + '"></span>' +
              '<span class="util-ra-rooms-item__group-area">' + fmt(summary.area_sqft, 2) + ' sq ft</span>' +
              (summary.polygon_count > 1
                ? '<span class="util-ra-rooms-item__group-count">' + summary.polygon_count + ' areas</span>'
                : '') +
            '</li>';
        });
        breakdownHtml += '</ul>';
      }
      li.innerHTML =
        '<div class="util-ra-rooms-item__main">' +
          '<div class="util-ra-rooms-item__title-row">' +
            '<span class="util-ra-rooms-item__name">' + esc(room.name) + '</span>' +
            '<span class="util-ra-rooms-item__area">' + fmt(room.area_sqft, 2) + ' sq ft</span>' +
            (room.photo_count
              ? '<span class="util-ra-rooms-item__photos">' + room.photo_count + (room.photo_count === 1 ? ' photo' : ' photos') + '</span>'
              : '') +
          '</div>' +
          breakdownHtml +
        '</div>' +
        '<div class="util-ra-rooms-item__actions">' +
          '<button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-edit-room="' + room.id + '" title="Load room" aria-label="Load room"><i class="fa fa-folder-open"></i><span class="util-ra-rooms-item__action-label">Load</span></button>' +
          '<button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-copy-area="' + room.area_sqft + '" title="Copy sq ft"><i class="fa fa-copy"></i><span class="util-ra-rooms-item__action-label">Copy</span></button>' +
          (room.pdf_url
            ? '<a class="admin-btn admin-btn--ghost admin-btn--sm" href="' + esc(room.pdf_url) + '" target="_blank" rel="noopener" title="Open attached PDF"><i class="fa fa-file-pdf-o"></i><span class="util-ra-rooms-item__action-label">PDF</span></a>'
            : '') +
          (quoteRoomUrl(room.id)
            ? '<a class="admin-btn admin-btn--primary admin-btn--sm" href="' + esc(quoteRoomUrl(room.id)) + '" title="Create quote from this room"><i class="fa fa-file-text-o"></i><span class="util-ra-rooms-item__action-label">Quote</span></a>'
            : '') +
          '<button type="button" class="admin-btn admin-btn--danger admin-btn--sm" data-delete-room="' + room.id + '" title="Delete"><i class="fa fa-trash"></i><span class="util-ra-rooms-item__action-label">Delete</span></button>' +
        '</div>';
      els.roomsList.appendChild(li);
    });

    els.totalSqft.textContent = fmt(total, 2);
    if (els.quoteAll) {
      var projectQuoteTpl = root.getAttribute('data-quote-project-template') || '';
      if (project && rooms.length >= 2 && projectQuoteTpl) {
        els.quoteAll.href = urlFromTemplate(projectQuoteTpl, project.id);
        show(els.quoteAll, true);
      } else {
        show(els.quoteAll, false);
      }
    }

    els.roomsList.querySelectorAll('[data-edit-room]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var roomId = parseInt(btn.getAttribute('data-edit-room'), 10);
        if (roomId === state.editingRoomId) return;
        promptUnsavedBeforeAction(function () {
          loadRoom(roomId);
        });
      });
    });
    els.roomsList.querySelectorAll('[data-copy-area]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        copyText(btn.getAttribute('data-copy-area'));
      });
    });
    els.roomsList.querySelectorAll('[data-delete-room]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        deleteRoom(parseInt(btn.getAttribute('data-delete-room'), 10));
      });
    });
  }

  function esc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function copyText(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(String(text)).then(function () {
        setFeedback('Copied to clipboard.', false);
      }).catch(function () {
        fallbackCopy(text);
      });
    } else {
      fallbackCopy(text);
    }
  }

  function fallbackCopy(text) {
    var ta = document.createElement('textarea');
    ta.value = String(text);
    document.body.appendChild(ta);
    ta.select();
    try {
      document.execCommand('copy');
      setFeedback('Copied to clipboard.', false);
    } catch (e) {
      setFeedback('Could not copy.', true);
    }
    document.body.removeChild(ta);
  }

  function guideHref() {
    var base = root.getAttribute('data-ra-guide-url') || '';
    if (!base || root.hasAttribute('data-ra-lock-client-project')) return base;
    try {
      var url = new URL(base, window.location.origin);
      if (state.activeProjectId) {
        url.searchParams.set('ra_project', String(state.activeProjectId));
      } else {
        url.searchParams.delete('ra_project');
      }
      if (state.editingRoomId) {
        url.searchParams.set('ra_room', String(state.editingRoomId));
      } else {
        url.searchParams.delete('ra_room');
      }
      return url.pathname + url.search;
    } catch (err) {
      return base;
    }
  }

  function syncGuideLinks() {
    if (!isFullPage()) return;
    var href = guideHref();
    if (!href) return;
    raAll('[data-ra-help-guide]').forEach(function (a) {
      a.setAttribute('href', href);
    });
    raAll('[data-ra-first-tip] a[href]').forEach(function (a) {
      a.setAttribute('href', href);
    });
  }

  function renderWorkspace() {
    var hasProject = !!state.activeProjectId;
    show(els.empty, !hasProject);
    show(els.workspace, hasProject);
    show(els.deleteProjectBtn, hasProject);
    renderLinked();
    renderRooms();
    syncGuideLinks();

    if (!hasProject) {
      resetEditor();
      return;
    }

    var project = activeProject();
    if (!project) {
      resetEditor();
      return;
    }

    var projectId = project.id;
    stopRulerMode();
    clearRulerSelection();
    clearShape({ force: true });
    var token = state.editorToken;
    ensureStage();
    loadBackground(null);
    scheduleStageResize();
    setTimeout(function () {
      if (token !== state.editorToken) return;
      var currentProject = activeProject();
      if (!currentProject || currentProject.id !== projectId) return;

      resizeStage();
      applyWorkingScaleFromSaved(
        currentProject.scale_pixels_per_foot,
        {
          fromW: currentProject.canvas_width || contentW(),
          fromH: currentProject.canvas_height || contentH(),
          toW: contentW(),
          toH: contentH()
        }
      );
      loadBackground(null);
      updateScaleUI();
      refreshViewportAfterContentChange();
      captureSavedSnapshot();
    }, 0);
  }

  function createProject() {
    var name = (els.newProjectName.value || '').trim();
    if (!name) {
      els.newProjectName.focus();
      return;
    }
    var clientId = els.newClientProject.value || null;
    els.createProjectBtn.disabled = true;

    fetch(root.getAttribute('data-project-store-url'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json'
      },
      body: JSON.stringify({ name: name, client_project_id: clientId ? parseInt(clientId, 10) : null })
    })
      .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
      .then(function (result) {
        els.createProjectBtn.disabled = false;
        if (!result.ok) throw new Error(result.data.message || 'Could not create project.');
        state.projects.unshift(result.data.project);
        state.activeProjectId = result.data.project.id;
        els.newProjectName.value = '';
        els.newClientProject.value = '';
        show(els.newProjectPanel, false);
        refreshProjectSelect();
        renderWorkspace();
      })
      .catch(function (e) { els.createProjectBtn.disabled = false; setFeedback(e.message, true); });
  }

  function updateProjectLink(clientProjectId) {
    var project = activeProject();
    if (!project) return;

    fetch(urlFromTemplate(root.getAttribute('data-project-update-template'), project.id), {
      method: 'PUT',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json'
      },
      body: JSON.stringify({ client_project_id: clientProjectId ? parseInt(clientProjectId, 10) : null })
    })
      .then(function (res) {
        return readFetchJson(res, 'Could not update project.');
      })
      .then(function (result) {
        if (!result.ok) return;
        project.client_project_id = result.data.project.client_project_id;
        project.client_project_name = result.data.project.client_project_name;
        renderLinked();
      })
      .catch(function (err) {
        setFeedback(friendlyNetworkError(err, 'Could not update project. Check your connection and try again.'), true);
      });
  }

  function deleteProject() {
    var project = activeProject();
    if (!project) return;
    if (projectHasLockedRoom(project)) {
      setFeedback('Unlock layers to delete this project.', true);
      return;
    }

    confirmAction('Delete this project and all saved rooms?', { title: 'Delete project', danger: true }).then(function (ok) {
      if (!ok) return;

      fetch(urlFromTemplate(root.getAttribute('data-project-destroy-template'), project.id), {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
          'X-CSRF-TOKEN': csrf(),
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json'
        }
      })
        .then(function (res) {
          return readFetchJson(res, 'Could not delete project.');
        })
        .then(function (result) {
          if (!result.ok) {
            setFeedback((result.data && result.data.message) || 'Unlock layers to delete this project.', true);
            return;
          }
          state.projects = state.projects.filter(function (p) { return p.id !== project.id; });
          state.activeProjectId = null;
          refreshProjectSelect();
          renderWorkspace();
          setFeedback('Project deleted.', false);
        })
        .catch(function (err) {
          setFeedback(friendlyNetworkError(err, 'Could not delete project. Check your connection and try again.'), true);
        });
    });
  }

  function saveRoom() {
    var project = activeProject();
    if (!project || !hasAnyClosedPolygon()) {
      var closedErr = new Error('Complete at least one closed area before saving.');
      setFeedback(closedErr.message, true);
      return Promise.reject(closedErr);
    }
    var name = (els.roomName.value || '').trim();
    if (!name) {
      els.roomName.focus();
      setFeedback('Enter a room name.', true);
      return Promise.reject(new Error('Enter a room name.'));
    }

    var isUpdate = !!state.editingRoomId;
    var url = isUpdate
      ? urlFromTemplate(root.getAttribute('data-room-update-template'), state.editingRoomId)
      : urlFromTemplate(root.getAttribute('data-room-store-template'), project.id);
    var method = isUpdate ? 'PUT' : 'POST';

    var scale = pixelsPerFoot();
    if (!scale) {
      var scaleErr = new Error('Calibrate scale before saving.');
      setFeedback(scaleErr.message, true);
      return Promise.reject(scaleErr);
    }

    setSaveDisabled(true);
    return fetch(url, {
      method: method,
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json'
      },
      body: JSON.stringify({
        name: name,
        canvas: serializeCanvas(),
        canvas_width: contentW(),
        canvas_height: contentH(),
        scale_pixels_per_foot: scale,
        wall_height_ft: wallHeightFt(),
        ceiling_height_ft: ceilingHeightFt(),
        flooring_waste_percent: flooringWastePercent(),
        flooring_direction: flooringDirectionDeg(),
        flooring_box_sqft: flooringBoxSqFt()
      })
    })
      .then(function (res) {
        return readFetchJson(res, 'Could not read server response.');
      })
      .then(function (result) {
        if (!result.ok) {
          throw new Error(saveErrorMessage(
            result.data,
            isUpdate ? 'Could not update room.' : 'Could not save room.'
          ));
        }
        var savedRoom = result.data.room;
        if (savedRoom && savedRoom.id && !state.editingRoomId) {
          state.editingRoomId = savedRoom.id;
        }
        var pendingFile = state.draftBackground && state.draftBackground.file;
        if (pendingFile && savedRoom && savedRoom.id) {
          return uploadRoomBackgroundFile(savedRoom.id, pendingFile).then(function (bgResult) {
            savedRoom.background_url = bgResult.background_url;
            return { ok: true, data: { room: savedRoom }, created: !isUpdate };
          }).catch(function (err) {
            throw new Error(RA.planUploadErrorAfterRoomSaved(err));
          });
        }
        return result;
      })
      .then(function (result) {
        setSaveDisabled(false);
        var savedListRoom = result.data.room;
        var idx = project.rooms.findIndex(function (r) { return r.id === savedListRoom.id; });
        if (idx >= 0) {
          var oldRoom = project.rooms[idx];
          if (oldRoom && oldRoom.rulers) savedListRoom.rulers = oldRoom.rulers;
          project.rooms[idx] = savedListRoom;
        } else {
          if (state.draftRulers && state.draftRulers.length) {
            savedListRoom.rulers = state.draftRulers.map(function (r) {
              return {
                id: r.id,
                p1: { x: r.p1.x, y: r.p1.y },
                p2: { x: r.p2.x, y: r.p2.y }
              };
            });
          }
          project.rooms.push(savedListRoom);
        }
        project.canvas_width = contentW();
        project.canvas_height = contentH();
        revokeDraftBackground();
        state.draftScale = null;
        var saved = result.data.room;
        if (saved.background_url) loadBackground(saved.background_url);
        reloadSavedRoomCanvas(saved);
        renderRooms();
        captureSavedSnapshot();
        setFeedback(
          isUpdate
            ? 'Changes saved.'
            : 'Room saved. Add more areas or click New room.',
          false
        );
      })
      .catch(function (e) {
        setSaveDisabled(false);
        setFeedback(e.message, true);
        throw e;
      });
  }

  function roomLayersLocked(roomId) {
    if (state.editingRoomId === roomId && geometryLocked()) return true;
    var project = activeProject();
    if (!project || !project.rooms) return false;
    var room = project.rooms.find(function (r) { return r.id === roomId; });
    if (!room) return false;
    var canvas = room.canvas || room.vertices;
    return !!(canvas && canvas.editor && canvas.editor.layers_locked);
  }

  function projectHasLockedRoom(project) {
    return !!(project && project.rooms && project.rooms.some(function (room) {
      var canvas = room.canvas || room.vertices;
      return !!(canvas && canvas.editor && canvas.editor.layers_locked);
    }));
  }

  function deleteRoom(roomId) {
    if (roomLayersLocked(roomId)) {
      setFeedback('Unlock layers to delete this room.', true);
      return;
    }
    confirmAction('Delete this room?', { title: 'Delete room', danger: true }).then(function (ok) {
      if (!ok) return;

      fetch(urlFromTemplate(root.getAttribute('data-room-destroy-template'), roomId), {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
          'X-CSRF-TOKEN': csrf(),
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json'
        }
      })
        .then(function (res) {
          return readFetchJson(res, 'Could not delete room.');
        })
        .then(function (result) {
          if (!result.ok) {
            setFeedback((result.data && result.data.message) || 'Unlock layers to delete this room.', true);
            return;
          }
          var project = activeProject();
          if (project) {
            project.rooms = project.rooms.filter(function (r) { return r.id !== roomId; });
            if (state.editingRoomId === roomId) {
              clearShape({ force: true });
              loadBackground(null);
              renderPhotos();
            }
            renderRooms();
            setFeedback('Room deleted.', false);
          }
        })
        .catch(function () {
          setFeedback('Could not delete room. Check your connection and try again.', true);
        });
    });
  }

  function uploadRoomBackgroundFile(roomId, file) {
    var fd = new FormData();
    fd.append('background', file);

    return fetch(urlFromTemplate(root.getAttribute('data-room-background-upload-template'), roomId), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json'
      },
      body: fd
    })
      .then(function (res) {
        return readFetchJson(res, 'Could not upload the floor plan.');
      })
      .then(function (result) {
        if (!result.ok) {
          var err = new Error(saveErrorMessage(result.data, result.status === 413
            ? 'That file is too large to upload.'
            : 'Could not upload the floor plan.'));
          err.status = result.status;
          throw err;
        }
        return result.data;
      });
  }

  function uploadBackground(file) {
    if (!file) return;
    state.bgLayout = null;

    if (state.editingRoomId) {
      var room = activeRoom();
      if (!room) return;
      if (room.background_url && geometryLocked()) {
        setFeedback('Unlock layers to change the floor plan.', true);
        return;
      }

      uploadRoomBackgroundFile(room.id, file)
        .then(function (data) {
          room.background_url = data.background_url;
          loadBackground(room.background_url);
          captureSavedSnapshot();
          setFeedback('Floor plan uploaded for this room.', false);
        })
        .catch(function (e) {
          setFeedback(RA.friendlyPlanUploadError(e), true);
        });
      return;
    }

    revokeDraftBackground();
    state.draftBackground = {
      file: file,
      url: URL.createObjectURL(file)
    };
    loadBackground(state.draftBackground.url);
    setFeedback('Floor plan ready for this new room. Save the room to keep it.', false);
  }

  function removeBackground() {
    if (state.editingRoomId) {
      var room = activeRoom();
      if (!room || !room.background_url) return;
      if (geometryLocked() || roomLayersLocked(room.id)) {
        setFeedback('Unlock layers to change the floor plan.', true);
        return;
      }

      confirmAction(
        'Delete the floor plan image for this room? The room outline will stay.',
        { title: 'Delete floor plan', danger: true }
      ).then(function (ok) {
        if (!ok) return;

        fetch(urlFromTemplate(root.getAttribute('data-room-background-destroy-template'), room.id), {
          method: 'DELETE',
          credentials: 'same-origin',
          headers: {
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json'
          }
        })
          .then(function (res) {
            return res.json().then(function (data) {
              return { ok: res.ok, data: data };
            }).catch(function () {
              return { ok: false, data: { message: 'Could not delete floor plan.' } };
            });
          })
          .then(function (result) {
            if (!result.ok) {
              setFeedback((result.data && result.data.message) || 'Unlock layers to change the floor plan.', true);
              return;
            }
            room.background_url = null;
            loadBackground(null);
            captureSavedSnapshot();
            setFeedback('Floor plan image deleted for this room.', false);
          })
          .catch(function () {
            setFeedback('Could not delete floor plan. Check your connection and try again.', true);
          });
      });
      return;
    }

    if (!state.draftBackground) return;

    confirmAction(
      'Remove the floor plan image for this new room?',
      { title: 'Delete floor plan', danger: true }
    ).then(function (ok) {
      if (!ok) return;
      revokeDraftBackground();
      loadBackground(null);
      setFeedback('Floor plan image removed.', false);
    });
  }

  function catalogItemByKey(key) {
    return OBJECT_CATALOG.find(function (item) { return item.key === key; }) || null;
  }

  function paletteIconSvg(item) {
    if (item.type === 'window') {
      return '<svg viewBox="0 0 28 20" aria-hidden="true"><rect x="2" y="6" width="24" height="8" fill="none" stroke="currentColor" stroke-width="1.6"/><line x1="2" y1="10" x2="26" y2="10" stroke="currentColor" stroke-width="1.2"/><line x1="14" y1="6" x2="14" y2="14" stroke="currentColor" stroke-width="1.2"/></svg>';
    }
    if (item.type === 'opening') {
      return '<svg viewBox="0 0 28 20" aria-hidden="true"><line x1="6" y1="4" x2="6" y2="16" stroke="currentColor" stroke-width="2"/><line x1="22" y1="4" x2="22" y2="16" stroke="currentColor" stroke-width="2"/><line x1="6" y1="10" x2="22" y2="10" stroke="currentColor" stroke-width="1.2" stroke-dasharray="3 2"/></svg>';
    }
    if (item.subtype === 'double') {
      return '<svg viewBox="0 0 28 20" aria-hidden="true"><path d="M4 16 A10 10 0 0 1 14 6" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M24 16 A10 10 0 0 0 14 6" fill="none" stroke="currentColor" stroke-width="1.5"/><line x1="4" y1="16" x2="24" y2="16" stroke="currentColor" stroke-width="1.4"/></svg>';
    }
    if (item.subtype === 'sliding' || item.subtype === 'pocket') {
      return '<svg viewBox="0 0 28 20" aria-hidden="true"><rect x="3" y="7" width="12" height="6" fill="none" stroke="currentColor" stroke-width="1.5"/><line x1="3" y1="16" x2="25" y2="16" stroke="currentColor" stroke-width="1.4" stroke-dasharray="3 2"/></svg>';
    }
    if (item.subtype === 'bifold') {
      return '<svg viewBox="0 0 28 20" aria-hidden="true"><path d="M4 16 L9 8 L14 16 L19 8 L24 16" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>';
    }
    if (item.type === 'stairs' && item.subtype === 'l') {
      return '<svg viewBox="0 0 28 20" aria-hidden="true"><path d="M4 16 V8 H16 V4 H24" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M4 13 H10 M4 10 H10" stroke="currentColor" stroke-width="1.1"/></svg>';
    }
    if (item.type === 'stairs' && item.subtype === 'u') {
      return '<svg viewBox="0 0 28 20" aria-hidden="true"><path d="M6 16 V5 H22 V16" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M6 12 H11 M17 12 H22" stroke="currentColor" stroke-width="1.1"/></svg>';
    }
    if (item.type === 'stairs') {
      return '<svg viewBox="0 0 28 20" aria-hidden="true"><path d="M5 16 H23 M5 13 H23 M5 10 H23 M5 7 H23" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>';
    }
    if (item.type === 'cabinet' && item.subtype === 'upper') {
      return '<svg viewBox="0 0 28 20" aria-hidden="true"><rect x="4" y="5" width="20" height="8" fill="none" stroke="currentColor" stroke-width="1.5" stroke-dasharray="3 2"/></svg>';
    }
    if (item.type === 'cabinet' && item.subtype === 'corner') {
      return '<svg viewBox="0 0 28 20" aria-hidden="true"><path d="M5 16 V6 H14 V11 H23 V16 Z" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>';
    }
    if (item.type === 'cabinet' && item.subtype === 'island') {
      return '<svg viewBox="0 0 28 20" aria-hidden="true"><rect x="4" y="6" width="20" height="10" fill="none" stroke="currentColor" stroke-width="1.5"/><rect x="7" y="9" width="14" height="4" fill="none" stroke="currentColor" stroke-width="1.1"/></svg>';
    }
    if (item.type === 'cabinet') {
      return '<svg viewBox="0 0 28 20" aria-hidden="true"><rect x="4" y="6" width="20" height="10" fill="none" stroke="currentColor" stroke-width="1.5"/><line x1="14" y1="6" x2="14" y2="16" stroke="currentColor" stroke-width="1.1"/></svg>';
    }
    if (item.subtype === 'toilet') {
      return '<svg viewBox="0 0 28 20" aria-hidden="true"><rect x="9" y="3" width="10" height="5" fill="none" stroke="currentColor" stroke-width="1.4"/><ellipse cx="14" cy="13" rx="6" ry="5" fill="none" stroke="currentColor" stroke-width="1.4"/></svg>';
    }
    if (item.subtype === 'tub') {
      return '<svg viewBox="0 0 28 20" aria-hidden="true"><rect x="3" y="6" width="22" height="10" rx="3" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>';
    }
    if (item.subtype === 'shower') {
      return '<svg viewBox="0 0 28 20" aria-hidden="true"><rect x="6" y="4" width="16" height="12" fill="none" stroke="currentColor" stroke-width="1.5"/><line x1="6" y1="4" x2="22" y2="16" stroke="currentColor" stroke-width="1.1"/></svg>';
    }
    if (item.subtype === 'vanity') {
      return '<svg viewBox="0 0 28 20" aria-hidden="true"><rect x="3" y="7" width="22" height="8" fill="none" stroke="currentColor" stroke-width="1.5"/><ellipse cx="14" cy="11" rx="4" ry="2.5" fill="none" stroke="currentColor" stroke-width="1.1"/></svg>';
    }
    if (item.subtype === 'sofa') {
      return '<svg viewBox="0 0 28 20" aria-hidden="true"><rect x="2" y="8" width="24" height="8" rx="2" fill="none" stroke="currentColor" stroke-width="1.5"/><rect x="2" y="5" width="24" height="4" fill="none" stroke="currentColor" stroke-width="1.2"/></svg>';
    }
    if (item.subtype === 'bed') {
      return '<svg viewBox="0 0 28 20" aria-hidden="true"><rect x="5" y="3" width="18" height="14" fill="none" stroke="currentColor" stroke-width="1.5"/><rect x="7" y="5" width="14" height="4" fill="none" stroke="currentColor" stroke-width="1.1"/></svg>';
    }
    if (item.subtype === 'dining_table') {
      return '<svg viewBox="0 0 28 20" aria-hidden="true"><ellipse cx="14" cy="10" rx="11" ry="6" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>';
    }
    return '<svg viewBox="0 0 28 20" aria-hidden="true"><path d="M6 16 A12 12 0 0 1 18 4" fill="none" stroke="currentColor" stroke-width="1.5"/><line x1="6" y1="16" x2="24" y2="16" stroke="currentColor" stroke-width="1.4"/><line x1="6" y1="16" x2="6" y2="5" stroke="currentColor" stroke-width="1.6"/></svg>';
  }

  function paletteHostFor(item) {
    if (item.kind === 'window') return els.paletteWindows;
    if (item.kind === 'stairs') return els.paletteStairs;
    if (item.kind === 'cabinet') return els.paletteCabinets;
    if (item.kind === 'fixture') return els.paletteFixtures;
    if (item.kind === 'furniture') return els.paletteFurniture;
    return els.paletteDoors;
  }

  function renderObjectPalette() {
    [els.paletteDoors, els.paletteWindows, els.paletteStairs, els.paletteCabinets, els.paletteFixtures, els.paletteFurniture].forEach(function (el) {
      if (el) el.innerHTML = '';
    });
    OBJECT_CATALOG.forEach(function (item) {
      var host = paletteHostFor(item);
      if (!host) return;
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'util-ra-palette__item';
      btn.setAttribute('data-ra-catalog', item.key);
      btn.setAttribute('aria-label', 'Place ' + item.label);
      btn.innerHTML = '<span class="util-ra-palette__icon">' + paletteIconSvg(item) + '</span><span class="util-ra-palette__name">' + esc(item.label) + '</span>';
      bindPaletteItem(btn, item);
      host.appendChild(btn);
    });
    syncArmedPaletteUi();
  }

  function bindPaletteItem(btn, item) {
    var pointerId = null;
    var startX = 0;
    var startY = 0;
    var dragging = false;

    function onMove(e) {
      if (pointerId == null || e.pointerId !== pointerId) return;
      var dx = e.clientX - startX;
      var dy = e.clientY - startY;
      if (!dragging && Math.hypot(dx, dy) > 6) {
        dragging = true;
        btn.classList.add('is-dragging');
        startPaletteDrag(item, e);
      }
      if (dragging) movePaletteDrag(e);
    }

    function onUp(e) {
      if (pointerId == null || e.pointerId !== pointerId) return;
      document.removeEventListener('pointermove', onMove);
      document.removeEventListener('pointerup', onUp);
      document.removeEventListener('pointercancel', onUp);
      pointerId = null;
      btn.classList.remove('is-dragging');
      if (dragging) {
        finishPaletteDrag(e);
      } else {
        armCatalogItem(item.key);
        collapsePaletteAfterArm();
      }
      dragging = false;
    }

    btn.addEventListener('pointerdown', function (e) {
      if (e.button !== undefined && e.button !== 0) return;
      e.preventDefault();
      pointerId = e.pointerId;
      startX = e.clientX;
      startY = e.clientY;
      dragging = false;
      document.addEventListener('pointermove', onMove);
      document.addEventListener('pointerup', onUp);
      document.addEventListener('pointercancel', onUp);
    });

    btn.addEventListener('click', function () {
      if (pointerId != null) return;
      armCatalogItem(item.key);
      collapsePaletteAfterArm();
    });
  }

  function isNarrowPalette() {
    return window.matchMedia('(max-width: 599px)').matches;
  }

  function collapsePaletteAfterArm() {
    if (isNarrowPalette() || isFullPage()) setPaletteOpen(false);
  }

  function setPaletteOpen(open) {
    if (!els.palette) return;
    els.palette.classList.toggle('is-open', !!open);
    if (els.paletteToggle) {
      els.paletteToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    syncPhoneSheetForDock();
  }

  function togglePaletteOpen() {
    var open = els.palette && els.palette.classList.contains('is-open');
    setPaletteOpen(!open);
    if (!open && isNarrowPalette()) setWallsOpen(false);
  }

  function armCatalogItem(key) {
    state.armedCatalogKey = state.armedCatalogKey === key ? null : key;
    state.placingCatalog = null;
    syncArmedPaletteUi();
    if (state.armedCatalogKey) {
      var item = catalogItemByKey(state.armedCatalogKey);
      if (item && catalogPlacesOnFloor(item)) {
        setFeedback('Tap the floor to place the ' + item.label + '.', false);
      } else {
        setFeedback('Tap a wall to place the ' + (item ? item.label : 'object') + '.', false);
      }
    }
  }

  function syncArmedPaletteUi() {
    if (!els.palette) return;
    els.palette.querySelectorAll('[data-ra-catalog]').forEach(function (btn) {
      btn.classList.toggle('is-armed', btn.getAttribute('data-ra-catalog') === state.armedCatalogKey);
    });
  }

  function startPaletteDrag(item, e) {
    state.placingCatalog = item;
    state.armedCatalogKey = item.key;
    syncArmedPaletteUi();
    ensureObjectGhost(item);
    movePaletteDrag(e);
    if (els.canvasWrap) els.canvasWrap.classList.add('is-placing-object');
  }

  function ensureObjectGhost(item) {
    if (state.objectGhost) state.objectGhost.remove();
    var ghost = document.createElement('div');
    ghost.className = 'util-ra-object-ghost';
    ghost.textContent = item.label;
    document.body.appendChild(ghost);
    state.objectGhost = ghost;
  }

  function movePaletteDrag(e) {
    if (state.objectGhost) {
      state.objectGhost.style.left = (e.clientX + 12) + 'px';
      state.objectGhost.style.top = (e.clientY + 12) + 'px';
    }
    if (!state.stage || !els.canvasWrap) return;
    var rect = els.canvasWrap.getBoundingClientRect();
    var over = e.clientX >= rect.left && e.clientX <= rect.right && e.clientY >= rect.top && e.clientY <= rect.bottom;
    if (!over) {
      state.drawPreview = null;
      redrawDrawPreview();
      return;
    }
    var screen = { x: e.clientX - rect.left, y: e.clientY - rect.top };
    var world = stageToWorld(screen);
    if (catalogPlacesOnFloor(state.placingCatalog)) {
      drawFreeObjectPreview(world, state.placingCatalog);
      return;
    }
    var snap = snapToNearestWall(world);
    if (snap) {
      drawObjectSnapPreview(snap, state.placingCatalog);
    } else if (state.layers.preview) {
      state.layers.preview.destroyChildren();
      flushKonva();
    }
  }

  function finishPaletteDrag(e) {
    var item = state.placingCatalog;
    hideObjectGhost();
    if (els.canvasWrap) els.canvasWrap.classList.remove('is-placing-object');
    if (!item || !els.canvasWrap) {
      state.placingCatalog = null;
      return;
    }
    var rect = els.canvasWrap.getBoundingClientRect();
    var over = e.clientX >= rect.left && e.clientX <= rect.right && e.clientY >= rect.top && e.clientY <= rect.bottom;
    if (over) {
      var world = stageToWorld({ x: e.clientX - rect.left, y: e.clientY - rect.top });
      tryPlaceCatalogItem(item, world);
    }
    state.placingCatalog = null;
    if (state.layers.preview) {
      state.layers.preview.destroyChildren();
      flushKonva();
    }
  }

  function hideObjectGhost() {
    if (state.objectGhost) {
      state.objectGhost.remove();
      state.objectGhost = null;
    }
  }

  function drawObjectSnapPreview(snap, item) {
    var layer = state.layers.preview;
    if (!layer || !item) return;
    layer.destroyChildren();
    var temp = createObjectFromCatalog(item, snap);
    state.nextObjectId -= 1;
    var place = objectPlacement(temp);
    if (!place) {
      flushKonva();
      return;
    }
    var group = new Konva.Group({
      x: place.mid.x,
      y: place.mid.y,
      rotation: place.angle * 180 / Math.PI,
      opacity: 0.7,
      listening: false
    });
    if (isFreeObject(temp)) drawFreeObjectSymbol(group, temp, place);
    else drawObjectSymbol(group, temp, place);
    layer.add(group);
    flushKonva();
  }

  function drawFreeObjectPreview(pos, item) {
    var layer = state.layers.preview;
    if (!layer || !item || !pos) return;
    layer.destroyChildren();
    var temp = createObjectFromCatalog(item, null, pos);
    state.nextObjectId -= 1;
    var place = objectPlacement(temp);
    if (!place) {
      flushKonva();
      return;
    }
    var group = new Konva.Group({
      x: place.mid.x,
      y: place.mid.y,
      rotation: place.angle * 180 / Math.PI,
      opacity: 0.7,
      listening: false
    });
    drawFreeObjectSymbol(group, temp, place);
    layer.add(group);
    flushKonva();
  }

  function tryPlaceCatalogItem(item, pos) {
    if (!item || !pos) return false;
    if (!hasAnyClosedPolygon()) {
      setFeedback('Close a room outline before placing objects.', true);
      return false;
    }
    if (catalogPlacesOnFloor(item)) {
      pushUndoSnapshot();
      var floorObj = createObjectFromCatalog(item, null, pos);
      state.objects.push(floorObj);
      state.armedCatalogKey = null;
      state.placingCatalog = null;
      syncArmedPaletteUi();
      hideObjectGhost();
      if (els.canvasWrap) els.canvasWrap.classList.remove('is-placing-object');
      selectObject(floorObj.id);
      redrawAll();
      setFeedback(item.label + ' placed.', false);
      return true;
    }
    var snap = snapToNearestWall(pos);
    if (!snap) {
      setFeedback('Drop closer to a wall to snap the object.', true);
      return false;
    }
    if (snap.lengthIn + 0.01 < (item.width || 36)) {
      setFeedback('That wall is shorter than the ' + item.label + '.', true);
      return false;
    }
    pushUndoSnapshot();
    var obj = createObjectFromCatalog(item, snap);
    state.objects.push(obj);
    state.armedCatalogKey = null;
    state.placingCatalog = null;
    syncArmedPaletteUi();
    hideObjectGhost();
    if (els.canvasWrap) els.canvasWrap.classList.remove('is-placing-object');
    selectObject(obj.id);
    redrawAll();
    setFeedback(item.label + ' placed on the wall.', false);
    return true;
  }

  function selectObject(objectId, skipRedraw) {
    var obj = state.objects.find(function (o) { return o.id === objectId; });
    if (!obj) return;
    clearWallSelection();
    clearVertexSelection();
    clearRulerSelection();
    state.selectedObjectId = obj.id;
    var parsed = parseWallId(obj.wall_id);
    if (parsed) {
      var entry = findPolygonEntryById(parsed.polygonId);
      if (entry) {
        state.activeGroupId = entry.group.id;
        state.activePolygonId = entry.polygon.id;
      }
    }
    show(els.objectEdit, true);
    if (els.canvasWrap) els.canvasWrap.classList.add('is-object-edit');
    syncObjectPropertiesPanel();
    setFeedback('', false);
    if (!skipRedraw) redrawAll();
    updateToolbar();
  }

  function clearObjectSelection() {
    if (!state.selectedObjectId && els.objectEdit && els.objectEdit.hasAttribute('hidden')) return;
    state.selectedObjectId = null;
    show(els.objectEdit, false);
    if (els.canvasWrap) els.canvasWrap.classList.remove('is-object-edit');
    if (state.layers.objects) redrawObjects();
    updateToolbar();
  }

  function fillCasingOptions(obj) {
    if (!els.objectCasing) return;
    var kind = objectType(obj);
    var options = kind === 'window'
      ? [['4', '4 sides'], ['3', '3 sides (stool)'], ['0', 'None']]
      : [['2', 'Both sides'], ['1', 'One side'], ['0', 'None']];
    var current = String(RA.casingSidesForObject(obj));
    els.objectCasing.innerHTML = '';
    options.forEach(function (opt) {
      var option = document.createElement('option');
      option.value = opt[0];
      option.textContent = opt[1];
      els.objectCasing.appendChild(option);
    });
    if (!options.some(function (opt) { return opt[0] === current; })) {
      current = String(RA.defaultCasingSides(kind, obj && obj.door_face));
    }
    els.objectCasing.value = current;
  }

  function syncObjectPropertiesPanel() {
    var obj = selectedObject();
    if (!obj || !els.objectEdit) return;
    if (els.objectTitle) els.objectTitle.textContent = objectTitle(obj);
    if (els.objectWidth) els.objectWidth.value = String(Math.round((obj.width || 0) * 100) / 100);
    if (els.objectOffset) els.objectOffset.value = String(Math.round((obj.offset || 0) * 100) / 100);
    if (els.objectDepth) els.objectDepth.value = String(Math.round((obj.depth || 0) * 100) / 100);
    if (els.objectRotation) els.objectRotation.value = String(Math.round((obj.rotation || 0) * 10) / 10);
    if (els.objectSwing) els.objectSwing.value = obj.swing || 'left_in';
    if (els.objectHinge) els.objectHinge.value = obj.hinge || 'left';
    fillCasingOptions(obj);
    if (els.objectCasing) els.objectCasing.value = String(RA.casingSidesForObject(obj));
    if (els.objectDoorFace) els.objectDoorFace.value = RA.normalizeDoorFace(obj.door_face);
    var free = isFreeObject(obj);
    var kind = objectType(obj);
    var showSwing = !free && kind === 'door' && obj.subtype !== 'sliding' && obj.subtype !== 'pocket' && obj.subtype !== 'bifold';
    var showCasing = !free && (kind === 'door' || kind === 'window' || kind === 'opening');
    var showDoorFace = !free && kind === 'door';
    show(els.objectSwingWrap, showSwing);
    show(els.objectHingeWrap, showSwing);
    show(els.objectCasingWrap, showCasing);
    show(els.objectDoorFaceWrap, showDoorFace);
    show(els.objectOffsetWrap, !free);
    show(els.objectDepthWrap, free);
    show(els.objectRotationWrap, free);
    if (els.objectFlip) {
      var flipText = els.objectFlip.querySelector('.util-ra-object-edit__btn-text');
      if (flipText) flipText.textContent = free ? '180°' : 'Flip';
    }
  }

  function parseInchesField(raw) {
    var text = String(raw || '').trim();
    if (!text) return null;
    var n = parseFloat(text.replace(/["″in]/gi, ''));
    return isFinite(n) ? n : null;
  }

  function applyObjectWidth() {
    var obj = selectedObject();
    if (!obj || !els.objectWidth) return;
    var value = parseInchesField(els.objectWidth.value);
    if (value == null) {
      setFeedback('Enter a width in inches.', true);
      return;
    }
    if (value < OBJECT_MIN_WIDTH_IN || value > OBJECT_MAX_WIDTH_IN) {
      setFeedback('Width must be between 6 and 240 inches.', true);
      return;
    }
    pushUndoSnapshot();
    obj.width = value;
    if (!isFreeObject(obj)) {
      var wall = resolveWall(obj.wall_id);
      if (wall) clampObjectToWall(obj, wall);
    }
    syncObjectPropertiesPanel();
    redrawAll();
  }

  function applyObjectDepth() {
    var obj = selectedObject();
    if (!obj || !isFreeObject(obj) || !els.objectDepth) return;
    var value = parseInchesField(els.objectDepth.value);
    if (value == null) {
      setFeedback('Enter a depth in inches.', true);
      return;
    }
    if (value < OBJECT_MIN_WIDTH_IN || value > OBJECT_MAX_WIDTH_IN) {
      setFeedback('Depth must be between 6 and 240 inches.', true);
      return;
    }
    pushUndoSnapshot();
    obj.depth = value;
    syncObjectPropertiesPanel();
    redrawAll();
  }

  function applyObjectRotation() {
    var obj = selectedObject();
    if (!obj || !isFreeObject(obj) || !els.objectRotation) return;
    var value = parseFloat(String(els.objectRotation.value || '').replace(/[°deg]/gi, ''));
    if (!isFinite(value)) {
      setFeedback('Enter a rotation in degrees.', true);
      return;
    }
    pushUndoSnapshot();
    obj.rotation = ((value % 360) + 360) % 360;
    syncObjectPropertiesPanel();
    redrawAll();
  }

  function applyObjectOffset() {
    var obj = selectedObject();
    if (!obj || !els.objectOffset) return;
    var value = parseInchesField(els.objectOffset.value);
    if (value == null) {
      setFeedback('Enter an offset in inches from the wall start.', true);
      return;
    }
    pushUndoSnapshot();
    obj.offset = Math.max(0, value);
    var wall = resolveWall(obj.wall_id);
    if (wall) clampObjectToWall(obj, wall);
    syncObjectPropertiesPanel();
    redrawAll();
  }

  function applyObjectSwingHinge() {
    var obj = selectedObject();
    if (!obj) return;
    pushUndoSnapshot();
    if (els.objectSwing) obj.swing = els.objectSwing.value;
    if (els.objectHinge) obj.hinge = els.objectHinge.value;
    var kind = objectType(obj);
    if (els.objectDoorFace && kind === 'door') {
      var nextFace = RA.normalizeDoorFace(els.objectDoorFace.value);
      if (nextFace !== RA.normalizeDoorFace(obj.door_face)) {
        obj.door_face = nextFace;
        if (els.objectCasing) els.objectCasing.value = String(RA.defaultCasingSides('door', nextFace));
      }
      obj.door_face = nextFace;
    }
    if (els.objectCasing && (kind === 'door' || kind === 'window' || kind === 'opening')) {
      obj.casing_sides = RA.casingSidesForObject({
        type: kind,
        casing_sides: els.objectCasing.value,
        door_face: obj.door_face
      });
    }
    if (obj.hinge === 'right' && obj.swing && obj.swing.indexOf('left') === 0) {
      obj.swing = obj.swing.replace('left', 'right');
    } else if (obj.hinge === 'left' && obj.swing && obj.swing.indexOf('right') === 0) {
      obj.swing = obj.swing.replace('right', 'left');
    }
    syncObjectPropertiesPanel();
    redrawAll();
  }

  function flipSelectedObject() {
    var obj = selectedObject();
    if (!obj) return;
    pushUndoSnapshot();
    if (isFreeObject(obj)) {
      obj.rotation = ((obj.rotation || 0) + 180) % 360;
      syncObjectPropertiesPanel();
      redrawAll();
      return;
    }
    obj.hinge = obj.hinge === 'right' ? 'left' : 'right';
    if (obj.swing) {
      obj.swing = obj.hinge === 'right'
        ? obj.swing.replace('left', 'right')
        : obj.swing.replace('right', 'left');
    }
    var wall = resolveWall(obj.wall_id);
    if (wall) {
      var lengthIn = wallLengthInches(wall.a, wall.b);
      obj.offset = Math.max(0, lengthIn - obj.width - obj.offset);
      clampObjectToWall(obj, wall);
    }
    syncObjectPropertiesPanel();
    redrawAll();
  }

  function rotateSelectedObject() {
    var obj = selectedObject();
    if (!obj) return;
    pushUndoSnapshot();
    if (isFreeObject(obj)) {
      obj.rotation = ((obj.rotation || 0) + 90) % 360;
      syncObjectPropertiesPanel();
      redrawAll();
      return;
    }
    if (obj.swing && obj.swing.indexOf('_') >= 0) {
      var parts = obj.swing.split('_');
      parts[1] = parts[1] === 'in' ? 'out' : 'in';
      obj.swing = parts.join('_');
    } else {
      obj.swing = obj.swing === 'left_in' ? 'left_out' : 'left_in';
    }
    syncObjectPropertiesPanel();
    redrawAll();
  }

  function deleteSelectedObject() {
    var obj = selectedObject();
    if (!obj) return;
    pushUndoSnapshot();
    state.objects = state.objects.filter(function (o) { return o.id !== obj.id; });
    clearObjectSelection();
    redrawAll();
    setFeedback('Object removed.', false);
  }

  function disarmObjectPlacement() {
    state.armedCatalogKey = null;
    state.placingCatalog = null;
    hideObjectGhost();
    if (els.canvasWrap) els.canvasWrap.classList.remove('is-placing-object');
    syncArmedPaletteUi();
    if (state.layers.preview) {
      state.layers.preview.destroyChildren();
      flushKonva();
    }
  }

  function setShowDimensions(on) {
    setDimensionsMode(on ? DIM_MODES.all : DIM_MODES.off);
  }

  function setDimensionsMode(mode, silent) {
    if (!DIM_MODES[mode]) mode = DIM_MODES.all;
    state.dimensionsMode = mode;
    state.showDimensions = mode !== DIM_MODES.off;
    raAll('[data-ra-dimensions-mode]').forEach(function (sel) { sel.value = mode; });
    if (els.dimensionsToggle) els.dimensionsToggle.checked = state.showDimensions;
    if (!silent) redrawLabels();
  }

  function setLayersLocked(on, silent) {
    state.layersLocked = !!on;
    raAll('[data-ra-lock-layers]').forEach(function (box) { box.checked = state.layersLocked; });
    if (els.canvasWrap) els.canvasWrap.classList.toggle('is-layers-locked', state.layersLocked);
    if (state.layersLocked) {
      clearVertexSelection();
      stopBgMoveMode();
      // Keep opening selection; only drop vertex/wall edits.
    }
    syncBackgroundUI();
    updateToolbar();
    redrawVertices();
    redrawWalls();
    if (!silent) {
      setFeedback(state.layersLocked
        ? 'Wall corners locked. Doors, windows, and notes stay editable.'
        : 'Wall corners unlocked.', false);
    }
  }

  function setAngleSnap(on, silent) {
    state.angleSnap = !!on;
    raAll('[data-ra-snap-toggle]').forEach(function (btn) {
      btn.classList.toggle('is-active', state.angleSnap);
      btn.setAttribute('aria-pressed', state.angleSnap ? 'true' : 'false');
    });
    if (!silent) {
      setFeedback(state.angleSnap ? 'Angle snap on (45° / 90°). Hold Alt to bypass.' : 'Angle snap off.', false);
    }
  }

  function templatesPopoverOpen() {
    if (isPhoneChrome()) return root.classList.contains('is-more-open');
    return !!(els.templatesPopover && !els.templatesPopover.hasAttribute('hidden'));
  }

  function placeObjectDock() {
    var dock = root.querySelector('.util-ra-object-dock');
    var card = root.querySelector('.util-ra-canvas-card');
    var slot = root.querySelector('[data-ra-sheet-dock]');
    if (!dock || !card) return;
    if (isPhoneChrome() && slot) {
      if (dock.parentElement !== slot) slot.appendChild(dock);
      return;
    }
    if (dock.parentElement === card) return;
    var edit = card.querySelector('.util-ra-edit-dock');
    var wrap = card.querySelector('.util-ra-canvas-wrap');
    card.insertBefore(dock, edit || wrap || null);
  }

  function syncPhoneSheetForDock() {
    if (!isPhoneChrome()) {
      root.classList.remove('is-dock-open');
      return;
    }
    var open = (els.palette && els.palette.classList.contains('is-open'))
      || (els.wallsPanel && els.wallsPanel.classList.contains('is-open'));
    root.classList.toggle('is-dock-open', !!open);
    if (open) {
      root.style.setProperty('--ra-sheet-h', 'min(52vh, 420px)');
      return;
    }
    if (root.classList.contains('is-sheet-expanded')) return;
    root.style.setProperty('--ra-sheet-h', 'auto');
  }

  function syncPhoneBarOverflow() {
    var bar = root.querySelector('[data-ra-phone-bar]');
    var scroll = root.querySelector('[data-ra-phone-scroll]');
    if (!bar || !scroll) return;
    bar.classList.toggle('is-overflowing', scroll.scrollWidth > scroll.clientWidth + 1);
  }

  var moreLastFocus = null;

  function moreSheetFocusables() {
    if (!els.moreSheet) return [];
    return Array.prototype.filter.call(
      els.moreSheet.querySelectorAll('a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])'),
      function (el) {
        if (el.disabled || el.hidden) return false;
        var cs = window.getComputedStyle(el);
        return cs.display !== 'none' && cs.visibility !== 'hidden' && el.getClientRects().length > 0;
      }
    );
  }

  function trapMoreFocus(e) {
    if (!root.classList.contains('is-more-open') || e.key !== 'Tab' || !els.moreSheet) return;
    var list = moreSheetFocusables();
    if (!list.length) return;
    var first = list[0];
    var last = list[list.length - 1];
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  }

  function setMoreOpen(open) {
    var wasOpen = root.classList.contains('is-more-open');
    root.classList.toggle('is-more-open', !!open);
    raAll('[data-ra-more-toggle]').forEach(function (btn) {
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      btn.classList.toggle('is-active', !!open);
    });
    if (els.moreSheet) {
      els.moreSheet.setAttribute('aria-modal', open ? 'true' : 'false');
    }
    if (isPhoneChrome() && els.templatesPopover) {
      els.templatesPopover.removeAttribute('hidden');
    }
    if (open && isPhoneChrome() && els.moreSheet) {
      els.moreSheet.scrollTop = 0;
      if (!wasOpen) moreLastFocus = document.activeElement;
      requestAnimationFrame(function () {
        var closeBtn = els.moreSheet.querySelector('[data-ra-more-close]');
        var list = moreSheetFocusables();
        (closeBtn || list[0] || els.moreSheet).focus();
      });
    } else if (!open && wasOpen) {
      var toggle = root.querySelector('[data-ra-more-toggle]');
      if (toggle) toggle.focus();
      else if (moreLastFocus && moreLastFocus.focus) moreLastFocus.focus();
    }
  }

  function scrollMoreToTemplates() {
    var sec = root.querySelector('[data-ra-more-templates]');
    if (!sec || !els.moreSheet) return;
    requestAnimationFrame(function () {
      var sheetBox = els.moreSheet.getBoundingClientRect();
      var secBox = sec.getBoundingClientRect();
      els.moreSheet.scrollTop += (secBox.top - sheetBox.top) - 8;
    });
  }

  function setTemplatesPopover(open) {
    if (isPhoneChrome()) {
      setMoreOpen(!!open);
      if (open) scrollMoreToTemplates();
      raAll('[data-ra-templates-toggle]').forEach(function (btn) {
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        btn.classList.toggle('is-active', !!open);
      });
      return;
    }
    if (!els.templatesPopover || !els.templatesToggle) return;
    show(els.templatesPopover, open);
    raAll('[data-ra-templates-toggle]').forEach(function (btn) {
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      btn.classList.toggle('is-active', !!open);
    });
    if (open) {
      var r = els.templatesToggle.getBoundingClientRect();
      var left = Math.max(8, Math.min(r.left, window.innerWidth - 280));
      els.templatesPopover.style.position = 'fixed';
      els.templatesPopover.style.top = Math.round(r.bottom + 8) + 'px';
      els.templatesPopover.style.left = Math.round(left) + 'px';
      els.templatesPopover.style.right = 'auto';
      els.templatesPopover.style.zIndex = '80';
    }
  }

  function bindTemplatesPopoverFollow() {
    if (!els.templatesPopover || !els.templatesToggle) return;
    var reposition = function () {
      if (isPhoneChrome()) return;
      if (templatesPopoverOpen()) setTemplatesPopover(true);
    };
    window.addEventListener('scroll', reposition, true);
    window.addEventListener('resize', reposition);
    var toolbar = root.querySelector('.util-ra-canvas-toolbar');
    if (toolbar) toolbar.addEventListener('scroll', reposition);
  }

  function toggleTemplatesPopover() {
    setTemplatesPopover(!templatesPopoverOpen());
  }

  function closeTemplatesPopover() {
    setTemplatesPopover(false);
  }

  function readTemplateSize(fallbackW, fallbackL) {
    var w = fallbackW;
    var l = fallbackL;
    try {
      if (els.typeRectW && String(els.typeRectW.value).trim()) w = parseLengthInput(els.typeRectW.value);
    } catch (e) { /* keep fallback */ }
    try {
      if (els.typeRectL && String(els.typeRectL.value).trim()) l = parseLengthInput(els.typeRectL.value);
    } catch (e) { /* keep fallback */ }
    return { w: w, l: l };
  }

  function templateRectFeet(w, l) {
    return RA.rectangle(w, l);
  }

  function templateLFeet(w, h, rw, rh) {
    return RA.lShape(w, h, rw, rh);
  }

  function templateUFeet(w, h, ow, od) {
    return RA.uShape(w, h, ow, od);
  }

  function templateTFeet(bw, bh, sw, sh) {
    return RA.tShape(bw, bh, sw, sh);
  }

  function feetTemplateToPixels(feetVerts) {
    var ppf = ensureTypedScale();
    var start = typedStartPoint();
    return feetVerts.map(function (v) {
      return clampToCanvas({ x: start.x + v.x * ppf, y: start.y + v.y * ppf });
    });
  }

  function applyRoomTemplate(kind) {
    if (geometryLocked()) {
      warnGeometryLocked();
      return;
    }
    ensureInitialCanvas();
    var size = (kind === 'l' || kind === 'u' || kind === 't')
      ? readTemplateSize(16, 12)
      : readTemplateSize(12, 10);
    var feet = RA.shapeFromKind(kind, size.w, size.l);
    pushUndoSnapshot();
    setActiveVertices(feetTemplateToPixels(feet));
    setActiveClosed(true);
    rematchAllObjects();
    syncTypedWallsFromCanvas();
    renderTypedWallsList();
    updateTypedCloseBar();
    closeTemplatesPopover();
    setMoreOpen(false);
    state.templateKind = (kind === 'l' || kind === 'u' || kind === 't') ? kind : 'rect';
    redrawAll();
    fitViewport();
    setFeedback((kind === 'l' ? 'L-shape' : kind === 'u' ? 'U-shape' : kind === 't' ? 'T-shape' : 'Rectangle') + ' placed. Type lengths to refine.', false);
  }

  function insertAlcoveVertices(verts, wallIndex, width, depth, closed) {
    var next = RA.insertAlcove(verts, wallIndex, width, depth, closed);
    if (!next || next.length < 3) return null;
    return next;
  }

  function longestWallIndex() {
    var best = 0;
    var bestLen = -1;
    var count = wallCount();
    for (var i = 0; i < count; i++) {
      var a = activeVertices()[i];
      var b = activeVertices()[wallEndIndex(i)];
      var len = dist(a, b);
      if (len > bestLen) {
        bestLen = len;
        best = i;
      }
    }
    return best;
  }

  function addAlcove() {
    if (geometryLocked()) {
      warnGeometryLocked();
      return;
    }
    if (wallCount() < 1) {
      setFeedback('Draw a wall first, then add an alcove.', true);
      return;
    }
    var idx = state.selectedWallIndex != null ? state.selectedWallIndex : longestWallIndex();
    var ppf = ensureTypedScale();
    pushUndoSnapshot();
    var next = insertAlcoveVertices(activeVertices(), idx, 4 * ppf, 2 * ppf, isActiveClosed());
    if (!next || !isValidShape(next, isActiveClosed())) {
      discardLastUndoSnapshot();
      setFeedback('Could not add an alcove on that wall.', true);
      return;
    }
    setActiveVertices(next.map(clampToCanvas));
    rematchAllObjects();
    syncTypedWallsFromCanvas();
    renderTypedWallsList();
    updateTypedCloseBar();
    closeTemplatesPopover();
    setMoreOpen(false);
    clearWallSelection();
    state.templateKind = 'alcove';
    redrawAll();
    setFeedback('Alcove added. Edit the new wall lengths if needed.', false);
  }

  function moveObjectAlongWall(obj, pos) {
    if (!obj || !pos) return;
    if (isFreeObject(obj)) {
      obj.x = pos.x;
      obj.y = pos.y;
      redrawObjects();
      syncObjectPropertiesPanel();
      return;
    }
    var snap = snapToNearestWall(pos, hitTolerance(OBJECT_SNAP_PX * 2));
    if (!snap) return;
    applySnapToObject(obj, snap);
    state.openingHint = openingCornerHint(obj);
    redrawPolygonLine();
    redrawObjects();
    redrawLabels();
    redrawWalls();
    redrawDrawPreview();
    syncObjectPropertiesPanel();
  }

  function syncExportButtons() {
    if (els.attachPdf) {
      show(els.attachPdf, !!state.editingRoomId);
    }
    if (els.createQuote) {
      var quoteTpl = root.getAttribute('data-quote-room-template') || '';
      if (state.editingRoomId && quoteTpl) {
        els.createQuote.href = urlFromTemplate(quoteTpl, state.editingRoomId);
        show(els.createQuote, true);
      } else {
        show(els.createQuote, false);
      }
    }
  }

  function roomExportTitle() {
    var room = activeRoom();
    var project = activeProject();
    var roomName = (els.roomName && els.roomName.value.trim()) || (room && room.name) || 'Room';
    if (project && project.name) return project.name + ' — ' + roomName;
    return roomName + ' — Room plan';
  }

  function captureRoomPng(pixelRatio) {
    if (!state.stage) return '';
    var prevShow = state.showDimensions;
    var prevDimMode = state.dimensionsMode;
    var prevViewport = {
      scale: state.viewport.scale,
      x: state.viewport.x,
      y: state.viewport.y,
      zoomPercent: state.viewport.zoomPercent
    };
    state.exporting = true;
    state.showDimensions = true;
    state.dimensionsMode = DIM_MODES.all;
    fitViewport();
    redrawAll();
    var bg = new Konva.Rect({
      x: 0,
      y: 0,
      width: state.stage.width(),
      height: state.stage.height(),
      fill: '#f7f8fa',
      listening: false
    });
    state.konvaLayer.add(bg);
    bg.moveToBottom();
    state.konvaLayer.draw();
    var dataUrl = '';
    try {
      dataUrl = state.stage.toDataURL({ pixelRatio: pixelRatio || 2, mimeType: 'image/png' });
    } catch (err) {
      dataUrl = '';
    }
    bg.destroy();
    state.exporting = false;
    state.showDimensions = prevShow;
    state.dimensionsMode = prevDimMode;
    state.viewport.scale = prevViewport.scale;
    state.viewport.x = prevViewport.x;
    state.viewport.y = prevViewport.y;
    state.viewport.zoomPercent = prevViewport.zoomPercent;
    applyViewport();
    redrawAll();
    return dataUrl;
  }

  function downloadDataUrl(dataUrl, filename) {
    var a = document.createElement('a');
    a.href = dataUrl;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  }

  function slugFilename(name, ext) {
    var slug = String(name || 'room-plan').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    return (slug || 'room-plan') + '.' + ext;
  }

  function exportPng() {
    if (!hasAnyClosedPolygon()) {
      setFeedback('Draw a closed room before exporting.', true);
      return;
    }
    var dataUrl = captureRoomPng();
    if (!dataUrl) {
      setFeedback('Could not export PNG.', true);
      return;
    }
    downloadDataUrl(dataUrl, slugFilename(roomExportTitle(), 'png'));
    setFeedback('PNG downloaded.', false);
  }

  function addPdfField(form, name, value) {
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value == null ? '' : String(value);
    form.appendChild(input);
  }

  function metricRows(metrics) {
    if (isMetricDisplay()) {
      return [
        { label: 'Floor area', value: formatMetricQty(metrics.area_sqft * SQFT_TO_SQM) + ' m²' },
        { label: 'Perimeter', value: formatMetricQty(metrics.perimeter_lf * FEET_TO_METERS) + ' m' },
        { label: 'Baseboard', value: formatMetricQty(metrics.baseboard_lf * FEET_TO_METERS) + ' m' },
        { label: 'Wall height', value: formatMetricQty(metrics.wall_height_ft * FEET_TO_METERS) + ' m' },
        { label: 'Ceiling height', value: formatMetricQty((metrics.ceiling_height_ft || metrics.wall_height_ft) * FEET_TO_METERS) + ' m' },
        { label: 'Ceiling area', value: formatMetricQty((metrics.ceiling_area_sqft || metrics.area_sqft) * SQFT_TO_SQM) + ' m²' },
        { label: 'Net wall area', value: formatMetricQty((metrics.net_wall_area_sqft != null ? metrics.net_wall_area_sqft : metrics.wall_area_sqft) * SQFT_TO_SQM) + ' m²' },
        { label: 'Flooring with waste', value: formatMetricQty((metrics.flooring_with_waste_sqft || metrics.area_sqft) * SQFT_TO_SQM) + ' m²' },
        { label: 'Flooring boxes', value: String(metrics.flooring_boxes || 0) },
        { label: 'Box size', value: formatMetricQty((metrics.flooring_box_sqft || 20) * SQFT_TO_SQM) + ' m²' },
        { label: 'Casing', value: formatMetricQty((metrics.casing_lf || 0) * FEET_TO_METERS) + ' m' },
        { label: 'Doors', value: String(metrics.door_count) },
        { label: 'Windows', value: String(metrics.window_count) },
        { label: 'Openings', value: String(metrics.opening_count) }
      ];
    }
    return [
      { label: 'Floor area', value: formatMetricQty(metrics.area_sqft) + ' sq ft' },
      { label: 'Perimeter', value: formatMetricQty(metrics.perimeter_lf) + ' lf' },
      { label: 'Baseboard', value: formatMetricQty(metrics.baseboard_lf) + ' lf' },
      { label: 'Wall height', value: formatMetricQty(metrics.wall_height_ft) + ' ft' },
      { label: 'Ceiling height', value: formatMetricQty(metrics.ceiling_height_ft || metrics.wall_height_ft) + ' ft' },
      { label: 'Ceiling area', value: formatMetricQty(metrics.ceiling_area_sqft || metrics.area_sqft) + ' sq ft' },
      { label: 'Net wall area', value: formatMetricQty(metrics.net_wall_area_sqft != null ? metrics.net_wall_area_sqft : metrics.wall_area_sqft) + ' sq ft' },
      { label: 'Flooring with waste', value: formatMetricQty(metrics.flooring_with_waste_sqft || metrics.area_sqft) + ' sq ft' },
      { label: 'Flooring boxes', value: String(metrics.flooring_boxes || 0) },
      { label: 'Box size', value: formatMetricQty(metrics.flooring_box_sqft || 20) + ' sq ft' },
      { label: 'Casing', value: formatMetricQty(metrics.casing_lf || 0) + ' lf' },
      { label: 'Doors', value: String(metrics.door_count) },
      { label: 'Windows', value: String(metrics.window_count) },
      { label: 'Openings', value: String(metrics.opening_count) }
    ];
  }

  function submitPdfForm(attach) {
    var pdfUrl = root.getAttribute('data-pdf-url');
    if (!pdfUrl) {
      setFeedback('PDF export is not available.', true);
      return;
    }
    if (!hasAnyClosedPolygon()) {
      setFeedback('Draw a closed room before exporting.', true);
      return;
    }
    var metrics = computeWallMetrics();
    var diagram = captureRoomPng(1.25);
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = pdfUrl;
    form.target = '_blank';
    form.style.display = 'none';
    addPdfField(form, '_token', csrf());
    addPdfField(form, 'title', roomExportTitle());
    addPdfField(form, 'summary', 'Wall metrics from the Room Area drawing.');
    addPdfField(form, 'wall_height_ft', metrics.wall_height_ft);
    addPdfField(form, 'ceiling_height_ft', metrics.ceiling_height_ft);
    addPdfField(form, 'flooring_waste_percent', metrics.flooring_waste_percent);
    addPdfField(form, 'flooring_direction', metrics.flooring_direction);
    addPdfField(form, 'flooring_box_sqft', metrics.flooring_box_sqft);
    if (state.editingRoomId) addPdfField(form, 'room_id', state.editingRoomId);
    if (attach) addPdfField(form, 'attach', '1');
    if (diagram) addPdfField(form, 'diagram', diagram);
    metricRows(metrics).forEach(function (row, idx) {
      addPdfField(form, 'rows[' + idx + '][label]', row.label);
      addPdfField(form, 'rows[' + idx + '][value]', row.value);
    });
    metricObjects().forEach(function (obj, idx) {
      addPdfField(form, 'objects[' + idx + '][type]', obj.type || '');
      addPdfField(form, 'objects[' + idx + '][subtype]', obj.subtype || '');
      addPdfField(form, 'objects[' + idx + '][width]', obj.width);
      addPdfField(form, 'objects[' + idx + '][offset]', obj.offset);
      addPdfField(form, 'objects[' + idx + '][wall_id]', obj.wall_id || '');
    });
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
    if (attach && state.editingRoomId) {
      var room = activeRoom();
      if (room) {
        room.pdf_url = urlFromTemplate(root.getAttribute('data-room-pdf-template'), state.editingRoomId);
      }
      setFeedback('PDF attached to this room' + (activeProject() && activeProject().client_project_id ? ' and the linked client project.' : '.'), false);
    } else {
      setFeedback('PDF opened in a new tab.', false);
    }
  }

  function exportPdf() {
    submitPdfForm(false);
  }

  function attachPdf() {
    if (!state.editingRoomId) {
      setFeedback('Save the room before attaching a PDF.', true);
      return;
    }
    submitPdfForm(true);
  }

  function ensureTypedScale() {
    var ppf = pixelsPerFoot();
    if (ppf > 0) return ppf;
    setWorkingPixelsPerFoot(DEFAULT_TYPED_PPF);
    if (!state.editingRoomId) state.draftScale = DEFAULT_TYPED_PPF;
    updateScaleUI();
    return DEFAULT_TYPED_PPF;
  }

  function typedStartPoint() {
    if (state.typedStart) return { x: state.typedStart.x, y: state.typedStart.y };
    var verts = activeVertices();
    if (verts.length) return { x: verts[0].x, y: verts[0].y };
    return {
      x: Math.max(48, contentW() * 0.18),
      y: Math.max(40, contentH() * 0.18)
    };
  }

  function wallsFromVertices(verts, closed) {
    var ppf = pixelsPerFoot() || DEFAULT_TYPED_PPF;
    var walls = [];
    var n = (verts || []).length;
    if (n < 2) return walls;
    var count = closed && n >= 3 ? n : n - 1;
    var minFt = 1 / 16 / INCHES_PER_FOOT;
    for (var i = 0; i < count; i++) {
      var a = verts[i];
      var b = verts[(i + 1) % n];
      var lengthFt = Math.hypot(b.x - a.x, b.y - a.y) / ppf;
      if (closed && lengthFt < minFt) continue;
      walls.push({
        lengthFt: lengthFt,
        headingDeg: normalizeHeading((Math.atan2(b.y - a.y, b.x - a.x) * 180) / Math.PI)
      });
    }
    return walls;
  }

  function syncTypedWallsFromCanvas() {
    var verts = activeVertices();
    var walls = wallsFromVertices(verts, isActiveClosed());
    state.typedWalls = walls;
    if (verts.length) {
      state.typedStart = { x: verts[0].x, y: verts[0].y };
    }
    if (walls.length) {
      var last = walls[walls.length - (walls[walls.length - 1].closing ? 2 : 1)];
      if (last) state.typedHeading = last.headingDeg;
    } else {
      state.typedHeading = 0;
    }
  }

  function normalizeHeading(deg) {
    var n = deg % 360;
    if (n < 0) n += 360;
    return n;
  }

  function nextTypedHeading(current, turn, customRaw) {
    var next = current;
    if (turn === 'left') next = current - 90;
    else if (turn === 'custom') next = current + (parseFloat(customRaw) || 0);
    else next = current + 90;
    return normalizeHeading(next);
  }

  function snapHeading(deg) {
    var n = normalizeHeading(deg);
    var nearest = Math.round(n / 90) * 90;
    if (nearest >= 360) nearest = 0;
    var delta = Math.abs(n - nearest);
    delta = Math.min(delta, 360 - delta);
    return delta <= SQUARE_SNAP_DEG ? nearest : n;
  }

  function headingGlyph(deg) {
    var n = normalizeHeading(deg);
    if (n < 22.5 || n >= 337.5) return '→';
    if (n < 67.5) return '↘';
    if (n < 112.5) return '↓';
    if (n < 157.5) return '↙';
    if (n < 202.5) return '←';
    if (n < 247.5) return '↖';
    if (n < 292.5) return '↑';
    return '↗';
  }

  function wallTurnLabel(walls, idx) {
    if (idx === 0) return { text: '→', title: 'First wall, right' };
    var turn = normalizeHeading(walls[idx].headingDeg - walls[idx - 1].headingDeg);
    if (Math.abs(turn - 90) < 2) return { text: 'R90', title: 'Turn right 90°' };
    if (Math.abs(turn - 270) < 2) return { text: 'L90', title: 'Turn left 90°' };
    if (turn <= 180) return { text: '+' + Math.round(turn) + '°', title: 'Turn +' + Math.round(turn) + '°' };
    return { text: '−' + Math.round(360 - turn) + '°', title: 'Turn left ' + Math.round(360 - turn) + '°' };
  }

  function formatClosingGap(inches) {
    var abs = Math.abs(inches);
    var rounded = Math.round(abs * 16) / 16;
    if (rounded < 1 / 16) return '0"';
    var whole = Math.floor(rounded);
    var frac = Math.round((rounded - whole) * 16);
    if (frac === 16) { whole += 1; frac = 0; }
    var fracStr = formatSixteenthInches(frac);
    if (fracStr === '') return whole + '"';
    if (whole === 0) return fracStr + '"';
    if (fracStr === '1/2') return whole + '½"';
    if (fracStr === '1/4') return whole + '¼"';
    if (fracStr === '3/4') return whole + '¾"';
    return whole + ' ' + fracStr + '"';
  }

  function closingGapLabel(gapIn) {
    if (Math.abs(gapIn) < 1 / 16) return 'closed';
    return 'off by ' + formatClosingGap(gapIn);
  }

  function analyzeClosing(verts) {
    if (!verts || verts.length < 2) return null;
    var ppf = pixelsPerFoot() || DEFAULT_TYPED_PPF;
    var last = verts[verts.length - 1];
    var start = verts[0];
    var dx = (start.x - last.x) / ppf;
    var dy = (start.y - last.y) / ppf;
    var gapFt = Math.hypot(dx, dy);
    var gapIn = gapFt * INCHES_PER_FOOT;
    return {
      gapIn: gapIn,
      label: closingGapLabel(gapIn),
      canAuto: gapIn > (1 / 16) && gapIn < CLOSE_GAP_THRESHOLD_IN
    };
  }

  function distributePoints(points) {
    var n = points.length;
    if (n < 3) return points.slice();
    var start = points[0];
    var last = points[n - 1];
    var dx = start.x - last.x;
    var dy = start.y - last.y;
    var steps = n - 1;
    var out = [{ x: start.x, y: start.y }];
    for (var i = 1; i < n; i++) {
      var t = i / steps;
      out.push({
        x: points[i].x + dx * t,
        y: points[i].y + dy * t
      });
    }
    var adj = out[n - 1];
    if (Math.hypot(adj.x - start.x, adj.y - start.y) < 0.05) out.pop();
    return out;
  }

  function applyTypedWalls(walls, start, closeIt) {
    var ppf = ensureTypedScale();
    var pts = [{ x: start.x, y: start.y }];
    walls.forEach(function (w) {
      if (w.closing) return;
      var rad = (w.headingDeg * Math.PI) / 180;
      var px = w.lengthFt * ppf;
      var last = pts[pts.length - 1];
      pts.push({
        x: last.x + px * Math.cos(rad),
        y: last.y + px * Math.sin(rad)
      });
    });
    if (closeIt && pts.length >= 4) {
      var closeLast = pts[pts.length - 1];
      var closeFirst = pts[0];
      if (Math.hypot(closeLast.x - closeFirst.x, closeLast.y - closeFirst.y) < 0.5) {
        pts.pop();
      }
    }
    setActiveVertices(pts.map(clampToCanvas));
    setActiveClosed(!!closeIt && pts.length >= 3);
    state.typedStart = { x: start.x, y: start.y };
    state.typedWalls = walls.filter(function (w) { return !w.closing; });
    if (state.typedWalls.length) {
      state.typedHeading = state.typedWalls[state.typedWalls.length - 1].headingDeg;
    }
    redrawAll();
    if (closeIt && pts.length >= 3) {
      syncTypedWallsFromCanvas();
    }
    renderTypedWallsList();
    updateTypedCloseBar();
    fitViewport();
  }

  function setTypedTurn(turn) {
    state.typedTurn = turn === 'left' || turn === 'custom' ? turn : 'right';
    if (els.typeDirBtns) {
      els.typeDirBtns.forEach(function (btn) {
        var on = btn.getAttribute('data-ra-type-dir') === state.typedTurn;
        btn.classList.toggle('is-active', on);
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
    }
    show(els.typeAngleWrap, state.typedTurn === 'custom');
  }

  function addTypedWall() {
    if (geometryLocked()) {
      warnGeometryLocked();
      return;
    }
    if (isActiveClosed()) {
      setFeedback('Room is closed. Start a new area to type more walls.', true);
      return;
    }
    var lengthFt;
    try {
      lengthFt = parseLengthInput(els.typeLength ? els.typeLength.value : '');
    } catch (err) {
      setFeedback(err.message, true);
      return;
    }
    ensureTypedScale();
    pushUndoSnapshot();
    var start = typedStartPoint();
    var walls = state.typedWalls.length ? state.typedWalls.slice() : wallsFromVertices(activeVertices(), isActiveClosed());
    var heading;
    if (walls.length === 0) {
      heading = 0;
    } else {
      heading = nextTypedHeading(
        walls[walls.length - 1].headingDeg,
        state.typedTurn,
        els.typeAngle && els.typeAngle.value
      );
    }
    walls.push({ lengthFt: lengthFt, headingDeg: heading });
    applyTypedWalls(walls, start, false);
    if (els.typeLength) {
      els.typeLength.value = '';
      els.typeLength.focus();
    }
    setTypedTurn('right');
  }

  function addTypedRectangle() {
    if (geometryLocked()) {
      warnGeometryLocked();
      return;
    }
    var w;
    var l;
    try {
      w = parseLengthInput(els.typeRectW ? els.typeRectW.value : '');
      l = parseLengthInput(els.typeRectL ? els.typeRectL.value : '');
    } catch (err) {
      setFeedback(err.message, true);
      return;
    }
    ensureTypedScale();
    pushUndoSnapshot();
    var start = typedStartPoint();
    var walls = [
      { lengthFt: w, headingDeg: 0 },
      { lengthFt: l, headingDeg: 90 },
      { lengthFt: w, headingDeg: 180 },
      { lengthFt: l, headingDeg: 270 }
    ];
    applyTypedWalls(walls, start, true);
    updateTypedCloseBar();
    setFeedback('Rectangle added.', false);
  }

  function closeTypedRoom() {
    if (isActiveClosed()) {
      updateTypedCloseBar();
      return;
    }
    if (activeVertices().length < 3) {
      setFeedback('Add at least three walls before closing.', true);
      return;
    }
    closeShape();
    syncTypedWallsFromCanvas();
    renderTypedWallsList();
    updateTypedCloseBar();
  }

  function autoCloseTyped() {
    if (geometryLocked()) {
      warnGeometryLocked();
      return;
    }
    var verts = activeVertices().slice();
    if (verts.length < 3) return;
    pushUndoSnapshot();
    var walls = (state.typedWalls.length ? state.typedWalls : wallsFromVertices(verts, isActiveClosed()))
      .filter(function (w) { return !w.closing; })
      .map(function (w) {
        return { lengthFt: w.lengthFt, headingDeg: snapHeading(w.headingDeg) };
      });
    var start = typedStartPoint();
    var ppf = ensureTypedScale();
    var pts = [{ x: start.x, y: start.y }];
    walls.forEach(function (w) {
      var rad = (w.headingDeg * Math.PI) / 180;
      var px = w.lengthFt * ppf;
      var last = pts[pts.length - 1];
      pts.push({ x: last.x + px * Math.cos(rad), y: last.y + px * Math.sin(rad) });
    });
    var dist = distributePoints(pts);
    setActiveVertices(dist.map(clampToCanvas));
    setActiveClosed(dist.length >= 3);
    state.typedWalls = walls;
    state.typedStart = start;
    redrawAll();
    syncTypedWallsFromCanvas();
    renderTypedWallsList();
    updateTypedCloseBar();
    fitViewport();
    setFeedback('Closing gap distributed.', false);
  }

  function editTypedWallLength(index, raw) {
    if (geometryLocked()) {
      warnGeometryLocked();
      renderTypedWallsList();
      return;
    }
    var walls = (state.typedWalls.length ? state.typedWalls : wallsFromVertices(activeVertices(), isActiveClosed()))
      .filter(function (w) { return !w.closing; });
    if (!walls[index]) return;
    var lengthFt;
    try {
      lengthFt = parseLengthInput(raw);
    } catch (err) {
      setFeedback(err.message, true);
      renderTypedWallsList();
      return;
    }
    pushUndoSnapshot();
    walls[index].lengthFt = lengthFt;
    applyTypedWalls(walls, typedStartPoint(), isActiveClosed());
  }

  function deleteTypedWall(index) {
    if (geometryLocked()) {
      warnGeometryLocked();
      return;
    }
    var walls = (state.typedWalls.length ? state.typedWalls : wallsFromVertices(activeVertices(), isActiveClosed()))
      .filter(function (w) { return !w.closing; });
    if (!walls[index]) return;
    pushUndoSnapshot();
    walls.splice(index, 1);
    applyTypedWalls(walls, typedStartPoint(), false);
  }

  function renderTypedWallsList() {
    if (!els.typeList) return;
    var walls = state.typedWalls.length ? state.typedWalls : wallsFromVertices(activeVertices(), isActiveClosed());
    if (!walls.length && activeVertices().length) {
      syncTypedWallsFromCanvas();
      walls = state.typedWalls;
    }
    els.typeList.innerHTML = '';
    show(els.typeList, walls.length > 0);
    var visible = walls.filter(function (wall) { return !wall.closing; });
    visible.forEach(function (wall, idx) {
      var li = document.createElement('li');
      li.className = 'util-ra-type-walls__item';
      var dir = wallTurnLabel(visible, idx);
      li.innerHTML =
        '<span class="util-ra-type-walls__n">' + (idx + 1) + '</span>' +
        '<label class="util-sr-only" for="ra-type-wall-' + idx + '">Wall ' + (idx + 1) + ' length</label>' +
        '<input class="admin-input util-ra-type-walls__item-len" id="ra-type-wall-' + idx + '" type="text" inputmode="decimal" value="' +
          esc(fmtLengthFromFeet(wall.lengthFt)) + '" data-ra-type-edit="' + idx + '">' +
        '<span class="util-ra-type-walls__item-dir" title="' + esc(dir.title) + '">' + esc(dir.text) + '</span>' +
        '<button type="button" class="admin-btn admin-btn--ghost util-ra-type-walls__item-del" data-ra-type-del="' +
          idx + '" aria-label="Delete wall ' + (idx + 1) + '"><i class="fa fa-trash" aria-hidden="true"></i></button>';
      els.typeList.appendChild(li);
    });
    els.typeList.querySelectorAll('[data-ra-type-edit]').forEach(function (input) {
      input.addEventListener('change', function () {
        editTypedWallLength(parseInt(input.getAttribute('data-ra-type-edit'), 10), input.value);
      });
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          input.blur();
        }
      });
    });
    els.typeList.querySelectorAll('[data-ra-type-del]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        deleteTypedWall(parseInt(btn.getAttribute('data-ra-type-del'), 10));
      });
    });
  }

  function updateTypedCloseBar() {
    if (!els.typeCloseBar) return;
    var walls = (state.typedWalls.length ? state.typedWalls : wallsFromVertices(activeVertices(), isActiveClosed()))
      .filter(function (w) { return !w.closing; });
    var n = walls.length;
    var closed = isActiveClosed();
    show(els.typeCloseBar, n >= 1 && !closed);
    if (n < 1 || closed) return;
    if (els.typeGap) {
      els.typeGap.textContent = n + (n === 1 ? ' wall, open' : ' walls, open');
      els.typeGap.classList.remove('is-closed', 'is-off');
    }
    show(els.typeAutoclose, false);
    if (els.typeClose) els.typeClose.disabled = n < 3;
  }

  function resetTypedWalls() {
    state.typedWalls = [];
    state.typedStart = null;
    state.typedHeading = 0;
    setTypedTurn('right');
    renderTypedWallsList();
    updateTypedCloseBar();
  }

  function photoStoreUrl(roomId) {
    var tpl = root.getAttribute('data-room-photos-store-template') || '';
    return tpl ? urlFromTemplate(tpl, roomId) : '';
  }

  function photoDestroyUrl(roomId, photoId) {
    var tpl = root.getAttribute('data-room-photo-destroy-template') || '';
    return tpl.replace('__RID__', String(roomId)).replace('__PID__', String(photoId));
  }

  function photoUpdateUrl(roomId, photoId) {
    var tpl = root.getAttribute('data-room-photo-update-template') || '';
    return tpl.replace('__RID__', String(roomId)).replace('__PID__', String(photoId));
  }

  function activeRoomPhotos() {
    var room = activeRoom();
    return room && Array.isArray(room.photos) ? room.photos : [];
  }

  function renderPhotos() {
    var editing = !!state.editingRoomId;
    show(els.photos, editing);
    if (!editing) {
      closeLightbox();
      return;
    }
    var photos = activeRoomPhotos();
    if (els.photosCount) {
      els.photosCount.textContent = photos.length ? (photos.length + (photos.length === 1 ? ' photo' : ' photos')) : '';
    }
    show(els.photosEmpty, photos.length === 0);
    show(els.photosStrip, photos.length > 0);
    if (!els.photosStrip) return;
    els.photosStrip.innerHTML = '';
    photos.forEach(function (photo) {
      var li = document.createElement('li');
      li.className = 'util-ra-photos__thumb';
      li.innerHTML =
        '<button type="button" class="util-ra-photos__thumb-btn" data-ra-photo-open="' + photo.id + '" aria-label="' + esc(photo.caption || 'Open photo') + '">' +
          '<img src="' + esc(photo.url) + '" alt="' + esc(photo.caption || 'Site photo') + '">' +
        '</button>' +
        '<button type="button" class="util-ra-photos__thumb-del" data-ra-photo-del="' + photo.id + '" aria-label="Delete photo">' +
          '<i class="fa fa-times" aria-hidden="true"></i>' +
        '</button>';
      els.photosStrip.appendChild(li);
    });
    els.photosStrip.querySelectorAll('[data-ra-photo-open]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openLightbox(parseInt(btn.getAttribute('data-ra-photo-open'), 10));
      });
    });
    els.photosStrip.querySelectorAll('[data-ra-photo-del]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        deletePhoto(parseInt(btn.getAttribute('data-ra-photo-del'), 10));
      });
    });
  }

  function uploadPhotos(fileList) {
    if (!state.editingRoomId) {
      setFeedback('Save the room before attaching photos.', true);
      return;
    }
    var files = Array.prototype.slice.call(fileList || []);
    if (!files.length) return;
    var url = photoStoreUrl(state.editingRoomId);
    if (!url) return;
    var fd = new FormData();
    files.forEach(function (file) { fd.append('photos[]', file); });
    fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json'
      },
      body: fd
    })
      .then(function (res) {
        return readFetchJson(res, 'Could not upload photos.');
      })
      .then(function (result) {
        if (!result.ok) {
          throw new Error(saveErrorMessage(result.data, result.status === 413
            ? 'That file is too large to upload.'
            : 'Could not upload photos.'));
        }
        var room = activeRoom();
        if (room) {
          room.photos = (room.photos || []).concat(result.data.photos || []);
          room.photo_count = result.data.photo_count;
        }
        renderPhotos();
        renderRooms();
        setFeedback('Photos added.', false);
      })
      .catch(function (err) {
        setFeedback(friendlyNetworkError(err, 'Could not upload photos. Check your connection and try again.'), true);
      });
  }

  function deletePhoto(photoId) {
    if (!state.editingRoomId) return;
    confirmAction('Delete this photo?', { title: 'Delete photo', danger: true }).then(function (ok) {
      if (!ok) return;
      fetch(photoDestroyUrl(state.editingRoomId, photoId), {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
          'X-CSRF-TOKEN': csrf(),
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json'
        }
      })
        .then(function (res) {
          return readFetchJson(res, 'Could not delete photo.');
        })
        .then(function (result) {
          if (!result.ok) {
            setFeedback((result.data && result.data.message) || 'Could not delete photo.', true);
            return;
          }
          var room = activeRoom();
          if (room) {
            room.photos = (room.photos || []).filter(function (p) { return p.id !== photoId; });
            room.photo_count = result.data.photo_count;
          }
          renderPhotos();
          renderRooms();
          closeLightbox();
          setFeedback('Photo deleted.', false);
        })
        .catch(function (err) {
          setFeedback(friendlyNetworkError(err, 'Could not delete photo. Check your connection and try again.'), true);
        });
    });
  }

  function openLightbox(photoId) {
    var photo = activeRoomPhotos().find(function (p) { return p.id === photoId; });
    if (!photo || !els.lightbox) return;
    state.lightboxPhotoId = photo.id;
    if (els.lightboxImg) {
      els.lightboxImg.src = photo.url;
      els.lightboxImg.alt = photo.caption || 'Site photo';
    }
    if (els.lightboxCaption) {
      els.lightboxCaption.value = photo.caption || '';
    }
    show(els.lightbox, true);
    if (els.lightboxCaption) {
      setTimeout(function () {
        els.lightboxCaption.focus();
        els.lightboxCaption.select();
      }, 0);
    }
  }

  function saveLightboxCaption() {
    if (!state.editingRoomId || !state.lightboxPhotoId || !els.lightboxCaption) return;
    var photo = activeRoomPhotos().find(function (p) { return p.id === state.lightboxPhotoId; });
    if (!photo) return;
    var caption = els.lightboxCaption.value.trim();
    if ((photo.caption || '') === caption) return;
    fetch(photoUpdateUrl(state.editingRoomId, photo.id), {
      method: 'PATCH',
      credentials: 'same-origin',
      headers: {
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ caption: caption })
    })
      .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
      .then(function (result) {
        if (!result.ok) throw new Error(result.data.message || 'Could not save caption.');
        photo.caption = result.data.photo ? result.data.photo.caption : caption;
        if (els.lightboxImg) els.lightboxImg.alt = photo.caption || 'Site photo';
        renderPhotos();
        renderRooms();
      })
      .catch(function (err) {
        setFeedback((err && err.message) || 'Could not save caption.', true);
      });
  }

  function closeLightbox() {
    saveLightboxCaption();
    if (!els.lightbox) return;
    show(els.lightbox, false);
    state.lightboxPhotoId = null;
    if (els.lightboxImg) els.lightboxImg.src = '';
    if (els.lightboxCaption) els.lightboxCaption.value = '';
  }

  function shortcutsPopoverOpen() {
    return !!(els.shortcutsPopover && !els.shortcutsPopover.hasAttribute('hidden'));
  }

  function setShortcutsPopover(open) {
    if (!els.shortcutsPopover || !els.shortcutsToggle) return;
    show(els.shortcutsPopover, open);
    els.shortcutsToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    els.shortcutsToggle.classList.toggle('is-active', !!open);
  }

  function toggleShortcutsPopover() {
    setShortcutsPopover(!shortcutsPopoverOpen());
  }

  function closeShortcutsPopover() {
    setShortcutsPopover(false);
  }

  function nudgeSelected(dx, dy) {
    if (Math.abs(dx) < 1e-9 && Math.abs(dy) < 1e-9) return;
    if (state.selectedObjectId) {
      nudgeSelectedObject(dx, dy);
      return;
    }
    if (state.selectedVertexIndices.length) {
      nudgeSelectedVertices(dx, dy);
      return;
    }
    if (state.selectedWallIndex !== null) {
      nudgeSelectedWall(dx, dy);
      return;
    }
    if (state.selectedRulerId !== null) {
      nudgeSelectedRuler(dx, dy);
    }
  }

  function nudgeSelectedObject(dx, dy) {
    var obj = selectedObject();
    if (!obj) return;
    if (isFreeObject(obj)) {
      pushUndoSnapshot();
      obj.x = (obj.x || 0) + dx;
      obj.y = (obj.y || 0) + dy;
      syncObjectPropertiesPanel();
      redrawAll();
      return;
    }
    var place = objectPlacement(obj);
    if (!place || !place.wall) return;
    var wx = place.wall.b.x - place.wall.a.x;
    var wy = place.wall.b.y - place.wall.a.y;
    var len = Math.hypot(wx, wy) || 1;
    var alongPx = (dx * wx + dy * wy) / len;
    var inches = alongPx / (pixelsPerFoot() || 12) * INCHES_PER_FOOT;
    if (Math.abs(inches) < 1e-6) return;
    pushUndoSnapshot();
    obj.offset = (obj.offset || 0) + inches;
    clampObjectToWall(obj, place.wall);
    syncObjectPropertiesPanel();
    redrawAll();
  }

  function nudgeSelectedVertices(dx, dy) {
    if (geometryLocked()) {
      warnGeometryLocked();
      return;
    }
    if (!state.selectedVertexIndices.length) return;
    pushUndoSnapshot();
    var orig = activeVertices().map(function (v) { return { x: v.x, y: v.y }; });
    var nudgePoly = activePolygon();
    state.selectedVertexIndices.forEach(function (idx) {
      var v = activeVertices()[idx];
      if (!v) return;
      var next = clampToCanvas({ x: v.x + dx, y: v.y + dy });
      activeVertices()[idx] = next;
      if (nudgePoly) applyLinkedVertexMove(nudgePoly.id, idx, next);
    });
    if (!isValidShape(activeVertices(), isActiveClosed())) {
      setActiveVertices(orig);
      discardLastUndoSnapshot();
      setFeedback('That nudge would make the shape cross itself.', true);
      return;
    }
    rematchAllObjects();
    redrawAll();
  }

  function nudgeSelectedWall(dx, dy) {
    if (geometryLocked()) {
      warnGeometryLocked();
      return;
    }
    if (state.selectedWallIndex === null) return;
    var idx = state.selectedWallIndex;
    var endIdx = wallEndIndex(idx);
    var a = activeVertices()[idx];
    var b = activeVertices()[endIdx];
    if (!a || !b) return;
    pushUndoSnapshot();
    var origA = { x: a.x, y: a.y };
    var origB = { x: b.x, y: b.y };
    activeVertices()[idx] = clampToCanvas({ x: a.x + dx, y: a.y + dy });
    activeVertices()[endIdx] = clampToCanvas({ x: b.x + dx, y: b.y + dy });
    if (!isValidShape(activeVertices(), isActiveClosed())) {
      activeVertices()[idx] = origA;
      activeVertices()[endIdx] = origB;
      discardLastUndoSnapshot();
      setFeedback('That nudge would make the shape cross itself.', true);
      return;
    }
    rematchAllObjects();
    refreshSelectedWallLengthInput();
    redrawAll();
  }

  function nudgeSelectedRuler(dx, dy) {
    var ruler = findRulerById(state.selectedRulerId);
    if (!ruler) return;
    ruler.p1 = clampToCanvas({ x: ruler.p1.x + dx, y: ruler.p1.y + dy });
    ruler.p2 = clampToCanvas({ x: ruler.p2.x + dx, y: ruler.p2.y + dy });
    redrawRulers();
  }

  function handleRoomAreaKeydown(e) {
    if (e.key === 'Escape' && root.classList.contains('is-more-open')) {
      e.preventDefault();
      setMoreOpen(false);
      return;
    }
    if (isTypingTarget(e.target)) return;
    if (els.lightbox && !els.lightbox.hasAttribute('hidden')) return;

    var cmd = e.ctrlKey || e.metaKey;
    var key = e.key;

    if (key === 'Escape') {
      e.preventDefault();
      if (shortcutsPopoverOpen()) {
        closeShortcutsPopover();
        return;
      }
      if (templatesPopoverOpen()) {
        closeTemplatesPopover();
        return;
      }
      if (state.armedCatalogKey || state.placingCatalog) {
        disarmObjectPlacement();
        return;
      }
      if (cancelRulerDraft()) return;
      if (state.rulerMode) {
        stopRulerMode();
        return;
      }
      if (state.calibrating) {
        stopCalibrationMode();
        return;
      }
      if (cancelActiveDrawing()) return;
      if (state.selectedObjectId) {
        clearObjectSelection();
        return;
      }
      if (state.selectedWallIndex !== null) {
        clearWallSelection();
        return;
      }
      if (state.selectedVertexIndices.length) {
        clearVertexSelection();
        return;
      }
      if (state.selectedRulerId !== null) {
        clearRulerSelection();
      }
      return;
    }

    if (cmd && !e.altKey && key.toLowerCase() === 'z') {
      e.preventDefault();
      if (e.shiftKey) redoAction();
      else undoVertex();
      return;
    }

    if (cmd && !e.altKey && (key === 'y' || key === 'Y')) {
      e.preventDefault();
      redoAction();
      return;
    }

    if (!cmd && (key === '?' || (key === '/' && e.shiftKey))) {
      e.preventDefault();
      toggleShortcutsPopover();
      return;
    }

    if (!cmd && (key === '+' || key === '=' || key === 'Add')) {
      e.preventDefault();
      zoomIn();
      return;
    }

    if (!cmd && (key === '-' || key === '_' || key === 'Subtract')) {
      e.preventDefault();
      zoomOut();
      return;
    }

    if (!cmd && (key === '0' || key === 'Numpad0')) {
      e.preventDefault();
      fitViewport();
      return;
    }

    if (!cmd && (key === 'ArrowLeft' || key === 'ArrowRight' || key === 'ArrowUp' || key === 'ArrowDown')) {
      var step = (e.shiftKey ? NUDGE_STEP_SHIFT_PX : NUDGE_STEP_PX) / uiScale();
      var dx = 0;
      var dy = 0;
      if (key === 'ArrowLeft') dx = -step;
      if (key === 'ArrowRight') dx = step;
      if (key === 'ArrowUp') dy = -step;
      if (key === 'ArrowDown') dy = step;
      e.preventDefault();
      nudgeSelected(dx, dy);
      return;
    }

    if (key === 'Backspace' && isDrawingOpenShape()) {
      if (!state.selectedVertexIndices.length && state.selectedWallIndex === null && state.selectedRulerId === null && !state.selectedObjectId) {
        e.preventDefault();
        removeLastDrawVertex();
        return;
      }
    }

    if (key !== 'Delete' && key !== 'Backspace') return;
    if (state.selectedObjectId) {
      e.preventDefault();
      deleteSelectedObject();
    } else if (state.selectedVertexIndices.length > 0) {
      e.preventDefault();
      deleteSelectedVertex();
    } else if (state.selectedWallIndex !== null) {
      e.preventDefault();
      deleteSelectedWall();
    } else if (state.selectedRulerId !== null && !state.rulerMode) {
      e.preventDefault();
      deleteSelectedRuler();
    }
  }

  function bindEvents() {
    els.projectSelect.addEventListener('change', function () {
      var newId = els.projectSelect.value ? parseInt(els.projectSelect.value, 10) : null;
      var previousId = state.activeProjectId;
      if (newId === previousId) return;

      els.projectSelect.value = previousId ? String(previousId) : '';

      promptUnsavedBeforeAction(function () {
        state.activeProjectId = newId;
        els.projectSelect.value = newId ? String(newId) : '';
        state.calibrating = false;
        state.calibPoints = [];
        raAll('[data-ra-calibrate-toggle]').forEach(function (btn) { btn.classList.remove('is-active'); });
        raAll('[data-ra-known-length]').forEach(function (el) { el.disabled = true; });
        raAll('[data-ra-apply-scale]').forEach(function (el) { el.disabled = true; });
        stopRulerMode();
        clearRulerSelection();
        renderWorkspace();
      });
    });

    els.newProjectToggle.addEventListener('click', function () {
      show(els.newProjectPanel, els.newProjectPanel.hasAttribute('hidden'));
    });

    els.createProjectBtn.addEventListener('click', createProject);
    els.deleteProjectBtn.addEventListener('click', deleteProject);
    els.linkClientProject.addEventListener('change', function () {
      updateProjectLink(els.linkClientProject.value);
    });

    raAll('[data-ra-calibrate-toggle]').forEach(function (btn) {
      btn.addEventListener('click', toggleCalibrate);
    });
    raAll('[data-ra-apply-scale]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        applyScale();
      });
    });

    raAll('[data-ra-undo-vertex]').forEach(function (btn) {
      btn.addEventListener('click', undoVertex);
    });
    raAll('[data-ra-redo]').forEach(function (btn) {
      btn.addEventListener('click', redoAction);
    });
    if (els.splitWall) els.splitWall.addEventListener('click', splitSelectedWall);
    if (els.shortcutsToggle) {
      els.shortcutsToggle.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        toggleShortcutsPopover();
      });
    }
    raAll('[data-ra-close-shape]').forEach(function (btn) {
      btn.addEventListener('click', closeShape);
    });
    if (els.paletteToggle) {
      els.paletteToggle.addEventListener('click', function (e) {
        e.preventDefault();
        togglePaletteOpen();
      });
    }
    if (els.wallsToggle) {
      els.wallsToggle.addEventListener('click', function (e) {
        e.preventDefault();
        toggleWallsOpen();
      });
    }
    root.querySelectorAll('[data-ra-thickness]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var val = btn.getAttribute('data-ra-thickness');
        if (val === 'custom') {
          show(els.thicknessCustom, true);
          if (els.thicknessCustomInput) els.thicknessCustomInput.focus();
          root.querySelectorAll('[data-ra-thickness]').forEach(function (chip) {
            chip.classList.toggle('is-active', chip === btn);
          });
          return;
        }
        pushUndoSnapshot();
        setWallThickness(val);
      });
    });
    if (els.thicknessCustomInput) {
      els.thicknessCustomInput.addEventListener('change', function () {
        var n = parseFloat(els.thicknessCustomInput.value);
        pushUndoSnapshot();
        setWallThickness(n);
      });
      els.thicknessCustomInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          var n = parseFloat(els.thicknessCustomInput.value);
          pushUndoSnapshot();
          setWallThickness(n);
        }
      });
    }
    raAll('[data-ra-add-area]').forEach(function (btn) {
      btn.addEventListener('click', addAreaSameGroup);
    });
    raAll('[data-ra-add-area-new-group]').forEach(function (btn) {
      btn.addEventListener('click', addAreaNewGroup);
    });
    raAll('[data-ra-delete-area]').forEach(function (btn) {
      btn.addEventListener('click', deleteActivePolygon);
    });
    raAll('[data-ra-clear-shape]').forEach(function (btn) {
      btn.addEventListener('click', clearShape);
    });
    raAll('[data-ra-save-room]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        saveRoom().catch(function () {});
      });
    });
    if (els.newRoom) els.newRoom.addEventListener('click', startNewRoom);
    if (els.newRoomSecondary) els.newRoomSecondary.addEventListener('click', startNewRoom);
    raAll('[data-ra-cancel-edit]').forEach(function (btn) {
      btn.addEventListener('click', cancelEdit);
    });

    if (els.wallApply) els.wallApply.addEventListener('click', applyWallLength);
    if (els.wallCancel) els.wallCancel.addEventListener('click', clearWallSelection);
    if (els.deleteWall) els.deleteWall.addEventListener('click', deleteSelectedWall);
    if (els.deleteVertex) els.deleteVertex.addEventListener('click', deleteSelectedVertex);
    if (els.vertexCancel) els.vertexCancel.addEventListener('click', clearVertexSelection);
    raAll('[data-ra-ruler-toggle]').forEach(function (btn) {
      btn.addEventListener('click', toggleRulerMode);
    });
    if (els.deleteRuler) els.deleteRuler.addEventListener('click', deleteSelectedRuler);
    if (els.rulerCancel) els.rulerCancel.addEventListener('click', clearRulerSelection);
    if (els.objectWidth) {
      els.objectWidth.addEventListener('change', applyObjectWidth);
      els.objectWidth.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          applyObjectWidth();
        }
      });
    }
    if (els.objectOffset) {
      els.objectOffset.addEventListener('change', applyObjectOffset);
      els.objectOffset.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          applyObjectOffset();
        }
      });
    }
    if (els.objectDepth) {
      els.objectDepth.addEventListener('change', applyObjectDepth);
      els.objectDepth.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          applyObjectDepth();
        }
      });
    }
    if (els.objectRotation) {
      els.objectRotation.addEventListener('change', applyObjectRotation);
      els.objectRotation.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          applyObjectRotation();
        }
      });
    }
    if (els.objectSwing) els.objectSwing.addEventListener('change', applyObjectSwingHinge);
    if (els.objectHinge) els.objectHinge.addEventListener('change', applyObjectSwingHinge);
    if (els.objectCasing) els.objectCasing.addEventListener('change', applyObjectSwingHinge);
    if (els.objectDoorFace) els.objectDoorFace.addEventListener('change', applyObjectSwingHinge);
    if (els.objectFlip) els.objectFlip.addEventListener('click', flipSelectedObject);
    if (els.objectRotate) els.objectRotate.addEventListener('click', rotateSelectedObject);
    if (els.deleteObject) els.deleteObject.addEventListener('click', deleteSelectedObject);
    if (els.objectCancel) els.objectCancel.addEventListener('click', clearObjectSelection);
    if (els.dimensionsToggle) {
      els.dimensionsToggle.addEventListener('change', function () {
        setShowDimensions(els.dimensionsToggle.checked);
      });
    }
    raAll('[data-ra-dimensions-mode]').forEach(function (sel) {
      sel.addEventListener('change', function () {
        setDimensionsMode(sel.value);
      });
    });
    raAll('[data-ra-lock-layers]').forEach(function (box) {
      box.addEventListener('change', function () {
        setLayersLocked(box.checked);
      });
    });
    raAll('[data-ra-snap-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        setAngleSnap(!state.angleSnap);
      });
    });
    raAll('[data-ra-unit-system]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        setUnitSystem(btn.getAttribute('data-ra-unit-system'));
      });
    });
    raAll('[data-ra-templates-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        toggleTemplatesPopover();
      });
    });
    raAll('[data-ra-more-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        setMoreOpen(!root.classList.contains('is-more-open'));
      });
    });
    raAll('[data-ra-more-close]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        setMoreOpen(false);
      });
    });
    raAll('[data-ra-template]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        applyRoomTemplate(btn.getAttribute('data-ra-template'));
      });
    });
    raAll('[data-ra-alcove]').forEach(function (btn) {
      btn.addEventListener('click', addAlcove);
    });
    bindAreaNameField();

    document.addEventListener('keydown', handleRoomAreaKeydown);
    document.addEventListener('keydown', trapMoreFocus, true);

    document.addEventListener('pointerdown', function (e) {
      if (shortcutsPopoverOpen() && !(els.shortcuts && els.shortcuts.contains(e.target))) {
        closeShortcutsPopover();
      }
      if (isPhoneChrome()) {
        if (root.classList.contains('is-more-open')) {
          var more = els.moreSheet;
          var phoneBar = root.querySelector('[data-ra-phone-bar]');
          if (more && !more.contains(e.target) && !(phoneBar && phoneBar.contains(e.target))) {
            setMoreOpen(false);
          }
        }
        return;
      }
      if (templatesPopoverOpen() && !(els.templatesRoot && els.templatesRoot.contains(e.target))) {
        closeTemplatesPopover();
      }
    });

    if (els.wallLength) {
      els.wallLength.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          applyWallLength();
        }
        if (e.key === 'Escape') clearWallSelection();
      });
    }

    if (els.bgFile) els.bgFile.addEventListener('change', function () {
      if (els.bgFile.files && els.bgFile.files[0]) {
        uploadBackground(els.bgFile.files[0]);
        els.bgFile.value = '';
      }
    });

    if (els.bgRemove) els.bgRemove.addEventListener('click', removeBackground);
    if (els.bgDeleteCanvas) els.bgDeleteCanvas.addEventListener('click', removeBackground);
    if (els.bgShowImage) {
      els.bgShowImage.addEventListener('change', function () {
        setBackgroundImageVisible(els.bgShowImage.checked);
      });
    }
    if (els.bgMoveToggle) els.bgMoveToggle.addEventListener('click', toggleBgMoveMode);
    els.copyTotal.addEventListener('click', function () {
      copyText(els.totalSqft.textContent);
    });

    if (els.wallHeight) {
      els.wallHeight.addEventListener('change', function () {
        var next = wallHeightFtFromValue(els.wallHeight.value);
        if (next == null) {
          syncWallHeightInput(true);
          return;
        }
        setWallHeight(next, true);
        updateLiveArea();
      });
      els.wallHeight.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        var next = wallHeightFtFromValue(els.wallHeight.value);
        if (next == null) {
          syncWallHeightInput(true);
          return;
        }
        setWallHeight(next, true);
        updateLiveArea();
      });
    }
    if (els.ceilingHeight) {
      els.ceilingHeight.addEventListener('change', function () {
        var next = wallHeightFtFromValue(els.ceilingHeight.value);
        if (next == null) {
          syncCeilingHeightInput(true);
          return;
        }
        setCeilingHeight(next, true);
        updateLiveArea();
      });
      els.ceilingHeight.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        var next = wallHeightFtFromValue(els.ceilingHeight.value);
        if (next == null) {
          syncCeilingHeightInput(true);
          return;
        }
        setCeilingHeight(next, true);
        updateLiveArea();
      });
    }
    if (els.floorWaste) {
      els.floorWaste.addEventListener('change', function () {
        setFlooringWaste(els.floorWaste.value, true);
        updateLiveArea();
      });
    }
    if (els.floorBox) {
      els.floorBox.addEventListener('change', function () {
        setFlooringBox(els.floorBox.value, true);
        updateLiveArea();
      });
    }
    raAll('[data-ra-floor-dir]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        setFlooringDirection(btn.getAttribute('data-ra-floor-dir'), true);
        updateLiveArea();
      });
    });
    if (els.exportPng) els.exportPng.addEventListener('click', exportPng);
    if (els.exportPdf) els.exportPdf.addEventListener('click', exportPdf);
    if (els.attachPdf) els.attachPdf.addEventListener('click', attachPdf);

    raAll('[data-ra-zoom-in]').forEach(function (btn) { btn.addEventListener('click', zoomIn); });
    raAll('[data-ra-zoom-out]').forEach(function (btn) { btn.addEventListener('click', zoomOut); });
    raAll('[data-ra-zoom-100]').forEach(function (btn) { btn.addEventListener('click', resetViewport); });
    raAll('[data-ra-zoom-reset]').forEach(function (btn) { btn.addEventListener('click', fitViewport); });
    bindSidePanel();
    bindHelpPops();
    bindFirstTip();
    if (els.roomName) {
      els.roomName.addEventListener('input', updateFullpageTitle);
    }

    raAll('[data-ra-measure-unit]').forEach(function (sel) {
      sel.addEventListener('change', function () {
        setMeasureUnit(sel.value);
      });
    });

    if (els.typeDirBtns) {
      els.typeDirBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
          setTypedTurn(btn.getAttribute('data-ra-type-dir'));
        });
      });
    }
    if (els.typeAdd) els.typeAdd.addEventListener('click', addTypedWall);
    if (els.typeLength) {
      els.typeLength.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          addTypedWall();
        }
      });
    }
    if (els.typeRect) els.typeRect.addEventListener('click', addTypedRectangle);
    if (els.typeRectL) {
      els.typeRectL.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          addTypedRectangle();
        }
      });
    }
    if (els.typeClose) els.typeClose.addEventListener('click', closeTypedRoom);
    if (els.typeAutoclose) els.typeAutoclose.addEventListener('click', autoCloseTyped);
    if (els.photosFile) {
      els.photosFile.addEventListener('change', function () {
        if (els.photosFile.files && els.photosFile.files.length) {
          uploadPhotos(els.photosFile.files);
          els.photosFile.value = '';
        }
      });
    }
    if (els.lightbox) {
      els.lightbox.querySelectorAll('[data-ra-lightbox-close]').forEach(function (btn) {
        btn.addEventListener('click', closeLightbox);
      });
    }
    if (els.lightboxCaption) {
      els.lightboxCaption.addEventListener('change', saveLightboxCaption);
      els.lightboxCaption.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          saveLightboxCaption();
        }
      });
    }

    window.addEventListener('resize', scheduleStageResize);

    function redrawForTheme() {
      drawGrid();
      redrawAll();
    }
    document.addEventListener('admin-theme-change', redrawForTheme);
    var lastHtmlDark = document.documentElement.classList.contains('admin-dark');
    if (window.MutationObserver) {
      new MutationObserver(function () {
        var dark = document.documentElement.classList.contains('admin-dark');
        if (dark === lastHtmlDark) return;
        lastHtmlDark = dark;
        redrawForTheme();
      }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    }

    window.addEventListener('beforeunload', function (e) {
      if (!hasUnsavedChanges()) return;
      e.preventDefault();
      e.returnValue = '';
    });
  }

  function updateFullpageTitle() {
    var titleEl = root.querySelector('[data-ra-full-title]');
    if (!titleEl || titleEl === els.roomName || titleEl.tagName === 'INPUT') return;
    var roomName = els.roomName ? String(els.roomName.value || '').trim() : '';
    var project = activeProject();
    if (roomName) {
      titleEl.textContent = roomName;
    } else if (project && project.name) {
      titleEl.textContent = project.name;
    } else {
      titleEl.textContent = 'Room Area';
    }
  }

  function setSidePane(pane) {
    if (root.classList.contains('is-side-collapsed')) {
      root.classList.remove('is-side-collapsed');
      var toggle = root.querySelector('[data-ra-side-toggle]');
      if (toggle) toggle.setAttribute('aria-expanded', 'true');
    }
    raAll('[data-ra-side-tab]').forEach(function (tab) {
      var on = tab.getAttribute('data-ra-side-tab') === pane;
      tab.classList.toggle('is-active', on);
      tab.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    raAll('[data-ra-side-pane]').forEach(function (el) {
      el.classList.toggle('is-active', el.getAttribute('data-ra-side-pane') === pane);
    });
    scheduleStageResize();
  }

  function closeHelpPops() {
    raAll('[data-ra-help-pop-panel]').forEach(function (panel) {
      panel.hidden = true;
    });
    raAll('[data-ra-help-pop]').forEach(function (btn) {
      btn.setAttribute('aria-expanded', 'false');
    });
  }

  function bindHelpPops() {
    raAll('[data-ra-help-pop]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var id = btn.getAttribute('data-ra-help-pop');
        var panel = root.querySelector('[data-ra-help-pop-panel="' + id + '"]');
        var wasOpen = panel && !panel.hidden;
        closeHelpPops();
        if (panel && !wasOpen) {
          panel.hidden = false;
          btn.setAttribute('aria-expanded', 'true');
        }
      });
    });
    document.addEventListener('pointerdown', function (e) {
      if (e.target.closest && e.target.closest('.util-ra-help')) return;
      closeHelpPops();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeHelpPops();
    });
  }

  function bindFirstTip() {
    if (!isFullPage()) return;
    var banner = root.querySelector('[data-ra-first-tip]');
    if (!banner) return;
    try {
      if (window.localStorage.getItem(FIRST_TIP_STORAGE_KEY) === '1') return;
    } catch (err) {}
    banner.hidden = false;
    var dismiss = banner.querySelector('[data-ra-first-tip-dismiss]');
    if (!dismiss) return;
    dismiss.addEventListener('click', function () {
      banner.hidden = true;
      try {
        window.localStorage.setItem(FIRST_TIP_STORAGE_KEY, '1');
      } catch (err2) {}
    });
  }

  function bindSidePanel() {
    var toggle = root.querySelector('[data-ra-side-toggle]');
    if (toggle) {
      toggle.addEventListener('click', function () {
        var collapsed = root.classList.toggle('is-side-collapsed');
        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        scheduleStageResize();
      });
    }
    raAll('[data-ra-side-tab]').forEach(function (tab) {
      tab.addEventListener('click', function () {
        setSidePane(tab.getAttribute('data-ra-side-tab'));
      });
    });
    bindSheetHandle();
  }

  function bindSheetHandle() {
    var handle = root.querySelector('[data-ra-sheet-handle]');
    var side = root.querySelector('[data-ra-side]');
    if (!handle || !side || !isFullPage()) return;
    var dragging = false;
    var startY = 0;
    var startH = 0;
    var pointerId = null;

    function eventY(e) {
      if (e.touches && e.touches[0]) return e.touches[0].clientY;
      if (e.changedTouches && e.changedTouches[0]) return e.changedTouches[0].clientY;
      return e.clientY;
    }

    function collapsedSheetMin() {
      var handleH = handle.getBoundingClientRect().height || 44;
      var strip = root.querySelector('[data-ra-sheet-strip]');
      var stripH = strip ? strip.getBoundingClientRect().height : 0;
      return Math.max(44, Math.round(handleH + stripH));
    }

    function clampSheet(height) {
      var minH = isPhoneChrome() ? collapsedSheetMin() : Math.round(Math.min(window.innerHeight * 0.22, 168));
      var maxH = Math.round(window.innerHeight * (isPhoneChrome() ? 0.7 : 0.55));
      return Math.max(minH, Math.min(maxH, Math.round(height)));
    }

    function afterSheetLayoutChange() {
      scheduleStageResize();
      requestAnimationFrame(function () {
        requestAnimationFrame(function () {
          resizeStage();
          if (viewportContentBounds().hasContent) fitViewport();
          redrawLabels();
        });
      });
    }

    function applySheet(height) {
      root.style.setProperty('--ra-sheet-h', clampSheet(height) + 'px');
      afterSheetLayoutChange();
    }

    var moved = false;

    function toggleSheet() {
      if (!isPhoneChrome()) return;
      var expanded = root.classList.toggle('is-sheet-expanded');
      handle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      root.style.setProperty('--ra-sheet-h', expanded ? 'min(52vh, 360px)' : 'auto');
      afterSheetLayoutChange();
    }

    var tapHandled = false;

    function onDown(e) {
      if (window.matchMedia('(min-width: 901px)').matches) return;
      dragging = true;
      moved = false;
      tapHandled = false;
      startY = eventY(e);
      startH = side.getBoundingClientRect().height;
      if (e.pointerId != null && handle.setPointerCapture) {
        pointerId = e.pointerId;
        try { handle.setPointerCapture(e.pointerId); } catch (err) {}
      }
      if (e.type !== 'touchstart') e.preventDefault();
    }

    function onMove(e) {
      if (!dragging) return;
      if (Math.abs(startY - eventY(e)) > 6) moved = true;
      if (!moved) return;
      var next = startH + (startY - eventY(e));
      applySheet(next);
      root.classList.toggle('is-sheet-expanded', clampSheet(next) > collapsedSheetMin() + 16);
      handle.setAttribute('aria-expanded', root.classList.contains('is-sheet-expanded') ? 'true' : 'false');
      e.preventDefault();
    }

    function onUp(e) {
      var wasDragging = dragging;
      dragging = false;
      pointerId = null;
      if (!wasDragging || moved) return;
      if (e && (e.type === 'touchend' || e.pointerType === 'touch')) {
        tapHandled = true;
        toggleSheet();
      }
    }

    handle.addEventListener('pointerdown', onDown);
    handle.addEventListener('pointermove', onMove);
    handle.addEventListener('pointerup', onUp);
    handle.addEventListener('pointercancel', onUp);
    handle.addEventListener('touchstart', onDown, { passive: true });
    handle.addEventListener('touchmove', onMove, { passive: false });
    handle.addEventListener('touchend', onUp);
    handle.addEventListener('click', function () {
      if (tapHandled) {
        tapHandled = false;
        return;
      }
      if (moved) return;
      toggleSheet();
    });
    handle.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        toggleSheet();
      }
    });
  }

  function init() {
    state.projects = parseJson('data-projects', []);
    applyEditorPrefs(defaultEditorPrefs(), true);
    raAll('[data-ra-measure-unit]').forEach(function (sel) { sel.value = state.imperialUnit || MEASURE_UNITS.feet_inch_frac; });
    updateMeasureUnitPlaceholders();
    bindEvents();
    bindViewportEvents();
    bindTemplatesPopoverFollow();
    renderObjectPalette();
    syncUnitSystemUi();
    updateLiveUnitLabels();
    if (els.dimensionsToggle) els.dimensionsToggle.checked = state.showDimensions;
    refreshProjectSelect();
    var queryParams = (function () {
      try { return new URLSearchParams(window.location.search); } catch (e) { return null; }
    })();
    var raProject = queryParams ? queryParams.get('ra_project') : null;
    var raRoom = queryParams ? queryParams.get('ra_room') : null;
    var initialRoom = parseInt(root.getAttribute('data-ra-initial-room-id') || '', 10);
    if (!initialRoom && raRoom) initialRoom = parseInt(raRoom, 10);
    var lockedClient = root.hasAttribute('data-ra-lock-client-project');
    if (state.projects.length) {
      var chosen = null;
      if (initialRoom) {
        chosen = state.projects.find(function (p) {
          return (p.rooms || []).some(function (r) { return r.id === initialRoom; });
        }) || null;
      }
      if (!chosen && raProject) {
        var raId = parseInt(raProject, 10);
        chosen = state.projects.find(function (p) { return p.id === raId; }) || null;
      }
      if (chosen) {
        state.activeProjectId = chosen.id;
        refreshProjectSelect();
      } else if (isFullPage() && lockedClient) {
        state.activeProjectId = state.projects[0].id;
        refreshProjectSelect();
      }
    }
    if (isPhoneChrome()) {
      root.style.setProperty('--ra-sheet-h', 'auto');
    }
    placeObjectDock();
    window.addEventListener('resize', function () {
      placeObjectDock();
      syncPhoneSheetForDock();
      syncPhoneBarOverflow();
    });
    syncPhoneBarOverflow();
    renderWorkspace();
    updateFullpageTitle();
    if (initialRoom) {
      loadRoom(initialRoom);
    }
    syncGuideLinks();
  }

  init();
})();
