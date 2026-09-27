{{--
  Compact labor-productivity fields: four columns on desktop.
  Two-field sections are paired on one row (e.g. Floor & walls + Openings).

  @param array  $productivityGroups  title => [key => label]
  @param array  $productivity        current values
  @param string $inputName           posted array name (default productivity)
  @param string $idPrefix            input id prefix (default prod)
--}}
@php
  $inputName = $inputName ?? 'productivity';
  $idPrefix = $idPrefix ?? 'prod';
  $excludeKeys = $excludeKeys ?? [];
  $groupList = [];
  foreach ($productivityGroups as $title => $rows) {
      $filtered = array_filter($rows, function ($label, $key) use ($excludeKeys) {
          return ! in_array($key, $excludeKeys, true);
      }, ARRAY_FILTER_USE_BOTH);
      if ($filtered === []) {
          continue;
      }
      $groupList[] = ['title' => $title, 'rows' => $filtered];
  }
  $claimed = [];
  $blocks = [];
  $n = count($groupList);
  for ($i = 0; $i < $n; $i++) {
      if (isset($claimed[$i])) {
          continue;
      }
      $pair = null;
      if (count($groupList[$i]['rows']) === 2) {
          for ($j = $i + 1; $j < $n; $j++) {
              if (isset($claimed[$j])) {
                  continue;
              }
              if (count($groupList[$j]['rows']) === 2) {
                  $pair = $j;
                  break;
              }
          }
      }
      if ($pair !== null) {
          $claimed[$pair] = true;
          $blocks[] = [$groupList[$i], $groupList[$pair]];
      } else {
          $blocks[] = [$groupList[$i]];
      }
  }
@endphp
<div class="br-prod-pack">
  @foreach ($blocks as $block)
    <div @class(['br-prod-pair' => count($block) === 2])>
      @foreach ($block as $group)
        <div class="br-prod-block">
          <h4 class="br-review-heading">{{ $group['title'] }}</h4>
          <div class="admin-form-grid-4">
            @foreach ($group['rows'] as $key => $label)
              <div class="admin-form-group">
                <label for="{{ $idPrefix }}-{{ $key }}">{{ $label }}</label>
                <input type="number" step="0.01" min="0" class="admin-input" id="{{ $idPrefix }}-{{ $key }}"
                       name="{{ $inputName }}[{{ $key }}]"
                       value="{{ old($inputName.'.'.$key, $productivity[$key] ?? '') }}">
              </div>
            @endforeach
          </div>
        </div>
      @endforeach
    </div>
  @endforeach
</div>
