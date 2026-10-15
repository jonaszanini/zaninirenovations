@php
  $links = $linkedUtilities['tool_links'] ?? [];
  $hasAny = $linkedUtilities['has_any'] ?? false;
  $sections = [
    'room_area' => [
      'title' => 'Room Area',
      'icon' => 'fa-object-group',
      'items' => $linkedUtilities['room_area'] ?? [],
      'new_url' => $links['room_area'] ?? route('admin.client-projects.utilities.room-area', $project),
      'new_label' => 'Open Room Area tool',
      'target' => '_blank',
    ],
    'measurement' => [
      'title' => 'Measurement Conversion',
      'icon' => 'fa-arrows-h',
      'items' => $linkedUtilities['measurement'] ?? [],
      'new_url' => $links['measurement'] ?? route('admin.utilities.measurement-conversion'),
      'new_label' => 'Open Measurement tool',
    ],
    'lumber' => [
      'title' => 'Lumber / Board Material',
      'icon' => 'fa-th-large',
      'items' => $linkedUtilities['lumber'] ?? [],
      'new_url' => $links['lumber'] ?? route('admin.utilities.lumber-board', ['project' => $project->id]),
      'new_label' => 'New lumber calculation',
    ],
    'pillar' => [
      'title' => 'Pillar & Spindle',
      'icon' => 'fa-bars',
      'items' => $linkedUtilities['pillar'] ?? [],
      'new_url' => $links['pillar'] ?? route('admin.utilities.pillar-spindle'),
      'new_label' => 'Open Pillar & Spindle tool',
    ],
  ];
@endphp

<div class="cp-utilities">
  <div class="admin-card cp-utilities-intro">
    <div class="cp-utilities-intro__text">
      <h3>Linked utilities</h3>
      <p class="admin-form-help">
        Saved work from admin calculators linked to this project. Open any item to continue editing in its tool.
      </p>
    </div>
    <a href="{{ $links['index'] ?? route('admin.utilities.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
      <i class="fa fa-wrench" aria-hidden="true"></i> All utilities
    </a>
  </div>

  @if (!$hasAny)
    <div class="admin-card cp-utilities-empty">
      <p>No utilities are linked to this project yet.</p>
      <p class="admin-form-help">
        When you save a Room Area or Measurement project, or a Lumber calculation, choose this client project in the tool to link it here.
      </p>
      <div class="cp-utilities-empty__actions">
        @foreach ($sections as $section)
          <a href="{{ $section['new_url'] }}" class="admin-btn admin-btn--ghost admin-btn--sm" @if (!empty($section['target'])) target="{{ $section['target'] }}" rel="noopener" @endif>
            <i class="fa {{ $section['icon'] }}" aria-hidden="true"></i> {{ $section['new_label'] }}
          </a>
        @endforeach
      </div>
    </div>
  @else
    @foreach ($sections as $key => $section)
      @if (count($section['items']) > 0)
        <div class="admin-card cp-utilities-section">
          <div class="cp-utilities-section__head">
            <h3>
              <i class="fa {{ $section['icon'] }}" aria-hidden="true"></i>
              {{ $section['title'] }}
              <span class="cp-utilities-section__count">{{ count($section['items']) }}</span>
            </h3>
            <a href="{{ $section['new_url'] }}" class="admin-btn admin-btn--ghost admin-btn--sm" @if (!empty($section['target'])) target="{{ $section['target'] }}" rel="noopener" @endif>{{ $section['new_label'] }}</a>
          </div>

          <div class="cp-utilities-list">
            @foreach ($section['items'] as $item)
              <article class="cp-utilities-item">
                <div class="cp-utilities-item__top">
                  <div class="cp-utilities-item__heading">
                    @if (!empty($item['name']))
                      <h4 class="cp-utilities-item__name">{{ $item['name'] }}</h4>
                    @endif
                    @if ($key === 'room_area')
                      <p class="cp-utilities-item__meta">
                        {{ $item['room_count'] }} {{ \Illuminate\Support\Str::plural('room', $item['room_count']) }}
                        @if ($item['total_sqft_label'])
                          · {{ $item['total_sqft_label'] }} total
                        @endif
                        @if ($item['updated_label'])
                          · Updated {{ $item['updated_label'] }}
                        @endif
                      </p>
                    @elseif ($key === 'measurement')
                      <p class="cp-utilities-item__meta">
                        {{ $item['row_count'] }} {{ \Illuminate\Support\Str::plural('measurement', $item['row_count']) }}
                        @if ($item['updated_label'])
                          · Updated {{ $item['updated_label'] }}
                        @endif
                      </p>
                    @else
                      <p class="cp-utilities-item__meta">
                        @if (!empty($item['summary']))
                          {{ $item['summary'] }}
                        @else
                          Saved calculation
                        @endif
                        @if ($item['updated_label'])
                          · {{ $item['updated_label'] }}
                        @endif
                      </p>
                    @endif
                  </div>
                  <a href="{{ $item['open_url'] }}" class="admin-btn admin-btn--primary admin-btn--sm cp-utilities-item__open" @if ($key === 'room_area') target="_blank" rel="noopener" @endif>
                    <i class="fa fa-external-link" aria-hidden="true"></i> Open
                  </a>
                </div>
                @if ($key === 'room_area' && !empty($item['rooms']))
                  <ul class="cp-utilities-item__rooms">
                    @foreach ($item['rooms'] as $room)
                      <li>
                        <span>{{ $room['name'] }}</span>
                        <span class="cp-utilities-item__room-meta">
                          <span class="cp-utilities-item__room-area">{{ $room['area_label'] }}</span>
                          @if (!empty($room['photo_count']))
                            <span class="cp-utilities-item__room-photos">{{ $room['photo_count'] }} {{ \Illuminate\Support\Str::plural('photo', $room['photo_count']) }}</span>
                          @endif
                          @if (!empty($room['pdf_url']))
                            <a href="{{ $room['pdf_url'] }}" class="admin-btn admin-btn--ghost admin-btn--sm cp-utilities-item__pdf" target="_blank" rel="noopener">PDF</a>
                          @endif
                          @if (!empty($room['open_url']))
                            <a href="{{ $room['open_url'] }}" class="admin-btn admin-btn--ghost admin-btn--sm" target="_blank" rel="noopener">Open</a>
                          @endif
                        </span>
                      </li>
                    @endforeach
                  </ul>
                @endif
              </article>
            @endforeach
          </div>
        </div>
      @endif
    @endforeach
  @endif
</div>
