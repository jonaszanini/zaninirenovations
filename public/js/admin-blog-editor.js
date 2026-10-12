(function () {
  var form = document.getElementById('blog-post-form');
  if (!form) return;

  var csrfMeta = document.querySelector('meta[name="csrf-token"]');
  var csrfToken = csrfMeta ? (csrfMeta.getAttribute('content') || '') : '';
  var dirty = false;
  var saving = false;
  var lastSavedSnapshot = '';
  var autosaveTimer = null;
  var cropper = null;
  var slugManual = form.getAttribute('data-slug-locked') === '1' || !!(document.getElementById('bp-slug') || {}).value;
  var slugLocked = form.getAttribute('data-slug-locked') === '1';

  function el(id) { return document.getElementById(id); }

  function slugify(t) {
    return (t || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/æ/g, 'ae').replace(/Æ/g, 'ae')
      .replace(/œ/g, 'oe').replace(/Œ/g, 'oe')
      .replace(/ß/g, 'ss')
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9\s-]/g, '')
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-')
      .replace(/^-|-$/g, '');
  }

  function editorHtml() {
    if (typeof tinymce !== 'undefined') {
      var ed = tinymce.get('bp-editor');
      if (ed) return ed.getContent() || '';
    }
    var raw = el('bp-editor');
    return raw ? (raw.value || '') : '';
  }

  function editorPlain() {
    var tmp = document.createElement('div');
    tmp.innerHTML = editorHtml();
    return (tmp.textContent || tmp.innerText || '').replace(/\s+/g, ' ').trim();
  }

  function wordCount(text) {
    text = (text || '').trim();
    if (!text) return 0;
    return text.split(/\s+/).length;
  }

  function setCount(node, value, max, warnAt) {
    if (!node) return;
    node.textContent = max ? (value + ' / ' + max) : (value + ' words');
    node.classList.toggle('is-warn', typeof warnAt === 'number' ? value > warnAt : (max && value > max));
  }

  function updateCounters() {
    var title = el('bp-title');
    var seo = el('bp-seo-title');
    var meta = el('bp-meta');
    var excerpt = el('bp-excerpt');
    setCount(el('bp-title-count'), (title && title.value || '').length, 255, 70);
    setCount(el('bp-seo-title-count'), (seo && seo.value || '').length, 70, 60);
    setCount(el('bp-meta-count'), (meta && meta.value || '').length, 155, 155);
    setCount(el('bp-excerpt-count'), (excerpt && excerpt.value || '').length, excerpt ? parseInt(excerpt.getAttribute('maxlength') || '500', 10) : 500);
    setCount(el('bp-word-count'), wordCount(editorPlain()));
  }

  function updateSnippet() {
    var title = (el('bp-seo-title') && el('bp-seo-title').value.trim()) || (el('bp-title') && el('bp-title').value.trim()) || 'Post title';
    var slug = (el('bp-slug') && el('bp-slug').value.trim()) || 'slug';
    var meta = (el('bp-meta') && el('bp-meta').value.trim()) || 'Meta description appears here.';
    if (el('bp-snippet-title')) el('bp-snippet-title').textContent = title;
    if (el('bp-snippet-slug')) el('bp-snippet-slug').textContent = slug;
    if (el('bp-snippet-desc')) el('bp-snippet-desc').textContent = meta.length > 155 ? meta.slice(0, 154) + '…' : meta;
  }

  function featuredPresent() {
    var wrap = el('bp-featured-preview-wrap');
    var img = el('bp-featured-preview-img');
    if (wrap && !wrap.hidden && img && (img.getAttribute('src') || '').trim() !== '') return true;
    return !!(el('bp-pending-featured-path') && el('bp-pending-featured-path').value.trim());
  }

  function publishDate() {
    var v = el('bp-published') && el('bp-published').value;
    return v ? new Date(v) : null;
  }

  function isFutureDate(d) {
    return d && !isNaN(d.getTime()) && d.getTime() > Date.now();
  }

  function updateButtons() {
    var status = el('bp-status') ? el('bp-status').value : 'draft';
    var future = isFutureDate(publishDate());
    var saveBtn = el('bp-btn-save');
    var pubBtn = el('bp-btn-publish');
    var unpubBtn = el('bp-btn-unpublish');
    var label = el('bp-published-label');
    if (label) label.textContent = (status === 'published' && future) ? 'Schedule date' : 'Publish date';

    if (status === 'published') {
      if (saveBtn) saveBtn.textContent = 'Update';
      if (pubBtn) {
        pubBtn.hidden = false;
        pubBtn.textContent = future ? 'Schedule' : 'Publish now';
        pubBtn.setAttribute('data-intent', future ? 'schedule' : 'publish');
      }
      if (unpubBtn) unpubBtn.hidden = false;
    } else {
      if (saveBtn) saveBtn.textContent = 'Save draft';
      if (pubBtn) {
        pubBtn.hidden = false;
        pubBtn.textContent = future ? 'Schedule' : 'Publish';
        pubBtn.setAttribute('data-intent', future ? 'schedule' : 'publish');
      }
      if (unpubBtn) unpubBtn.hidden = true;
    }
  }

  function markDirty() {
    dirty = true;
    scheduleAutosave();
  }

  function snapshot() {
    if (typeof tinymce !== 'undefined') tinymce.triggerSave();
    var fd = new FormData(form);
    fd.delete('featured_image');
    return Array.from(fd.entries()).map(function (pair) { return pair[0] + '=' + pair[1]; }).join('&');
  }

  function jsonHeaders() {
    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': csrfToken,
    };
  }

  function formPayload(extra) {
    if (typeof tinymce !== 'undefined') tinymce.triggerSave();
    var data = {
      title: el('bp-title') ? el('bp-title').value : '',
      slug: el('bp-slug') ? el('bp-slug').value : '',
      html_content: editorHtml(),
      excerpt: el('bp-excerpt') ? el('bp-excerpt').value : '',
      seo_title: el('bp-seo-title') ? el('bp-seo-title').value : '',
      meta_description: el('bp-meta') ? el('bp-meta').value : '',
      featured_image: featuredPresent(),
      featured_image_alt: el('bp-featured-alt') ? el('bp-featured-alt').value : '',
      category_ids: Array.prototype.map.call(document.querySelectorAll('input[name="category_ids[]"]:checked'), function (cb) { return cb.value; }),
      status: el('bp-status') ? el('bp-status').value : 'draft',
      published_at: el('bp-published') ? el('bp-published').value : '',
      author_name: el('bp-author') ? el('bp-author').value : '',
      tag_names: el('bp-tags') ? el('bp-tags').value : '',
      ai_image_prompt: el('bp-ai-image-prompt') ? el('bp-ai-image-prompt').value : '',
      cta_service: el('bp-cta') ? el('bp-cta').value : '',
      noindex: el('bp-noindex') && el('bp-noindex').checked ? 1 : 0,
      pending_featured_image_path: el('bp-pending-featured-path') ? el('bp-pending-featured-path').value : '',
      autosave: 1,
    };
    if (extra) Object.keys(extra).forEach(function (k) { data[k] = extra[k]; });
    return data;
  }

  function setSaveStatus(msg) {
    var node = el('bp-save-status');
    if (node) node.textContent = msg || '';
  }

  function applyAutosaveResponse(body) {
    if (!body || !body.id) return;
    form.setAttribute('data-post-id', String(body.id));
    form.setAttribute('data-is-edit', '1');
    if (body.autosave_url) form.setAttribute('data-autosave-url', body.autosave_url);
    if (body.edit_url) {
      form.action = body.edit_url;
      if (!form.querySelector('input[name="_method"]')) {
        var method = document.createElement('input');
        method.type = 'hidden';
        method.name = '_method';
        method.value = 'PUT';
        form.appendChild(method);
      }
      if (window.history && window.history.replaceState) {
        window.history.replaceState({}, '', body.edit_url);
      }
    }
    if (body.preview_url) {
      var link = el('bp-preview-link');
      if (link) {
        link.href = body.preview_url;
        link.hidden = false;
      }
      form.setAttribute('data-preview-url', body.preview_url);
    }
    if (body.slug && el('bp-slug') && !slugManual) {
      el('bp-slug').value = body.slug;
    }
    setSaveStatus('Saved ' + (body.saved_at ? new Date(body.saved_at).toLocaleTimeString() : 'just now'));
    dirty = false;
    lastSavedSnapshot = snapshot();
  }

  function runAutosave() {
    if (saving) return;
    var title = el('bp-title') ? el('bp-title').value.trim() : '';
    var html = editorPlain();
    if (!title && !html) return;
    saving = true;
    setSaveStatus('Saving…');
    fetch(form.getAttribute('data-autosave-url'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: jsonHeaders(),
      body: JSON.stringify(formPayload()),
    }).then(function (res) {
      return res.json().then(function (body) {
        if (!res.ok) throw body;
        return body;
      });
    }).then(applyAutosaveResponse).catch(function () {
      setSaveStatus('Autosave failed — your edits are still on this page.');
    }).finally(function () {
      saving = false;
    });
  }

  function scheduleAutosave() {
    if (autosaveTimer) window.clearTimeout(autosaveTimer);
    autosaveTimer = window.setTimeout(runAutosave, 20000);
  }

  function setInlineError(id, msg) {
    var node = el(id);
    if (!node) return;
    if (msg) {
      node.hidden = false;
      node.textContent = msg;
    } else {
      node.hidden = true;
      node.textContent = '';
    }
  }

  function validateForPublish() {
    var ok = true;
    var title = el('bp-title') ? el('bp-title').value.trim() : '';
    if (!title) {
      setInlineError('bp-title-inline', 'Add a title before publishing.');
      ok = false;
    } else {
      setInlineError('bp-title-inline', '');
    }
    if (!editorPlain()) {
      ok = false;
      window.AdminDialog && window.AdminDialog.alert('Add article content before publishing.');
    }
    return ok;
  }

  function updateInlineAlts() {
    var box = el('bp-inline-alts');
    if (!box) return;
    var html = editorHtml();
    var imgs = html.match(/<img\b[^>]*>/gi) || [];
    var missing = 0;
    imgs.forEach(function (tag) {
      var m = tag.match(/\balt\s*=\s*(['"])(.*?)\1/i);
      if (!m || !String(m[2] || '').trim()) missing++;
    });
    if (missing > 0) {
      box.hidden = false;
      box.textContent = missing + ' inline image' + (missing === 1 ? '' : 's') + ' missing alt text. Use the image dialog to add it.';
    } else {
      box.hidden = true;
      box.textContent = '';
    }
  }

  function renderChecklist(data) {
    var list = el('bp-check-list');
    var ready = el('bp-check-ready');
    if (!list) return;
    list.innerHTML = '';
    (data.items || []).forEach(function (item) {
      var li = document.createElement('li');
      li.className = 'bp-check-item' + (item.ok ? ' is-ok' : (item.blocking ? ' is-bad' : ' is-warn'));
      li.textContent = (item.ok ? '✓ ' : '• ') + item.label + (item.detail ? ' — ' + item.detail : '');
      list.appendChild(li);
    });
    if (ready) {
      ready.textContent = data.ready ? 'Ready' : 'Needs work';
      ready.className = 'bp-check-ready' + (data.ready ? ' is-ok' : ' is-bad');
    }
  }

  function runChecklist() {
    fetch(form.getAttribute('data-check-url'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: jsonHeaders(),
      body: JSON.stringify(formPayload()),
    }).then(function (res) { return res.json(); }).then(renderChecklist).catch(function () {});
  }

  function applyFeaturedPreview(data) {
    var wrap = el('bp-featured-preview-wrap');
    var img = el('bp-featured-preview-img');
    var removeWrap = el('bp-remove-featured-wrap');
    var removeCb = el('bp-remove-featured');
    var pending = el('bp-pending-featured-path');
    if (!data.featured_image_url || !wrap || !img) return;
    var url = data.featured_image_url;
    url += (url.indexOf('?') === -1 ? '?' : '&') + 'v=' + Date.now();
    img.src = url;
    wrap.hidden = false;
    if (removeWrap) removeWrap.hidden = false;
    if (removeCb) removeCb.checked = false;
    if (pending) pending.value = data.featured_image_path || '';
  }

  function applyAiMetadata(data) {
    if (el('bp-excerpt') && data.excerpt) el('bp-excerpt').value = data.excerpt;
    if (el('bp-meta') && data.meta_description) el('bp-meta').value = data.meta_description;
    if (el('bp-seo-title') && data.seo_title) el('bp-seo-title').value = data.seo_title;
    if (el('bp-featured-alt') && data.featured_image_alt) el('bp-featured-alt').value = data.featured_image_alt;
    if (el('bp-ai-image-prompt') && data.image_prompt) el('bp-ai-image-prompt').value = data.image_prompt;
    if (data.slug && el('bp-slug') && !slugLocked) {
      el('bp-slug').value = data.slug;
      slugManual = true;
    }
    if (data.tags) setTagsFromString(data.tags);
    var ids = Array.isArray(data.category_ids) ? data.category_ids.map(String) : [];
    document.querySelectorAll('input[name="category_ids[]"]').forEach(function (cb) {
      cb.checked = ids.indexOf(String(cb.value)) !== -1;
    });
    applyFeaturedPreview(data);
    updateCounters();
    updateSnippet();
    runChecklist();
    markDirty();
  }

  function metadataSnapshot() {
    return JSON.stringify({
      excerpt: el('bp-excerpt') ? el('bp-excerpt').value : '',
      meta_description: el('bp-meta') ? el('bp-meta').value : '',
      seo_title: el('bp-seo-title') ? el('bp-seo-title').value : '',
      slug: el('bp-slug') ? el('bp-slug').value : '',
      tags: el('bp-tags') ? el('bp-tags').value : '',
      image_prompt: el('bp-ai-image-prompt') ? el('bp-ai-image-prompt').value : '',
      featured_image_alt: el('bp-featured-alt') ? el('bp-featured-alt').value : '',
      category_ids: Array.prototype.map.call(document.querySelectorAll('input[name="category_ids[]"]:checked'), function (cb) { return cb.value; }),
      featured_image_url: el('bp-featured-preview-img') ? (el('bp-featured-preview-img').getAttribute('src') || '') : '',
      featured_image_path: el('bp-pending-featured-path') ? el('bp-pending-featured-path').value : '',
    });
  }

  function setAiStatus(msg, isError) {
    var node = el('bp-ai-metadata-status');
    if (!node) return;
    node.textContent = msg || '';
    node.classList.toggle('is-error', !!isError);
  }

  function currentTags() {
    var hidden = el('bp-tags');
    if (!hidden || !hidden.value.trim()) return [];
    return hidden.value.split(',').map(function (t) { return t.trim(); }).filter(Boolean);
  }

  function renderTagChips() {
    var wrap = el('bp-tag-chips');
    if (!wrap) return;
    wrap.innerHTML = '';
    currentTags().forEach(function (name) {
      var chip = document.createElement('span');
      chip.className = 'bp-chip';
      chip.setAttribute('data-tag', name);
      chip.appendChild(document.createTextNode(name + ' '));
      var x = document.createElement('button');
      x.type = 'button';
      x.className = 'bp-chip__x';
      x.setAttribute('aria-label', 'Remove ' + name);
      x.innerHTML = '&times;';
      chip.appendChild(x);
      wrap.appendChild(chip);
    });
  }

  function setTagsFromString(raw) {
    var names = String(raw || '').split(',').map(function (t) { return t.trim(); }).filter(Boolean);
    var unique = [];
    names.forEach(function (n) {
      if (unique.indexOf(n) === -1) unique.push(n);
    });
    if (el('bp-tags')) el('bp-tags').value = unique.join(', ');
    renderTagChips();
  }

  function addTag(name) {
    name = String(name || '').trim();
    if (!name) return;
    var tags = currentTags();
    if (tags.indexOf(name) === -1) tags.push(name);
    if (el('bp-tags')) el('bp-tags').value = tags.join(', ');
    renderTagChips();
    markDirty();
  }

  var titleEl = el('bp-title');
  var slugEl = el('bp-slug');
  if (titleEl && slugEl) {
    titleEl.addEventListener('input', function () {
      if (!slugLocked && (!slugManual || slugEl.dataset.auto === '1')) {
        slugEl.value = slugify(titleEl.value);
        slugEl.dataset.auto = '1';
      }
      updateCounters();
      updateSnippet();
      markDirty();
    });
    slugEl.addEventListener('input', function () {
      slugEl.dataset.auto = '0';
      slugManual = true;
      updateSnippet();
      markDirty();
    });
  }
  var unlock = el('bp-slug-unlock');
  if (unlock && slugEl) {
    unlock.addEventListener('click', function () {
      window.AdminDialog.confirm('Changing a published slug 301-redirects the old URL. Continue?', { title: 'Change slug' }).then(function (ok) {
        if (!ok) return;
        slugLocked = false;
        slugEl.readOnly = false;
        slugEl.focus();
        var hint = el('bp-slug-hint');
        if (hint) hint.textContent = 'Old URL will 301 to the new slug after save.';
        unlock.hidden = true;
      });
    });
  }

  ['bp-seo-title', 'bp-meta', 'bp-excerpt', 'bp-featured-alt', 'bp-ai-image-prompt'].forEach(function (id) {
    var node = el(id);
    if (!node) return;
    node.addEventListener('input', function () {
      updateCounters();
      updateSnippet();
      markDirty();
    });
  });
  if (el('bp-status')) el('bp-status').addEventListener('change', function () { updateButtons(); markDirty(); });
  if (el('bp-published')) el('bp-published').addEventListener('change', function () { updateButtons(); markDirty(); });
  form.addEventListener('change', function (e) {
    if (e.target && e.target.name === 'category_ids[]') markDirty();
  });

  var tagInput = el('bp-tag-input');
  if (tagInput) {
    tagInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        addTag(tagInput.value.replace(',', ''));
        tagInput.value = '';
      }
    });
    tagInput.addEventListener('blur', function () {
      if (tagInput.value.trim()) {
        addTag(tagInput.value);
        tagInput.value = '';
      }
    });
  }
  var chips = el('bp-tag-chips');
  if (chips) {
    chips.addEventListener('click', function (e) {
      var btn = e.target.closest('.bp-chip__x');
      if (!btn) return;
      var chip = btn.closest('.bp-chip');
      var name = chip ? chip.getAttribute('data-tag') : '';
      setTagsFromString(currentTags().filter(function (t) { return t !== name; }).join(', '));
      markDirty();
    });
  }

  var bpWordWrapKey = 'bp-editor-word-wrap';
  function bpApplyWordWrap(editor, enabled) {
    if (!editor.getBody) return;
    var body = editor.getBody();
    if (!body) return;
    body.classList.toggle('bp-wrap-on', !!enabled);
    body.classList.toggle('bp-wrap-off', !enabled);
    var doc = editor.getDoc();
    if (!doc || !doc.head) return;
    var style = doc.getElementById('bp-word-wrap-style');
    if (!style) {
      style = document.createElement('style');
      style.id = 'bp-word-wrap-style';
      doc.head.appendChild(style);
    }
    style.textContent = [
      'body.bp-wrap-on, body.bp-wrap-on p, body.bp-wrap-on div, body.bp-wrap-on li,',
      'body.bp-wrap-on td, body.bp-wrap-on th, body.bp-wrap-on span { overflow-wrap: anywhere !important; word-break: break-word !important; white-space: normal !important; }',
      'body.bp-wrap-on pre, body.bp-wrap-on code { white-space: pre-wrap !important; overflow-wrap: anywhere !important; }',
    ].join('\n');
    try { localStorage.setItem(bpWordWrapKey, enabled ? '1' : '0'); } catch (e) { /* ignore */ }
  }

  var bpIsDark = document.documentElement.classList.contains('admin-dark');
  var bpContentStyle = 'body { font-family: Arial, Helvetica, sans-serif; font-size: 12pt; line-height: 1.55; overflow-wrap: break-word; word-break: break-word; '
    + (bpIsDark ? 'background: #1e293b; color: #e2e8f0; ' : '')
    + '} pre, code { white-space: pre-wrap; overflow-wrap: anywhere; } img { max-width: 100%; height: auto; }'
    + (bpIsDark ? ' a { color: #38bdf8; }' : '');

  if (typeof tinymce !== 'undefined') {
    tinymce.init({
      selector: '#bp-editor',
      skin: bpIsDark ? 'oxide-dark' : 'oxide',
      content_css: bpIsDark ? 'dark' : 'default',
      menubar: 'edit insert view format table tools',
      plugins: 'advlist autolink lists link image table code preview searchreplace fullscreen visualblocks wordcount paste help',
      toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | link table image | bpwordwrap | removeformat code',
      height: 380,
      branding: false,
      promotion: false,
      relative_urls: false,
      remove_script_host: false,
      convert_urls: true,
      paste_data_images: true,
      automatic_uploads: true,
      images_upload_handler: function (blobInfo) {
        return new Promise(function (resolve, reject) {
          var fd = new FormData();
          fd.append('file', blobInfo.blob(), blobInfo.filename());
          fd.append('post_id', form.getAttribute('data-post-id') || '0');
          fetch(form.getAttribute('data-upload-url'), {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': csrfToken,
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
            },
            body: fd,
          }).then(function (r) { return r.json(); }).then(function (data) {
            if (data.location) resolve(data.location);
            else reject('Upload failed');
          }).catch(function () { reject('Upload failed'); });
        });
      },
      content_style: bpContentStyle,
      setup: function (editor) {
        var wrapEnabled = true;
        try { wrapEnabled = localStorage.getItem(bpWordWrapKey) !== '0'; } catch (e) { /* ignore */ }
        editor.ui.registry.addIcon('bpwordwrap', '<svg width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M4 19h6v-2H4v2zm16-14H4v2h16V5zm-3 6H4v2h13.25c1.1 0 2 .9 2 2s-.9 2-2 2H15v-2l-3 3 3 3v-2h2c2.21 0 4-1.79 4-4s-1.79-4-4-4z"/></svg>');
        editor.ui.registry.addToggleButton('bpwordwrap', {
          icon: 'bpwordwrap',
          tooltip: 'Word wrap',
          onAction: function (api) {
            wrapEnabled = !wrapEnabled;
            api.setActive(wrapEnabled);
            bpApplyWordWrap(editor, wrapEnabled);
          },
          onSetup: function (api) {
            api.setActive(wrapEnabled);
            return function () {};
          },
        });
        editor.on('init', function () {
          bpApplyWordWrap(editor, wrapEnabled);
          updateCounters();
          updateInlineAlts();
          runChecklist();
          lastSavedSnapshot = snapshot();
        });
        editor.on('change keyup undo redo SetContent', function () {
          editor.save();
          updateCounters();
          updateInlineAlts();
          markDirty();
        });
      },
    });
  }

  var fileInput = el('bp-featured');
  var cropWrap = el('bp-crop-wrap');
  var cropImg = el('bp-crop-image');
  var cropAppliesInput = el('bp-featured-crop-applies');
  var pendingFeaturedInput = el('bp-pending-featured-path');

  function clearFeaturedCropFields() {
    if (cropAppliesInput) cropAppliesInput.value = '0';
    ['bp-crop-x', 'bp-crop-y', 'bp-crop-w', 'bp-crop-h'].forEach(function (id) {
      if (el(id)) el(id).value = '';
    });
  }

  if (fileInput && cropImg) {
    fileInput.addEventListener('change', function () {
      var file = fileInput.files && fileInput.files[0];
      if (!file) { clearFeaturedCropFields(); return; }
      if (pendingFeaturedInput) pendingFeaturedInput.value = '';
      var url = URL.createObjectURL(file);
      cropImg.src = url;
      cropWrap.hidden = false;
      if (cropper) cropper.destroy();
      cropper = new Cropper(cropImg, { aspectRatio: 16 / 9, viewMode: 1 });
      var wrap = el('bp-featured-preview-wrap');
      var img = el('bp-featured-preview-img');
      if (wrap && img) { img.src = url; wrap.hidden = false; }
      markDirty();
    });
  }

  form.addEventListener('submit', function (e) {
    var intentInput = el('bp-intent');
    var submitter = e.submitter;
    var intent = submitter && submitter.getAttribute('data-intent') ? submitter.getAttribute('data-intent') : 'save';
    if (intentInput) intentInput.value = intent;
    if ((intent === 'publish' || intent === 'schedule') && !validateForPublish()) {
      e.preventDefault();
      return;
    }
    if (intent === 'schedule' && !isFutureDate(publishDate())) {
      e.preventDefault();
      window.AdminDialog && window.AdminDialog.alert('Set a future publish date to schedule this post.');
      return;
    }
    clearFeaturedCropFields();
    var hasNewFile = fileInput && fileInput.files && fileInput.files.length > 0;
    if (cropper && hasNewFile) {
      var d = cropper.getData(true);
      el('bp-crop-x').value = Math.round(d.x);
      el('bp-crop-y').value = Math.round(d.y);
      el('bp-crop-w').value = Math.round(d.width);
      el('bp-crop-h').value = Math.round(d.height);
      if (cropAppliesInput) cropAppliesInput.value = '1';
    }
    if (typeof tinymce !== 'undefined') tinymce.triggerSave();
    dirty = false;
  });

  window.addEventListener('beforeunload', function (e) {
    if (!dirty || snapshot() === lastSavedSnapshot) return;
    e.preventDefault();
    e.returnValue = '';
  });

  var blogHistoryMount = document.querySelector('.blog-admin-ai-metadata-wrap [data-history-mount]');
  var blogHistory = null;
  if (blogHistoryMount && window.AdminAiTextHistory) {
    blogHistory = window.AdminAiTextHistory.create({
      storageKey: 'blog-metadata:' + (blogHistoryMount.getAttribute('data-history-key') || 'new'),
      mountEl: blogHistoryMount,
      title: 'Blog metadata versions',
      getText: metadataSnapshot,
      setText: function (json) {
        try { applyAiMetadata(JSON.parse(json)); } catch (e) { /* ignore */ }
      },
      formatPreview: function (json) {
        try {
          var data = JSON.parse(json);
          var parts = [];
          if (data.seo_title) parts.push(data.seo_title);
          if (data.meta_description) parts.push(data.meta_description);
          return window.AdminAiTextHistory.defaultPreview(parts.join(' · '));
        } catch (e) {
          return window.AdminAiTextHistory.defaultPreview(json);
        }
      },
    });
  }

  function requireTitleContent() {
    var title = el('bp-title') ? el('bp-title').value.trim() : '';
    if (!title || !editorPlain()) {
      window.AdminDialog.alert('Enter a title and blog content first.');
      return false;
    }
    return true;
  }

  var suggestBtn = el('bp-ai-suggest-btn');
  if (suggestBtn) {
    suggestBtn.addEventListener('click', function () {
      if (!requireTitleContent()) return;
      function run() {
        if (blogHistory) blogHistory.captureOriginalIfNeeded();
        suggestBtn.disabled = true;
        setAiStatus('Suggesting metadata…', false);
        fetch(form.getAttribute('data-suggest-url'), {
          method: 'POST',
          credentials: 'same-origin',
          headers: jsonHeaders(),
          body: JSON.stringify({ title: el('bp-title').value.trim(), html_content: editorHtml() }),
        }).then(function (res) {
          return res.json().then(function (body) {
            if (!res.ok) throw new Error(body.error || ('HTTP ' + res.status));
            return body;
          });
        }).then(function (body) {
          applyAiMetadata(body);
          if (blogHistory) blogHistory.addVersion(metadataSnapshot());
          setAiStatus('Metadata filled in. Review before saving.', false);
          window.setTimeout(function () { setAiStatus('', false); }, 6000);
        }).catch(function (err) {
          setAiStatus(err.message || 'Could not suggest metadata.', true);
        }).finally(function () { suggestBtn.disabled = false; });
      }
      var filled = (el('bp-excerpt') && el('bp-excerpt').value.trim()) || (el('bp-meta') && el('bp-meta').value.trim()) || (el('bp-seo-title') && el('bp-seo-title').value.trim());
      if (filled) {
        window.AdminDialog.confirm('Replace SEO title, slug, excerpt, tags, and meta with AI suggestions?', { title: 'Replace metadata' }).then(function (ok) {
          if (ok) run();
        });
        return;
      }
      run();
    });
  }

  var imageBtn = el('bp-ai-image-btn');
  if (imageBtn) {
    imageBtn.addEventListener('click', function () {
      var prompt = el('bp-ai-image-prompt') ? el('bp-ai-image-prompt').value.trim() : '';
      if (!prompt) {
        window.AdminDialog.alert('Add an image prompt (or run Suggest metadata first).');
        return;
      }
      imageBtn.disabled = true;
      setAiStatus('Generating featured image…', false);
      fetch(form.getAttribute('data-image-url'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: jsonHeaders(),
        body: JSON.stringify({ image_prompt: prompt }),
      }).then(function (res) {
        return res.json().then(function (body) {
          if (!res.ok) throw new Error(body.error || ('HTTP ' + res.status));
          return body;
        });
      }).then(function (body) {
        applyFeaturedPreview(body);
        markDirty();
        runChecklist();
        setAiStatus('Featured image ready. It replaces the current one only when you save.', false);
        window.setTimeout(function () { setAiStatus('', false); }, 6000);
      }).catch(function (err) {
        setAiStatus(err.message || 'Could not generate image.', true);
      }).finally(function () { imageBtn.disabled = false; });
    });
  }

  var checkBtn = el('bp-check-btn');
  if (checkBtn) {
    checkBtn.addEventListener('click', function () {
      checkBtn.disabled = true;
      fetch(form.getAttribute('data-check-url'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: jsonHeaders(),
        body: JSON.stringify(formPayload()),
      }).then(function (res) { return res.json(); }).then(function (body) {
        renderChecklist(body);
        var licensed = (body.issues || []).find(function (i) { return i.code === 'licensed'; });
        if (licensed) setAiStatus(licensed.message, true);
        else setAiStatus((body.ready ? 'Content looks ready.' : 'Checklist updated.'), false);
        window.setTimeout(function () { setAiStatus('', false); }, 7000);
      }).catch(function () {
        setAiStatus('Could not check content.', true);
      }).finally(function () { checkBtn.disabled = false; });
    });
  }

  updateCounters();
  updateSnippet();
  updateButtons();
  renderTagChips();
  runChecklist();
})();
