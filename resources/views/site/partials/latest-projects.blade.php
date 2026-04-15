@forelse ($projects as $project)
  @php
    $photos = $project->photos->sortBy('sort_order');
    $galleryUrls = $photos->isNotEmpty()
      ? $photos->map(fn ($p) => $p->url())->values()->all()
      : ['/images/gallery_1.jpg'];
    $cover = $photos->first();
    $coverSrc = $cover ? $cover->url() : '/images/gallery_1.jpg';
  @endphp
  <div
    class="col-md-3 col-sm-6 col-xs-12 portfolio-container js-latest-project-item"
    data-category-slugs="{{ $project->categories->pluck('slug')->implode(',') }}"
  >

    <a
      href="#"
      class="portfolio-light-box xs-margin text-center js-project-gallery"
      data-project-title="{{ e($project->title) }}"
      data-gallery='@json($galleryUrls)'
    >

      <figure class="portfolio-img">

        <img src="{{ $coverSrc }}" alt="{{ e(\Illuminate\Support\Str::limit($project->title.' — Rough Carpentry Contractors; Finish Carpentry Services Ontario; O.Reg. 332/12 compliant portfolio', 200)) }}" loading="lazy" decoding="async">

      </figure>
      <!-- end .cause-img  -->

      <div class="portfolio-info-box">
        <h4>{{ $project->title }}</h4>
        <span>{{ $project->categoryLabel() }}</span>
      </div>

    </a>
    <!-- end .gallery-light-box  -->

  </div>
  <!-- end .col-sm-3  -->
@empty
  <div class="col-md-12 col-sm-12 text-center" style="padding: 3rem 1rem;">
    <p class="text-dark-color" style="margin:0;font-size:1.1rem;">New projects will appear here soon.</p>
  </div>
@endforelse
