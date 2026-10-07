@php
  $seoProject = $project ?? null;
  $townValue = old('town', $seoProject->town ?? '');
  $completedDefault = isset($seoProject) ? optional($seoProject->completed_on)->format('Y-m-d') : now()->toDateString();
@endphp
<div class="admin-form-grid-2">
  <div class="admin-form-group">
    <label for="town">Town</label>
    <input
      class="admin-input"
      type="text"
      id="town"
      name="town"
      value="{{ $townValue }}"
      maxlength="80"
      list="portfolio-town-options"
      placeholder="Kanata"
      autocomplete="off"
    >
    <datalist id="portfolio-town-options">
      @foreach (\App\Models\Project::APPROVED_TOWNS as $townOption)
        <option value="{{ $townOption }}"></option>
      @endforeach
    </datalist>
    <p class="admin-form-help">Short name on the public page. No ward or neighbourhood.</p>
    @error('town')
      <div class="admin-form-error">{{ $message }}</div>
    @enderror
  </div>
  <div class="admin-form-group">
    <label for="completed_on">Completed on</label>
    <input class="admin-input" type="date" id="completed_on" name="completed_on" value="{{ old('completed_on', $completedDefault) }}" required>
    <p class="admin-form-help">Used to order projects on the site — newest completion first.</p>
    @error('completed_on')
      <div class="admin-form-error">{{ $message }}</div>
    @enderror
  </div>
</div>
