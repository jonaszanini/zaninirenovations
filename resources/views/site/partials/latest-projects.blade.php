@forelse ($projects as $project)
  @php
    $eagerFirstCount = $eagerFirstCount ?? 0;
    $photos = $project->photos->sortBy('sort_order');
    $cover = $photos->first();
    $coverSrc = $cover ? $cover->url() : '/images/gallery_1.jpg';
    $coverDims = $cover
      ? $cover->dimensions()
      : \App\Support\PublicImageDimensions::of(public_path('images/gallery_1.jpg'));
    $lazy = $loop->index >= (int) $eagerFirstCount;
  @endphp
  <div
    class="col-md-3 col-sm-6 col-xs-12 js-latest-project-item"
    data-category-slugs="{{ $project->categories->pluck('slug')->implode(',') }}"
  >

    <div class="portfolio-container">

      <a
        href="{{ $project->publicUrl() }}"
        class="portfolio-light-box xs-margin text-center"
        title="{{ $project->title }}"
      >

        <figure class="portfolio-img">

          <img src="{{ $coverSrc }}" alt="{{ e($project->title) }}"
            @if ($coverDims) width="{{ $coverDims['width'] }}" height="{{ $coverDims['height'] }}" @endif
            @if ($lazy) loading="lazy" @endif
            decoding="async">

        </figure>
        <!-- end .cause-img  -->

        <div class="portfolio-info-box">
          <h4>{{ $project->title }}</h4>
          <span>{{ $project->categoryLabel() }}</span>
        </div>

      </a>
      @if ($project->townLabel() !== '')
        <p class="portfolio-card-town">{{ $project->townLabel() }}</p>
      @endif
      <!-- end .gallery-light-box  -->

    </div>

  </div>
  <!-- end .col-sm-3  -->
@empty
  <div class="col-md-12 col-sm-12 text-center" style="padding: 3rem 1rem;">
    <p class="text-dark-color" style="margin:0;font-size:1.1rem;">New projects will appear here soon.</p>
  </div>
@endforelse
