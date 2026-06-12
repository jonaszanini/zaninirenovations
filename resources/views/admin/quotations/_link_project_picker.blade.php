{{--
  Searchable project picker for quotation "link to existing project" forms.
  Expects a non-empty collection of ClientProject models.
--}}
@php
  $fieldName = $name ?? 'client_project_id';
  $pickerId = $id ?? 'fq-project-picker-' . substr(uniqid(), -6);
  $selectedId = (string) old($fieldName, $selected ?? '');
  $items = $projects->map(fn ($p) => [
      'id' => (int) $p->id,
      'label' => $p->client_name . ' — ' . $p->name,
  ])->values();
@endphp
<div class="fq-project-picker" id="{{ $pickerId }}" data-fq-project-picker data-projects='@json($items)'>
  <div class="admin-form-group cp-form-group-flush">
    <label for="{{ $pickerId }}-search">Project</label>
    <div class="fq-project-picker__control">
      <input
        type="search"
        id="{{ $pickerId }}-search"
        class="admin-input fq-project-picker__search"
        placeholder="Search by client or project name…"
        autocomplete="off"
        role="combobox"
        aria-autocomplete="list"
        aria-expanded="false"
        aria-controls="{{ $pickerId }}-list"
      >
      <select
        name="{{ $fieldName }}"
        class="fq-project-picker__select"
        required
        tabindex="-1"
        aria-hidden="true"
      >
        <option value="" @selected($selectedId === '')>Choose a project…</option>
        @foreach ($items as $item)
          <option value="{{ $item['id'] }}" @selected((string) $item['id'] === $selectedId)>{{ $item['label'] }}</option>
        @endforeach
      </select>
      <ul id="{{ $pickerId }}-list" class="fq-project-picker__list" role="listbox" hidden></ul>
    </div>
    <p class="fq-project-picker__selected admin-form-help pq-hint" hidden>
      Selected: <strong class="fq-project-picker__selected-text"></strong>
    </p>
  </div>
</div>
