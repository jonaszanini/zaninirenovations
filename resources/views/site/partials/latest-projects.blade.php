@forelse ($projects as $project)
  @php
    $photos = $project->photos->sortBy('sort_order');
    $galleryUrls = $photos->isNotEmpty()
      ? $photos->map(fn ($p) => $p->url())->values()->all()
      : ['/images/gallery_1.jpg'];
    $cover = $photos->first();
    $coverSrc = $cover ? $cover->url() : '/images/gallery_1.jpg';
  @endphp
  <div class="col-md-3 col-sm-6 col-xs-10 col-xs-offset-1 col-sm-offset-0 col-md-offset-0">
    <div class="portfolio-container">
      <a
        href="#"
        class="portfolio-light-box text-center js-project-gallery"
        data-project-title="{{ e($project->title) }}"
        data-gallery='@json($galleryUrls)'
      >
        <figure class="portfolio-img">
          <img src="{{ $coverSrc }}" alt="{{ e($project->title) }}">
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
