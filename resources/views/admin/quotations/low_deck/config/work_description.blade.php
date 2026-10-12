@extends('admin.layouts.app')

@section('heading', 'Work description template')

@section('content')
  <div class="admin-toolbar fq-list-toolbar">
    <div class="admin-toolbar-group">
      <a href="{{ route('admin.quotations.low-deck.config') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-arrow-left"></i> Low deck configuration
      </a>
      <a href="{{ route('admin.quotations.low-deck.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-list"></i> Low deck quotations
      </a>
    </div>
  </div>

  <form method="post" action="{{ route('admin.quotations.low-deck.config.work-description.update') }}" class="admin-card" style="max-width:960px;">
    @csrf
    @method('PUT')

    <h3 class="cp-form-help-flush" style="margin:0 0 8px;">
      Work description template
      <x-admin.info-tip label="Work description template">Used by <strong>Generate description</strong> on the low deck quotation form. Plain text — line breaks are preserved on the client PDF.</x-admin.info-tip>
    </h3>

    <div class="admin-form-group">
      <label for="ld-work-description-template">Template</label>
      <textarea id="ld-work-description-template" name="work_description_template" rows="12" maxlength="10000"
                @class(['admin-input', 'is-invalid' => $errors->has('work_description_template')])>{{ old('work_description_template', $template) }}</textarea>
      @error('work_description_template') <p class="admin-form-error">{{ $message }}</p> @enderror
    </div>

    <div class="admin-form-group">
      <p class="admin-form-help" style="margin:0 0 8px;"><strong>Placeholders</strong> (required — replaced when generating):</p>
      <ul class="admin-form-help" style="margin:0;padding-left:1.25rem;line-height:1.6;">
        @foreach ($placeholders as $token => $help)
          <li><code>{{ $token }}</code> — {{ $help }}</li>
        @endforeach
      </ul>
    </div>

    <div class="admin-form-actions">
      <button type="submit" class="admin-btn admin-btn--primary" data-hotkey-save>
        <i class="fa fa-save"></i> Save template
      </button>
    </div>
  </form>
@endsection
