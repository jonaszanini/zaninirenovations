@if ($homeBlogFeatured)
<section id="blog" class="section-content-block section-secondary-bg home-blog-section" aria-labelledby="home-blog-heading">
  <div class="container wow fadeInUp">
    <div class="row section-heading-wrapper">
      <div class="col-md-12 col-sm-12 text-center">
        <h4 class="heading-alt-style text-uppercase text-dark-color">Blog</h4>
        <span class="heading-separator heading-separator-horizontal"></span>
        <h2 id="home-blog-heading" class="subheading-alt-style">
          Tips, project insights, and renovation advice from our team.
        </h2>
      </div>
    </div>

    <div class="row home-blog-featured-row">
      <div class="{{ $homeBlogMore->isNotEmpty() ? 'col-lg-7 col-md-12' : 'col-lg-8 col-lg-offset-2 col-md-12' }}">
        <article class="latest-blog theme-custom-box-shadow home-blog-featured">
          @if ($homeBlogFeatured->featuredImageUrl())
            <div class="latest-thumbnail">
              <a href="{{ route('blog.show', $homeBlogFeatured) }}">
                @php $homeBlogDims = $homeBlogFeatured->featuredImageDimensions(); @endphp
                <img src="{{ $homeBlogFeatured->featuredImageUrl() }}" alt="{{ $homeBlogFeatured->title }}" loading="lazy" decoding="async"
                  @if ($homeBlogDims) width="{{ $homeBlogDims['width'] }}" height="{{ $homeBlogDims['height'] }}" @endif>
              </a>
            </div>
          @endif
          <div class="news-content">
            <span class="home-blog-featured__label">Latest article</span>
            <h3 class="latest-title">
              <a href="{{ route('blog.show', $homeBlogFeatured) }}">{{ $homeBlogFeatured->title }}</a>
            </h3>
            <p class="single-post-meta">
              <i class="fa fa-user"></i> {{ $homeBlogFeatured->author_name }}
              @if ($homeBlogFeatured->categories->isNotEmpty())
                &nbsp;<i class="fa fa-book"></i>
                @foreach ($homeBlogFeatured->categories as $cat)
                  <a href="{{ route('blog.index', ['category' => $cat->slug]) }}">{{ $cat->name }}</a>@if (! $loop->last), @endif
                @endforeach
              @endif
              &nbsp;<i class="fa fa-calendar"></i> {{ $homeBlogFeatured->displayDateLong() }}
              &nbsp;<i class="fa fa-clock-o"></i> {{ $homeBlogFeatured->readTimeMinutes() }} min read
            </p>
            <p>{{ $homeBlogFeatured->displayExcerpt(260) }}</p>
            <a href="{{ route('blog.show', $homeBlogFeatured) }}" class="btn btn-theme">Read article</a>
          </div>
        </article>
      </div>

      @if ($homeBlogMore->isNotEmpty())
        <div class="col-lg-5 col-md-12">
          <div class="home-blog-more theme-custom-box-shadow">
            <h3 class="home-blog-more__title">More articles</h3>
            <ul class="home-blog-more__list">
              @foreach ($homeBlogMore as $post)
                <li class="home-blog-more__item">
                  <a href="{{ route('blog.show', $post) }}" class="home-blog-more__link">{{ $post->title }}</a>
                  <p class="home-blog-more__date">
                    <i class="fa fa-calendar"></i> {{ $post->displayDateLong() }}
                    &nbsp;<i class="fa fa-clock-o"></i> {{ $post->readTimeMinutes() }} min read
                  </p>
                </li>
              @endforeach
            </ul>
          </div>
        </div>
      @endif
    </div>

    <div class="row">
      <div class="col-sm-12 text-center">
        <a href="{{ route('blog.index') }}" class="btn btn-theme margin-top-24">View all posts</a>
      </div>
    </div>
  </div>
</section>
@endif
