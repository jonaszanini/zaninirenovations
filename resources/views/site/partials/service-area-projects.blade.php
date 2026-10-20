@if (isset($projects) && $projects->isNotEmpty())
  <ul class="sa-projects">
    @foreach ($projects as $project)
      @php
        $cover = $project->coverImageUrl();
        $town = $project->townLabel();
      @endphp
      <li class="sa-project">
        <a class="sa-project__link" href="{{ $project->publicUrl() }}">
          @if ($cover)
            <img
              class="sa-project__img"
              src="{{ $cover }}"
              alt="{{ $project->title }}"
              width="640"
              height="400"
              loading="{{ $loop->first ? 'eager' : 'lazy' }}"
              decoding="async"
            >
          @endif
          <span class="sa-project__body">
            <span class="sa-project__title">{{ $project->title }}</span>
            @if ($town !== '' && ! \Illuminate\Support\Str::contains(strtolower($project->title), strtolower($town)))
              <span class="sa-project__town">{{ $town }}</span>
            @endif
          </span>
        </a>
      </li>
    @endforeach
  </ul>
@else
  <p class="sa-muted">Local project pages will appear here as they are published.</p>
@endif
