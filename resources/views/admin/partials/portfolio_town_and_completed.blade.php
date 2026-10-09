@php
  $seoProject = $project ?? null;
  $townValue = old('town', $seoProject->town ?? '');
  $completedDefault = isset($seoProject) ? optional($seoProject->completed_on)->format('Y-m-d') : now()->toDateString();
@endphp
<div class="admin-form-grid-2">
  <div class="admin-form-group">
    @include('admin.partials.tipped_label', [
      'label' => 'Town',
      'for' => 'town',
      'tipText' => 'Short name on the public page. No ward or neighbourhood.',
      'tipLabel' => 'Town',
    ])
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
    @error('town')
      <div class="admin-form-error">{{ $message }}</div>
    @enderror
  </div>
  <div class="admin-form-group">
    @include('admin.partials.tipped_label', [
      'label' => 'Completed on',
      'for' => 'completed_on',
      'tipText' => 'Used to order projects on the site — newest completion first.',
      'tipLabel' => 'Completed on',
    ])
    <input class="admin-input" type="date" id="completed_on" name="completed_on" value="{{ old('completed_on', $completedDefault) }}" required>
    @error('completed_on')
      <div class="admin-form-error">{{ $message }}</div>
    @enderror
  </div>
</div>
