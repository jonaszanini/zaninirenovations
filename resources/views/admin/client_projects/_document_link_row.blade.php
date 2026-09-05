@php
  $index = $index ?? 0;
  $row = is_array($row ?? null) ? $row : [];
  $url = (string) ($row['url'] ?? '');
  $title = (string) ($row['title'] ?? '');
  $kind = (string) ($row['kind'] ?? 'auto');
  if (! in_array($kind, ['auto', 'word', 'excel', 'ppt'], true)) {
      $kind = 'auto';
  }
  $formId = $formId ?? null;
  $urlId = 'cp-doc-link-url-'.$index;
  $titleId = 'cp-doc-link-title-'.$index;
  $kindId = 'cp-doc-link-kind-'.$index;
@endphp

<div class="cp-document-links-row" data-cp-doc-link-row>
  <div class="admin-form-group cp-document-links-url">
    <label for="{{ $urlId }}">Link URL</label>
    <input class="admin-input"
           type="text"
           id="{{ $urlId }}"
           name="document_links[{{ $index }}][url]"
           value="{{ $url }}"
           placeholder="https://…"
           inputmode="url"
           autocomplete="off"
           @if ($formId) form="{{ $formId }}" @endif>
    @error('document_links.'.$index.'.url')
      <p class="admin-form-error">{{ $message }}</p>
    @enderror
  </div>
  <div class="cp-document-links-meta">
    <div class="admin-form-group">
      <label for="{{ $titleId }}">Label <span class="admin-form-help cp-inline-help">(optional)</span></label>
      <input class="admin-input"
             type="text"
             id="{{ $titleId }}"
             name="document_links[{{ $index }}][title]"
             value="{{ $title }}"
             maxlength="255"
             placeholder="e.g. Material list"
             @if ($formId) form="{{ $formId }}" @endif>
    </div>
    <div class="admin-form-group">
      <label for="{{ $kindId }}">Type</label>
      <select class="admin-input" id="{{ $kindId }}" name="document_links[{{ $index }}][kind]" @if ($formId) form="{{ $formId }}" @endif>
        <option value="auto" @selected($kind === 'auto')>Auto-detect</option>
        <option value="word" @selected($kind === 'word')>Word</option>
        <option value="excel" @selected($kind === 'excel')>Excel</option>
        <option value="ppt" @selected($kind === 'ppt')>PowerPoint</option>
      </select>
    </div>
    <button type="button"
            class="admin-btn admin-btn--ghost cp-document-links-remove"
            data-cp-doc-link-remove
            aria-label="Remove this link row"
            title="Remove this row">
      <i class="fa fa-times" aria-hidden="true"></i>
    </button>
  </div>
</div>
