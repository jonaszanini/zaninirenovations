/* Room Area shared math — parser, units, templates, alcove.
 * Loaded in the browser before admin-utilities-room-area.js and required by
 * Node CI tests (tests/js/room-area-lib.test.js). Keep PHP
 * RoomAreaMeasurementParser / RoomAreaTemplates in sync with this file.
 */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory();
  } else {
    root.RoomAreaLib = factory();
  }
}(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  var INCHES_PER_FOOT = 12;
  var FEET_TO_METERS = 0.3048;
  var SQFT_TO_SQM = 0.092903;
  var MIN_LENGTH_FT = 1 / 192; // 1/16 in
  var MAX_LENGTH_FT = 1000;
  var MIN_WALL_HEIGHT_FT = 1;
  var MAX_WALL_HEIGHT_FT = 20;
  var DEFAULT_WALL_HEIGHT_FT = 8;
  var DEFAULT_DOOR_HEIGHT_IN = 80;
  var DEFAULT_WINDOW_HEIGHT_IN = 36;
  var DEFAULT_WASTE_PERCENT = 10;
  var DEFAULT_BOX_SQFT = 20;
  var MIN_BOX_SQFT = 0.25;
  var MAX_BOX_SQFT = 500;
  var DIRECTION_LENGTH = 0;
  var DIRECTION_WIDTH = 90;
  var L_RETURN_W = 6;
  var L_RETURN_H = 4;

  var UNITS = {
    meter: 'meter',
    inch: 'inch',
    feet_decimal: 'feet_decimal',
    feet_inch_frac: 'feet_inch_frac'
  };

  var VULGAR = {
    '½': '1/2',
    '¼': '1/4',
    '¾': '3/4',
    '⅓': '1/3',
    '⅔': '2/3',
    '⅛': '1/8',
    '⅜': '3/8',
    '⅝': '5/8',
    '⅞': '7/8'
  };

  function ParseError(message) {
    var err = new Error(message);
    err.name = 'RoomAreaParseError';
    return err;
  }

  function replaceVulgar(s) {
    return s.replace(/[½¼¾⅓⅔⅛⅜⅝⅞]/g, function (ch, offset, whole) {
      var prev = offset > 0 ? whole.charAt(offset - 1) : '';
      var repl = VULGAR[ch] || ch;
      if (prev && /[\d]/.test(prev)) return ' ' + repl;
      return repl;
    });
  }

  function normalizeRaw(raw) {
    var s = String(raw == null ? '' : raw).replace(/[\u00A0\t]/g, ' ');
    s = s.replace(/[\u2018\u2019\u201B]/g, "'");
    s = s.replace(/[\u201C\u201D\u201F]/g, '"');
    s = replaceVulgar(s);
    s = s.replace(/\s+/g, ' ').trim();
    return s;
  }

  function toFloat(token) {
    var s = String(token).trim();
    if (!s) throw ParseError('Enter a valid length greater than zero.');
    if (/[eE]/.test(s)) throw ParseError('Enter a valid length greater than zero.');
    if (s.indexOf('.') !== -1) {
      s = s.replace(/,/g, '');
    } else if (s.indexOf(',') !== -1) {
      var last = s.lastIndexOf(',');
      s = s.slice(0, last).replace(/,/g, '') + '.' + s.slice(last + 1);
    }
    if (!/^[+-]?\d+(\.\d+)?$/.test(s)) {
      throw ParseError('Use 12\' 6", 12.5 ft, or 150" (inches).');
    }
    var n = Number(s);
    if (!isFinite(n)) throw ParseError('Enter a valid length greater than zero.');
    return n;
  }

  /**
   * Treat '.' as a decimal unless the token is clearly thousands:
   * comma groups (3,810), two-or-more dot groups (1.500.000), or
   * European mixed (3.810,5). So 1.500 mm and 10.000 mm stay decimals.
   */
  function toFloatMm(token) {
    var s = String(token).trim();
    if (/^\d{1,3}(?:\.\d{3})+,\d+$/.test(s)) {
      s = s.replace(/\./g, '').replace(',', '.');
    } else if (/^[1-9]\d{0,2}(?:,\d{3})+$/.test(s)) {
      s = s.replace(/,/g, '');
    } else if (/^[1-9]\d{0,2}(?:\.\d{3}){2,}$/.test(s)) {
      s = s.replace(/\./g, '');
    }
    return toFloat(s);
  }

  function describeParsedLength(feet) {
    var meters = feet * FEET_TO_METERS;
    var mm = meters * 1000;
    if (mm < 1000) {
      return (Math.round(mm * 1000) / 1000) + ' mm (' + (Math.round(feet * 10000) / 10000) + ' ft)';
    }
    return (Math.round(meters * 1000) / 1000) + ' m (' + (Math.round(feet * 100) / 100) + ' ft)';
  }

  function parseFraction(num, den) {
    var d = toFloat(den);
    if (d === 0) throw ParseError('Enter a valid length greater than zero.');
    return toFloat(num) / d;
  }

  function mixedInches(whole, num, den) {
    return toFloat(whole) + parseFraction(num, den);
  }

  function positiveFeet(n) {
    if (!isFinite(n) || n <= 0) {
      throw ParseError('Enter a valid length greater than zero.');
    }
    if (n < MIN_LENGTH_FT || n > MAX_LENGTH_FT) {
      throw ParseError('Enter a length between 1/16 in and 1000 ft.');
    }
    return n;
  }

  function remainderAfter(s, pattern) {
    return s.replace(pattern, '').trim();
  }

  function assertNoJunk(s) {
    if (s) throw ParseError('Use 12\' 6", 12.5 ft, or 150" (inches).');
  }

  function detectExplicitUnit(raw) {
    var s = normalizeRaw(raw);
    if (!s) return null;
    if (/mm|millimet/i.test(s)) return 'mm';
    if (/cm/i.test(s)) return UNITS.meter;
    if (/(?:^|[^\w])m(?:eters?|etres?)?(?:$|[^\w])/i.test(s) || /\d\s*m(?:eters?|etres?)?(?:\s|$)/i.test(s)) {
      return UNITS.meter;
    }
    if (/['′]|ft|feet|foot/i.test(s)) return UNITS.feet_inch_frac;
    if (/["″]|in(?:ch(?:es)?)?/i.test(s)) return UNITS.inch;
    return null;
  }

  function parseCombinedMetric(s) {
    var m = s.match(/^([\d.,]+)\s*m(?:eters?|etres?)?\s+([\d.,]+)\s*cm(?:s)?$/i);
    if (m) return positiveFeet((toFloat(m[1]) + toFloat(m[2]) / 100) / FEET_TO_METERS);
    m = s.match(/^([\d.,]+)\s*m(?:eters?|etres?)?\s+([\d.,]+)\s*mm(?:s)?$/i);
    if (m) return positiveFeet((toFloat(m[1]) + toFloatMm(m[2]) / 1000) / FEET_TO_METERS);
    m = s.match(/^([\d.,]+)\s*cm(?:s)?\s+([\d.,]+)\s*mm(?:s)?$/i);
    if (m) return positiveFeet((toFloat(m[1]) / 100 + toFloatMm(m[2]) / 1000) / FEET_TO_METERS);
    return null;
  }

  function parseMillimeters(raw) {
    var s = normalizeRaw(raw);
    var combo = parseCombinedMetric(s);
    if (combo != null) return combo;
    var m = s.match(/^([\d.,]+)\s*(mm|millimet(?:er|re)s?)$/i);
    if (!m) throw ParseError('Enter a length in millimetres.');
    assertNoJunk(remainderAfter(s, m[0]));
    return positiveFeet((toFloatMm(m[1]) / 1000) / FEET_TO_METERS);
  }

  function parseMeters(raw) {
    var s = normalizeRaw(raw);
    var combo = parseCombinedMetric(s);
    if (combo != null) return combo;
    var mm = s.match(/^([\d.,]+)\s*mm(?:s)?$/i);
    if (mm) return parseMillimeters(s);
    var cm = s.match(/^([\d.,]+)\s*cm(?:s)?$/i);
    if (cm) {
      assertNoJunk(remainderAfter(s, cm[0]));
      return positiveFeet((toFloat(cm[1]) / 100) / FEET_TO_METERS);
    }
    var m = s.match(/^([\d.,]+)\s*m(?:eters?|etres?)?$/i);
    if (m) {
      assertNoJunk(remainderAfter(s, m[0]));
      return positiveFeet(toFloat(m[1]) / FEET_TO_METERS);
    }
    if (/^[\d.,]+$/.test(s)) {
      return positiveFeet(toFloat(s) / FEET_TO_METERS);
    }
    throw ParseError('Enter a length in meters.');
  }

  function parseInchesOnly(raw) {
    var s = normalizeRaw(raw);
    s = s.replace(/["″]\s*$/, '').replace(/\s*in(?:ch(?:es)?)?\s*$/i, '').trim();
    if (!s) throw ParseError('Enter a length in inches.');
    var mixed = s.match(/^([\d.,]+)\s+(\d+)\s*\/\s*(\d+)$/);
    if (mixed) {
      assertNoJunk('');
      return positiveFeet(mixedInches(mixed[1], mixed[2], mixed[3]) / INCHES_PER_FOOT);
    }
    var frac = s.match(/^(\d+)\s*\/\s*(\d+)$/);
    if (frac) return positiveFeet(parseFraction(frac[1], frac[2]) / INCHES_PER_FOOT);
    if (!/^[\d.,]+$/.test(s)) throw ParseError('Enter a length in inches.');
    return positiveFeet(toFloat(s) / INCHES_PER_FOOT);
  }

  function parseFeetDecimal(raw) {
    var s = normalizeRaw(raw).replace(/\s*(ft|feet|foot)\s*$/i, '').trim();
    if (!s) throw ParseError('Enter a length in feet.');
    if (!/^[\d.,]+$/.test(s)) throw ParseError('Enter a length in feet.');
    return positiveFeet(toFloat(s));
  }

  function parseFeetInchFrac(raw) {
    var s = normalizeRaw(raw);
    if (!s) throw ParseError('Enter a length (e.g. 12\' 6", 12.5, or 150").');

    s = s.replace(/feet|foot/gi, "'");
    s = s.replace(/ft/gi, "'");
    s = s.replace(/inches|inch/gi, '"');
    s = s.replace(/in/gi, '"');
    s = s.replace(/\s+/g, ' ').trim();

    var m;
    if ((m = s.match(/^([\d.,]+)\s*['′]\s*([\d.,]+)\s+(\d+)\s*\/\s*(\d+)\s*["″]?$/))) {
      return positiveFeet(toFloat(m[1]) + mixedInches(m[2], m[3], m[4]) / INCHES_PER_FOOT);
    }
    if ((m = s.match(/^([\d.,]+)\s*['′]\s*(\d+)\s*\/\s*(\d+)\s*["″]?$/))) {
      return positiveFeet(toFloat(m[1]) + parseFraction(m[2], m[3]) / INCHES_PER_FOOT);
    }
    if ((m = s.match(/^([\d.,]+)\s*['′]\s*([\d.,]+)\s*["″]?$/))) {
      return positiveFeet(toFloat(m[1]) + toFloat(m[2]) / INCHES_PER_FOOT);
    }
    if ((m = s.match(/^([\d.,]+)\s*-\s*([\d.,]+)$/))) {
      return positiveFeet(toFloat(m[1]) + toFloat(m[2]) / INCHES_PER_FOOT);
    }
    if ((m = s.match(/^([\d.,]+)\s+([\d.,]+)\s+(\d+)\s*\/\s*(\d+)$/))) {
      return positiveFeet(toFloat(m[1]) + mixedInches(m[2], m[3], m[4]) / INCHES_PER_FOOT);
    }
    if ((m = s.match(/^([\d.,]+)\s+(\d+)\s*\/\s*(\d+)$/))) {
      return positiveFeet(toFloat(m[1]) + parseFraction(m[2], m[3]) / INCHES_PER_FOOT);
    }
    if ((m = s.match(/^([\d.,]+)\s+([\d.,]+)$/))) {
      return positiveFeet(toFloat(m[1]) + toFloat(m[2]) / INCHES_PER_FOOT);
    }
    if ((m = s.match(/^([\d.,]+)\s*['′]\s*$/))) {
      return positiveFeet(toFloat(m[1]));
    }
    if ((m = s.match(/^([\d.,]+)\s*["″]\s*$/))) {
      return positiveFeet(toFloat(m[1]) / INCHES_PER_FOOT);
    }
    if ((m = s.match(/^(\d+)\s*\/\s*(\d+)\s*["″]?$/))) {
      return positiveFeet(parseFraction(m[1], m[2]) / INCHES_PER_FOOT);
    }
    if (/^[\d.,]+$/.test(s)) {
      var n = toFloat(s);
      if (s.indexOf('.') !== -1 || s.indexOf(',') !== -1) return positiveFeet(n);
      return positiveFeet(n / INCHES_PER_FOOT);
    }

    throw ParseError('Use 12\' 6", 12.5 ft, or 150" (inches).');
  }

  function normalizeUnit(unit) {
    var u = String(unit || '').toLowerCase().trim();
    if (u === 'm' || u === 'meter' || u === 'meters' || u === 'metre' || u === 'metres' || u === 'metric') {
      return UNITS.meter;
    }
    if (u === 'in' || u === 'inch' || u === 'inches') return UNITS.inch;
    if (u === 'ft' || u === 'feet' || u === 'foot' || u === 'feet_decimal' || u === 'decimal') {
      return UNITS.feet_decimal;
    }
    if (u === 'mm') return 'mm';
    return UNITS.feet_inch_frac;
  }

  function parseLengthInput(raw, unitMode) {
    var detected = detectExplicitUnit(raw);
    var unit = detected || normalizeUnit(unitMode);
    if (unit === 'mm') return parseMillimeters(raw);
    if (unit === UNITS.meter) return parseMeters(raw);
    if (unit === UNITS.inch) return parseInchesOnly(raw);
    if (unit === UNITS.feet_decimal) return parseFeetDecimal(raw);
    return parseFeetInchFrac(raw);
  }

  function clampWallHeightFt(feet) {
    if (!isFinite(feet) || feet < MIN_WALL_HEIGHT_FT) return DEFAULT_WALL_HEIGHT_FT;
    if (feet > MAX_WALL_HEIGHT_FT) return MAX_WALL_HEIGHT_FT;
    return Math.round(feet * 100) / 100;
  }

  function parseWallHeight(raw, metric) {
    var s = String(raw == null ? '' : raw).trim();
    if (!s) {
      return { feet: DEFAULT_WALL_HEIGHT_FT, warning: null };
    }
    try {
      var feet = parseLengthInput(s, metric ? UNITS.meter : UNITS.feet_decimal);
      if (!isFinite(feet) || feet < MIN_WALL_HEIGHT_FT || feet > MAX_WALL_HEIGHT_FT) {
        return {
          feet: null,
          warning: 'Wall height must be between 1 ft and 20 ft (0.30–6.10 m).'
        };
      }
      return { feet: feet, warning: null };
    } catch (e) {
      return { feet: null, warning: (e && e.message) || 'Enter a valid wall height.' };
    }
  }

  function wallHeightDisplay(feet, metric, fmtFn) {
    var n = clampWallHeightFt(feet);
    if (metric) {
      var meters = n * FEET_TO_METERS;
      if (fmtFn) return fmtFn(meters, 2);
      return String(Math.round(meters * 100) / 100);
    }
    return String(n);
  }

  function positiveDim(n) {
    return n > 0 ? n : 1;
  }

  function rectangle(width, length) {
    var w = positiveDim(width);
    var l = positiveDim(length);
    return [
      { x: 0, y: 0 },
      { x: w, y: 0 },
      { x: w, y: l },
      { x: 0, y: l }
    ];
  }

  function lShape(outerW, outerH, returnW, returnH) {
    var w = positiveDim(outerW);
    var h = positiveDim(outerH);
    var rw = positiveDim(returnW);
    var rh = positiveDim(returnH);
    if (rw >= w || rh >= h) return rectangle(w, h);
    return [
      { x: 0, y: 0 },
      { x: w, y: 0 },
      { x: w, y: h },
      { x: w - rw, y: h },
      { x: w - rw, y: h - rh },
      { x: 0, y: h - rh }
    ];
  }

  function uShape(outerW, outerH, openingW, openingD) {
    var w = positiveDim(outerW);
    var h = positiveDim(outerH);
    var ow = positiveDim(openingW);
    var od = positiveDim(openingD);
    if (ow >= w || od >= h) return rectangle(w, h);
    var arm = (w - ow) / 2;
    return [
      { x: 0, y: 0 },
      { x: w, y: 0 },
      { x: w, y: h },
      { x: w - arm, y: h },
      { x: w - arm, y: h - od },
      { x: arm, y: h - od },
      { x: arm, y: h },
      { x: 0, y: h }
    ];
  }

  function tShape(barW, barH, stemW, stemH) {
    var bw = positiveDim(barW);
    var bh = positiveDim(barH);
    var sw = positiveDim(stemW);
    var sh = positiveDim(stemH);
    if (sw >= bw) return rectangle(bw, bh + sh);
    var left = (bw - sw) / 2;
    return [
      { x: 0, y: 0 },
      { x: bw, y: 0 },
      { x: bw, y: bh },
      { x: left + sw, y: bh },
      { x: left + sw, y: bh + sh },
      { x: left, y: bh + sh },
      { x: left, y: bh },
      { x: 0, y: bh }
    ];
  }

  function lFromOverall(width, length) {
    var w = positiveDim(width);
    var h = positiveDim(length);
    var rw = L_RETURN_W;
    var rh = L_RETURN_H;
    if (rw >= w) rw = w / 3;
    if (rh >= h) rh = h / 3;
    return lShape(w, h, rw, rh);
  }

  function tFromOverall(width, length) {
    var w = positiveDim(width);
    var h = positiveDim(length);
    var barH = Math.min(4, h / 2);
    var stemH = h - barH;
    if (stemH < 1) {
      barH = h / 2;
      stemH = h / 2;
    }
    var stemW = Math.min(6, w / 2);
    return tShape(w, barH, stemW, stemH);
  }

  function uFromOverall(width, length) {
    var w = positiveDim(width);
    var h = positiveDim(length);
    return uShape(w, h, Math.min(8, w / 2), Math.min(8, h * 0.65));
  }

  function shapeFromKind(kind, width, length) {
    var k = String(kind || 'rect').toLowerCase();
    if (k === 'l') return lFromOverall(width || 16, length || 12);
    if (k === 'u') return uFromOverall(width || 16, length || 12);
    if (k === 't') return tFromOverall(width || 16, length || 12);
    return rectangle(width || 12, length || 10);
  }

  function isAxisAlignedRectangle(verts) {
    if (!verts || verts.length !== 4) return false;
    var area = Math.abs(polygonArea(verts));
    var minX = Infinity;
    var minY = Infinity;
    var maxX = -Infinity;
    var maxY = -Infinity;
    var i;
    for (i = 0; i < verts.length; i++) {
      if (verts[i].x < minX) minX = verts[i].x;
      if (verts[i].y < minY) minY = verts[i].y;
      if (verts[i].x > maxX) maxX = verts[i].x;
      if (verts[i].y > maxY) maxY = verts[i].y;
    }
    var aabb = (maxX - minX) * (maxY - minY);
    return aabb > 0 && Math.abs(area - aabb) <= 1e-6;
  }

  function overallDimensionText(verts, opts) {
    opts = opts || {};
    var minX = Infinity;
    var minY = Infinity;
    var maxX = -Infinity;
    var maxY = -Infinity;
    (verts || []).forEach(function (v) {
      if (v.x < minX) minX = v.x;
      if (v.y < minY) minY = v.y;
      if (v.x > maxX) maxX = v.x;
      if (v.y > maxY) maxY = v.y;
    });
    var boxW = opts.width != null ? opts.width : (maxX - minX);
    var boxH = opts.height != null ? opts.height : (maxY - minY);
    var wLabel = opts.formatLength ? opts.formatLength(boxW) : boxW;
    var hLabel = opts.formatLength ? opts.formatLength(boxH) : boxH;
    if (opts.areaOnly) return opts.areaLabel || '';
    if (isAxisAlignedRectangle(verts)) {
      return opts.areaLabel ? (wLabel + ' × ' + hLabel + ' · ' + opts.areaLabel) : (wLabel + ' × ' + hLabel);
    }
    return 'Overall ' + wLabel + ' × ' + hLabel;
  }

  function readFetchJson(res, fallbackMessage) {
    if (!res || typeof res.json !== 'function') {
      return Promise.resolve({
        ok: false,
        status: res && res.status != null ? res.status : 0,
        data: { message: fallbackMessage || 'Could not read server response.' }
      });
    }
    return Promise.resolve(res.json()).then(function (data) {
      return { ok: !!res.ok, status: res.status, data: data || {} };
    }).catch(function () {
      var message = fallbackMessage || 'Could not read server response.';
      if (res && res.status === 413) message = 'That file is too large to upload.';
      return { ok: false, status: res.status, data: { message: message } };
    });
  }

  function planUploadReason(err) {
    var raw = err && err.message ? String(err.message).trim() : '';
    var status = Number(err && err.status);
    if (status === 413 || /413/.test(raw) || /payload too large/i.test(raw) || /too large to upload/i.test(raw)) {
      return raw && !/payload too large/i.test(raw) && !/^413/.test(raw) ? raw : 'That file is too large.';
    }
    if (status === 422 && raw) {
      return raw;
    }
    if (/wrong type|must be an image|not a valid|unsupported|invalid file/i.test(raw)) {
      return raw;
    }
    if (status >= 500 || /server error/i.test(raw)) {
      return 'The server had a problem.';
    }
    if (!raw || /failed to fetch/i.test(raw) || /networkerror/i.test(raw)
      || /unexpected token/i.test(raw) || /not valid json/i.test(raw)
      || /could not upload/i.test(raw) || /could not read/i.test(raw)
      || /check your connection/i.test(raw)) {
      return 'Check your connection and try again.';
    }
    return raw;
  }

  function isWrongFileTypeError(err) {
    var raw = err && err.message ? String(err.message) : '';
    var status = Number(err && err.status);
    if (status === 413 || /too large|payload too large|unlock layers/i.test(raw)) return false;
    if (/must be an image|wrong (file )?type|mimes|jpeg|png|webp|unsupported|not a valid/i.test(raw)
      && !/too large|10240|10 ?mb|kilobytes/i.test(raw)) {
      return true;
    }
    return false;
  }

  function planUploadErrorAfterRoomSaved(err) {
    var reason = planUploadReason(err);
    if (isWrongFileTypeError(err)) {
      return reason || 'Wrong file type. Use a JPEG, PNG, or WebP image.';
    }
    var base = 'Floor plan upload failed. The room itself was saved. Save again to retry.';
    return reason ? base + ' ' + reason : base;
  }

  function friendlyNetworkError(err, fallbackMessage) {
    var raw = err && err.message ? String(err.message) : '';
    if (err && (err.status === 413 || /413/.test(raw) || /payload too large/i.test(raw))) {
      return 'That file is too large to upload.';
    }
    if (err && (Number(err.status) >= 500 || /server error/i.test(raw))) {
      return fallbackMessage || 'The server had a problem. Try again.';
    }
    if (!raw || /failed to fetch/i.test(raw) || /networkerror/i.test(raw)
      || /unexpected token/i.test(raw) || /not valid json/i.test(raw)
      || /json is not a function/i.test(raw) || /is not a function/i.test(raw)) {
      return fallbackMessage || 'Check your connection and try again.';
    }
    return raw;
  }

  function friendlyPlanUploadError(err) {
    var raw = err && err.message ? String(err.message).trim() : '';
    var status = Number(err && err.status);
    if (status === 413 || /413/.test(raw) || /payload too large/i.test(raw) || /too large to upload/i.test(raw)) {
      return raw && /too large/i.test(raw) && !/payload too large/i.test(raw)
        ? raw
        : 'That file is too large to upload.';
    }
    if (status === 422 && raw) {
      return raw;
    }
    if (isWrongFileTypeError(err)) {
      return raw || 'Wrong file type. Use a JPEG, PNG, or WebP image.';
    }
    if (status >= 500 || /server error/i.test(raw)) {
      return 'Could not upload the floor plan. The server had a problem. Try again.';
    }
    return friendlyNetworkError(err, 'Could not upload the floor plan. Check your connection and try again.');
  }

  function ceilingHeightFt(ceiling, wall) {
    var wallFt = clampWallHeightFt(wall);
    if (ceiling == null || ceiling === '' || !isFinite(Number(ceiling))) return wallFt;
    return clampWallHeightFt(ceiling);
  }

  function ceilingAreaSqFt(floorArea) {
    var n = Number(floorArea);
    return Math.round(Math.max(0, isFinite(n) ? n : 0) * 100) / 100;
  }

  function parseWastePercent(percent) {
    if (percent == null) {
      return { ok: true, value: DEFAULT_WASTE_PERCENT, error: null };
    }
    if (typeof percent === 'number') {
      if (!isFinite(percent)) {
        return { ok: false, value: DEFAULT_WASTE_PERCENT, error: 'Enter waste as a percent, like 10 or 15%.' };
      }
      if (percent < 0) {
        return { ok: false, value: DEFAULT_WASTE_PERCENT, error: 'Waste must be between 0 and 100%.' };
      }
      return { ok: true, value: Math.round(Math.min(100, percent) * 100) / 100, error: null };
    }
    var raw = String(percent).trim();
    if (!raw) return { ok: true, value: DEFAULT_WASTE_PERCENT, error: null };
    raw = raw.replace(/\s+/g, '').replace(/%/g, '').replace(',', '.');
    if (!raw || !/^[+-]?\d+(\.\d+)?$/.test(raw)) {
      return { ok: false, value: DEFAULT_WASTE_PERCENT, error: 'Enter waste as a percent, like 10 or 15%.' };
    }
    var n = Number(raw);
    if (!isFinite(n) || n < 0) {
      return { ok: false, value: DEFAULT_WASTE_PERCENT, error: 'Waste must be between 0 and 100%.' };
    }
    return { ok: true, value: Math.round(Math.min(100, n) * 100) / 100, error: null };
  }

  function normalizeWastePercent(percent) {
    return parseWastePercent(percent).value;
  }

  function normalizeBoxSqFt(box) {
    if (box == null || box === '' || !isFinite(Number(box))) return DEFAULT_BOX_SQFT;
    var n = Number(box);
    if (n < MIN_BOX_SQFT) return 0;
    if (n > MAX_BOX_SQFT) return MAX_BOX_SQFT;
    return Math.round(n * 100) / 100;
  }

  function normalizeDirection(deg) {
    var n = parseInt(deg, 10);
    if (!isFinite(n)) return DIRECTION_LENGTH;
    return Math.abs(n) % 180 >= 45 ? DIRECTION_WIDTH : DIRECTION_LENGTH;
  }

  function openingAreaSqFt(widthIn, heightIn) {
    var w = Number(widthIn);
    var h = Number(heightIn);
    if (!isFinite(w) || !isFinite(h) || w <= 0 || h <= 0) return 0;
    return (w / INCHES_PER_FOOT) * (h / INCHES_PER_FOOT);
  }

  function netWallAreaSqFt(lengthFt, heightFt, openingArea) {
    var length = Number(lengthFt) || 0;
    var height = Number(heightFt) || 0;
    var deduct = Number(openingArea) || 0;
    return Math.round(Math.max(0, length * height - Math.max(0, deduct)) * 100) / 100;
  }

  function flooringBoxes(floorArea, wastePercent, boxSqFt) {
    var area = Math.max(0, Number(floorArea) || 0);
    var box = normalizeBoxSqFt(boxSqFt);
    if (area <= 0 || box < MIN_BOX_SQFT) return 0;
    var needed = area * (1 + (normalizeWastePercent(wastePercent) / 100));
    return Math.ceil(needed / box);
  }

  function objectType(obj) {
    return String(obj && obj.type != null ? obj.type : obj || '').toLowerCase().trim();
  }

  function normalizeDoorFace(face) {
    return String(face || '').toLowerCase().trim() === 'exterior' ? 'exterior' : 'interior';
  }

  function casingLf(widthIn, heightIn, sides, type) {
    var w = Number(widthIn);
    var h = Number(heightIn);
    var kind = objectType(type || 'door');
    var n = parseInt(sides, 10);
    if (!isFinite(n)) n = defaultCasingSides(kind);
    if (!isFinite(w) || !isFinite(h) || w <= 0 || h <= 0) return 0;
    if (kind === 'window') {
      if (n >= 4) return Math.round((2 * w + 2 * h) / INCHES_PER_FOOT * 100) / 100;
      if (n === 3) return Math.round((w + (2 * h)) / INCHES_PER_FOOT * 100) / 100;
      return 0;
    }
    n = Math.max(0, Math.min(2, n));
    if (n <= 0) return 0;
    return Math.round(n * (w + (2 * h)) / INCHES_PER_FOOT * 100) / 100;
  }

  function defaultCasingSides(type, doorFace) {
    type = objectType(type);
    if (type === 'door') return normalizeDoorFace(doorFace) === 'exterior' ? 1 : 2;
    if (type === 'window') return 4;
    if (type === 'opening') return 2;
    return 0;
  }

  function casingSidesForObject(obj) {
    var type = objectType(obj);
    var raw = obj && obj.casing_sides;
    if (raw == null || String(raw).trim() === '' || !isFinite(Number(raw))) {
      return defaultCasingSides(type, obj && obj.door_face);
    }
    var n = parseInt(raw, 10);
    if (!isFinite(n)) return defaultCasingSides(type, obj && obj.door_face);
    var max = type === 'window' ? 4 : 2;
    return Math.max(0, Math.min(max, n));
  }

  function objectHeightInches(obj, wallHeightFt) {
    var type = objectType(obj);
    var ceilingIn = clampWallHeightFt(wallHeightFt) * INCHES_PER_FOOT;
    var height;
    if (obj && obj.height != null && isFinite(obj.height) && obj.height > 0) {
      height = Number(obj.height);
    } else if (type === 'window') {
      height = DEFAULT_WINDOW_HEIGHT_IN;
    } else if (type === 'opening') {
      height = ceilingIn;
    } else {
      height = DEFAULT_DOOR_HEIGHT_IN;
    }
    if (ceilingIn > 0) height = Math.min(height, ceilingIn);
    return Math.max(0, height);
  }

  function openingRect(obj, ceilingHeightFt) {
    var type = objectType(obj);
    var width = Number(obj && obj.width) || 0;
    var height = objectHeightInches(obj, ceilingHeightFt);
    if (width <= 0 || height <= 0) return null;
    var offset = Math.max(0, Number(obj && obj.offset) || 0);
    var sill = 0;
    if (type === 'window' && obj && obj.sill_height != null && isFinite(obj.sill_height)) {
      sill = Math.max(0, Number(obj.sill_height));
    }
    var ceilingIn = clampWallHeightFt(ceilingHeightFt) * INCHES_PER_FOOT;
    var y0 = sill;
    var y1 = sill + height;
    if (ceilingIn > 0) {
      y0 = Math.min(y0, ceilingIn);
      y1 = Math.min(y1, ceilingIn);
    }
    if (y1 <= y0) return null;
    return { x0: offset, x1: offset + width, y0: y0, y1: y1 };
  }

  function unionRectAreaInches(rects) {
    if (!rects || !rects.length) return 0;
    var xs = [];
    rects.forEach(function (r) {
      xs.push(r.x0, r.x1);
    });
    xs = xs.filter(function (v, i, all) { return all.indexOf(v) === i; }).sort(function (a, b) { return a - b; });
    var area = 0;
    for (var i = 0; i < xs.length - 1; i++) {
      var x0 = xs[i];
      var x1 = xs[i + 1];
      var dx = x1 - x0;
      if (dx <= 0) continue;
      var spans = [];
      rects.forEach(function (r) {
        if (r.x0 < x1 && r.x1 > x0) spans.push([r.y0, r.y1]);
      });
      if (!spans.length) continue;
      spans.sort(function (a, b) { return a[0] - b[0]; });
      var cur0 = spans[0][0];
      var cur1 = spans[0][1];
      var cover = 0;
      for (var j = 1; j < spans.length; j++) {
        if (spans[j][0] <= cur1) {
          cur1 = Math.max(cur1, spans[j][1]);
        } else {
          cover += cur1 - cur0;
          cur0 = spans[j][0];
          cur1 = spans[j][1];
        }
      }
      cover += cur1 - cur0;
      area += dx * cover;
    }
    return area;
  }

  function unionOpeningAreaSqFt(objects, ceilingHeightFt) {
    var rects = [];
    (objects || []).forEach(function (obj) {
      var type = objectType(obj);
      if (type !== 'door' && type !== 'window' && type !== 'opening') return;
      var rect = openingRect(obj, ceilingHeightFt);
      if (rect) rects.push(rect);
    });
    return Math.round(unionRectAreaInches(rects) / 144 * 100) / 100;
  }

  function wallId(polyId, start, end) {
    return 'wall_' + polyId + '_' + start + '_' + end;
  }

  function perWallNetAreas(canvas, pixelsPerFoot, ceilingHeightFt) {
    var ppf = Number(pixelsPerFoot) || 0;
    var height = clampWallHeightFt(ceilingHeightFt);
    if (ppf <= 0) return [];
    var objectsByWall = {};
    ((canvas && canvas.objects) || []).forEach(function (obj) {
      if (!obj) return;
      var type = objectType(obj);
      if (type !== 'door' && type !== 'window' && type !== 'opening') return;
      var id = obj.wall_id ? String(obj.wall_id) : '';
      if (!id) return;
      if (!objectsByWall[id]) objectsByWall[id] = [];
      objectsByWall[id].push(obj);
    });
    var walls = [];
    var index = 0;
    ((canvas && canvas.groups) || []).forEach(function (group) {
      if (!group) return;
      (group.polygons || []).forEach(function (polygon) {
        if (!polygon) return;
        var verts = polygon.vertices || [];
        if (verts.length < 3) return;
        var polyId = polygon.id || 'p';
        for (var i = 0; i < verts.length; i++) {
          var j = (i + 1) % verts.length;
          var a = verts[i];
          var b = verts[j];
          if (!a || !b || a.x == null || b.x == null) continue;
          var id = wallId(polyId, i, j);
          var lengthFt = Math.hypot(b.x - a.x, b.y - a.y) / ppf;
          var deduct = unionOpeningAreaSqFt(objectsByWall[id] || [], height);
          index += 1;
          walls.push({
            id: id,
            index: index,
            label: 'W' + index,
            length_lf: Math.round(lengthFt * 100) / 100,
            opening_area_sqft: deduct,
            net_area_sqft: netWallAreaSqFt(lengthFt, height, deduct)
          });
        }
      });
    });
    return walls;
  }

  function totalCasingLf(objects, wallHeightFt) {
    var total = 0;
    (objects || []).forEach(function (obj) {
      var type = objectType(obj);
      if (type !== 'door' && type !== 'window' && type !== 'opening') return;
      var width = Number(obj.width) || 0;
      if (width <= 0) return;
      total += casingLf(width, objectHeightInches(obj, wallHeightFt), casingSidesForObject(obj), type);
    });
    return Math.round(total * 100) / 100;
  }

  function signedArea2(verts) {
    var s = 0;
    var n = verts.length;
    for (var i = 0; i < n; i++) {
      var j = (i + 1) % n;
      s += verts[i].x * verts[j].y - verts[j].x * verts[i].y;
    }
    return s;
  }

  function polygonArea(verts) {
    return Math.abs(signedArea2(verts)) / 2;
  }

  function dedupeClose(verts) {
    var out = [];
    verts.forEach(function (v) {
      var last = out[out.length - 1];
      if (last && Math.hypot(v.x - last.x, v.y - last.y) < 1e-6) return;
      out.push({ x: v.x, y: v.y });
    });
    if (out.length > 1) {
      var first = out[0];
      var lastPt = out[out.length - 1];
      if (Math.hypot(first.x - lastPt.x, first.y - lastPt.y) < 1e-6) out.pop();
    }
    return out;
  }

  function insertAlcove(verts, wallIndex, width, depth, closed) {
    var n = verts.length;
    var count = closed && n >= 3 ? n : Math.max(0, n - 1);
    if (n < 2 || count < 1) return verts.slice();
    var idx = ((wallIndex % count) + count) % count;
    var a = verts[idx];
    var b = verts[closed && n >= 3 ? (idx + 1) % n : idx + 1];
    var dx = b.x - a.x;
    var dy = b.y - a.y;
    var len = Math.hypot(dx, dy);
    if (len < 1e-6) return verts.slice();
    width = Math.min(Math.max(width, 0.01), len * 0.9);
    depth = Math.max(depth, 0.01);
    var ux = dx / len;
    var uy = dy / len;
    var leftNx = -uy;
    var leftNy = ux;
    var nx = leftNx;
    var ny = leftNy;
    if (closed && n >= 3) {
      // Outward = away from interior. CCW (positive shoelace) has interior on the left.
      if (signedArea2(verts) > 0) {
        nx = -leftNx;
        ny = -leftNy;
      }
    }
    var inset = (len - width) / 2;
    var p0 = { x: a.x + ux * inset, y: a.y + uy * inset };
    var p1 = { x: a.x + ux * (inset + width), y: a.y + uy * (inset + width) };
    var p0o = { x: p0.x + nx * depth, y: p0.y + ny * depth };
    var p1o = { x: p1.x + nx * depth, y: p1.y + ny * depth };
    var out = [];
    verts.forEach(function (v, i) {
      out.push({ x: v.x, y: v.y });
      if (i === idx) out.push(p0, p0o, p1o, p1);
    });
    return dedupeClose(out);
  }

  return {
    INCHES_PER_FOOT: INCHES_PER_FOOT,
    FEET_TO_METERS: FEET_TO_METERS,
    SQFT_TO_SQM: SQFT_TO_SQM,
    MIN_LENGTH_FT: MIN_LENGTH_FT,
    MAX_LENGTH_FT: MAX_LENGTH_FT,
    MIN_WALL_HEIGHT_FT: MIN_WALL_HEIGHT_FT,
    MAX_WALL_HEIGHT_FT: MAX_WALL_HEIGHT_FT,
    DEFAULT_WALL_HEIGHT_FT: DEFAULT_WALL_HEIGHT_FT,
    DEFAULT_DOOR_HEIGHT_IN: DEFAULT_DOOR_HEIGHT_IN,
    DEFAULT_WINDOW_HEIGHT_IN: DEFAULT_WINDOW_HEIGHT_IN,
    DEFAULT_WASTE_PERCENT: DEFAULT_WASTE_PERCENT,
    DEFAULT_BOX_SQFT: DEFAULT_BOX_SQFT,
    DIRECTION_LENGTH: DIRECTION_LENGTH,
    DIRECTION_WIDTH: DIRECTION_WIDTH,
    L_RETURN_W: L_RETURN_W,
    L_RETURN_H: L_RETURN_H,
    UNITS: UNITS,
    parseLengthInput: parseLengthInput,
    describeParsedLength: describeParsedLength,
    detectExplicitUnit: detectExplicitUnit,
    normalizeUnit: normalizeUnit,
    parseWallHeight: parseWallHeight,
    wallHeightDisplay: wallHeightDisplay,
    clampWallHeightFt: clampWallHeightFt,
    rectangle: rectangle,
    lShape: lShape,
    uShape: uShape,
    tShape: tShape,
    lFromOverall: lFromOverall,
    tFromOverall: tFromOverall,
    uFromOverall: uFromOverall,
    shapeFromKind: shapeFromKind,
    insertAlcove: insertAlcove,
    signedArea2: signedArea2,
    polygonArea: polygonArea,
    isAxisAlignedRectangle: isAxisAlignedRectangle,
    overallDimensionText: overallDimensionText,
    readFetchJson: readFetchJson,
    friendlyNetworkError: friendlyNetworkError,
    friendlyPlanUploadError: friendlyPlanUploadError,
    planUploadReason: planUploadReason,
    planUploadErrorAfterRoomSaved: planUploadErrorAfterRoomSaved,
    isWrongFileTypeError: isWrongFileTypeError,
    ceilingHeightFt: ceilingHeightFt,
    ceilingAreaSqFt: ceilingAreaSqFt,
    parseWastePercent: parseWastePercent,
    normalizeWastePercent: normalizeWastePercent,
    normalizeBoxSqFt: normalizeBoxSqFt,
    normalizeDirection: normalizeDirection,
    openingAreaSqFt: openingAreaSqFt,
    unionOpeningAreaSqFt: unionOpeningAreaSqFt,
    perWallNetAreas: perWallNetAreas,
    netWallAreaSqFt: netWallAreaSqFt,
    flooringBoxes: flooringBoxes,
    flooringWithWasteSqFt: function (area, waste) {
      var n = Math.max(0, Number(area) || 0);
      if (n <= 0) return 0;
      return Math.round(n * (1 + (normalizeWastePercent(waste) / 100)) * 100) / 100;
    },
    casingLf: casingLf,
    defaultCasingSides: defaultCasingSides,
    casingSidesForObject: casingSidesForObject,
    objectHeightInches: objectHeightInches,
    objectType: objectType,
    normalizeDoorFace: normalizeDoorFace,
    totalCasingLf: totalCasingLf
  };
}));
