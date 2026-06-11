@php
  /**
   * @var int $maxTotal
   * @var string $fileInputId  ID of the companion file input for counting uploads
   * @var string $statusMode   'total' | 'remaining'
   * @var bool $formSync       Sync checked items into hidden fields on form submit
   * @var string|null $footText
   */
  $statusMode = $statusMode ?? 'total';
  $formSync = $formSync ?? true;
  $footText = $footText ?? null;
@endphp

<div class="admin-google-photos-panel">
  <div class="admin-google-photos-panel__head">
    <span class="admin-form-group-label">Google Photos</span>
    @if (auth()->user()->googlePhotosToken)
      <span class="admin-google-photos-panel__badge">Import from cloud</span>
    @endif
  </div>

  @if (auth()->user()->googlePhotosToken)
    <div
      class="admin-google-photos"
      data-picker-create-url="{{ route('admin.google-photos.picker-sessions.create') }}"
      data-picker-media-url-template="{{ route('admin.google-photos.picker-sessions.media', ['sessionId' => '__SESSION__']) }}"
      data-picker-thumbnail-url="{{ route('admin.google-photos.thumbnail') }}"
      data-max-total="{{ (int) $maxTotal }}"
      data-file-input-id="{{ $fileInputId }}"
      data-status-mode="{{ $statusMode }}"
      data-form-sync="{{ $formSync ? 'true' : 'false' }}">
      <div class="admin-google-photos-head">
        <span class="admin-google-photos-account">Connected as <strong>{{ auth()->user()->googlePhotosToken->google_email }}</strong></span>
        <button
          type="button"
          class="admin-btn admin-btn--ghost admin-btn--sm"
          data-admin-google-disconnect
          data-disconnect-url="{{ route('admin.google-photos.disconnect') }}">Disconnect</button>
      </div>
      <p class="admin-form-help admin-google-photos-panel__intro">Open Google Photos Picker, choose photos (you can open your album there and select all), then import the picked results here.</p>
      <div class="admin-google-photos-toolbar">
        <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-google-open-picker>Open Google Photos Picker</button>
        <span class="admin-google-photos-status" data-google-status></span>
        <span class="admin-google-photos-hint" data-google-hint hidden></span>
      </div>
      <div class="admin-google-photos-grid" data-google-grid></div>
      <div data-google-picked-fields></div>
    </div>
    @if ($footText)
      <p class="admin-google-photos-panel__foot">{{ $footText }}</p>
    @endif
  @else
    <p class="admin-form-help admin-google-photos-panel__intro">
      <a class="admin-btn admin-btn--ghost admin-btn--sm" href="{{ route('admin.google-photos.connect', ['return' => request()->getRequestUri()]) }}">Connect Google Photos</a>
      <span class="admin-google-photos-panel__connect-hint">Required to open Google Photos Picker.</span>
    </p>
  @endif

  @error('google_picker_items')
    <div class="admin-form-error">{{ $message }}</div>
  @enderror
  @error('google_picker_items.*')
    <div class="admin-form-error">{{ $message }}</div>
  @enderror
</div>

@once
  @push('scripts')
    <script src="{{ asset('js/admin-google-photos-picker.js') }}?v={{ @filemtime(public_path('js/admin-google-photos-picker.js')) ?: time() }}"></script>
  @endpush
@endonce
