/**
 * Converts .avif picks on any <input type="file"> to WebP in the browser before
 * page scripts or form submits see them. The server then applies its usual
 * WebP compression. Runs in the capture phase so page-level change listeners
 * only ever receive the converted files.
 */
(function () {
  'use strict';

  var MAX_EDGE = 4096;
  var QUALITY = 0.9;
  var pending = 0;
  var queuedSubmit = null;

  function isAvif(file) {
    if (!file) return false;
    var type = String(file.type || '').toLowerCase();
    return type === 'image/avif' || /\.avif$/i.test(file.name || '');
  }

  function loadImage(file) {
    if (window.createImageBitmap) {
      return createImageBitmap(file).catch(function () { return loadViaImg(file); });
    }
    return loadViaImg(file);
  }

  function loadViaImg(file) {
    return new Promise(function (resolve, reject) {
      var url = URL.createObjectURL(file);
      var img = new Image();
      img.onload = function () { URL.revokeObjectURL(url); resolve(img); };
      img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('decode')); };
      img.src = url;
    });
  }

  function convert(file) {
    return loadImage(file).then(function (img) {
      var w = img.width || img.naturalWidth;
      var h = img.height || img.naturalHeight;
      var scale = Math.min(1, MAX_EDGE / Math.max(w, h));
      var cw = Math.max(1, Math.round(w * scale));
      var ch = Math.max(1, Math.round(h * scale));
      var canvas = document.createElement('canvas');
      canvas.width = cw;
      canvas.height = ch;
      canvas.getContext('2d').drawImage(img, 0, 0, cw, ch);
      if (img.close) img.close();
      return new Promise(function (resolve, reject) {
        canvas.toBlob(function (blob) {
          if (!blob) { reject(new Error('encode')); return; }
          var ext = blob.type === 'image/webp' ? 'webp' : 'png';
          var base = String(file.name || 'image').replace(/\.avif$/i, '');
          resolve(new File([blob], base + '.' + ext, { type: blob.type, lastModified: Date.now() }));
        }, 'image/webp', QUALITY);
      });
    });
  }

  document.addEventListener('change', function (e) {
    var input = e.target;
    if (!input || input.tagName !== 'INPUT' || input.type !== 'file') return;
    if (input.__avifConverted) {
      input.__avifConverted = false;
      return;
    }
    var files = Array.prototype.slice.call(input.files || []);
    if (!files.some(isAvif)) return;
    if (typeof DataTransfer === 'undefined') return;

    e.stopImmediatePropagation();
    pending++;

    Promise.all(files.map(function (f) {
      // Keep the original when the browser can't decode AVIF; the server
      // converts it instead.
      return isAvif(f) ? convert(f).catch(function () { return f; }) : Promise.resolve(f);
    })).then(function (out) {
      var dt = new DataTransfer();
      out.forEach(function (f) { dt.items.add(f); });
      input.files = dt.files;
      input.__avifConverted = true;
      input.dispatchEvent(new Event('change', { bubbles: true }));
    }).finally(function () {
      pending--;
      if (pending === 0 && queuedSubmit) {
        var form = queuedSubmit;
        queuedSubmit = null;
        if (form.requestSubmit) form.requestSubmit(); else form.submit();
      }
    });
  }, true);

  document.addEventListener('submit', function (e) {
    if (pending > 0) {
      e.preventDefault();
      e.stopImmediatePropagation();
      queuedSubmit = e.target;
    }
  }, true);
})();
