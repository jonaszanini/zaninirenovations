@forelse ($projects as $project)
  @php
    $cover = $project->photos->sortBy('sort_order')->first();
    $coverSrc = $cover ? $cover->url() : '/images/gallery_1.jpg';
  @endphp
  <div class="col-md-3 col-sm-6 col-xs-10 col-xs-offset-1 col-sm-offset-0 col-md-offset-0">
    <div class="portfolio-container">
      <a class="portfolio-light-box text-center venobox" href="{{ $coverSrc }}" title="{{ $project->title }}">
        <figure class="portfolio-img">
          <img src="{{ $coverSrc }}" alt="{{ $project->title }}">
        </figure>
        <div class="portfolio-info-box">
          <h4>{{ $project->title }}</h4>
          <span>{{ $project->categoryLabel() }}</span>
        </div>
      </a>
    </div>
  </div>
@empty
  <div class="col-md-12 col-sm-12 text-center" style="padding: 3rem 1rem;">
    <p class="text-dark-color" style="margin:0;font-size:1.1rem;">New projects will appear here soon.</p>
  </div>
@endforelse
