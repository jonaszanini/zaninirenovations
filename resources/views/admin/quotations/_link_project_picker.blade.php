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
      'name' => (string) $p->name,
      'label' => $p->client_name.' — '.$p->name,
      'client_name' => (string) $p->client_name,
  ])->values();
  $selectedOption = $items->first(fn ($item) => (string) $item['id'] === $selectedId);
@endphp
@include('admin.partials.search_select', [
  'id' => $pickerId,
  'name' => $fieldName,
  'value' => $selectedId,
  'label' => 'Project',
  'placeholder' => 'Search by client or project name…',
  'ariaLabel' => 'Search projects',
  'required' => true,
  'options' => $items->all(),
  'selectedOption' => $selectedOption,
  'searchFields' => ['label', 'name', 'client_name'],
  'metaKeys' => ['client_name'],
  'emptyMessage' => 'No projects match your search.',
  'wrapperClass' => 'fq-project-picker',
  'attrs' => ['data-fq-project-picker' => '1'],
])
