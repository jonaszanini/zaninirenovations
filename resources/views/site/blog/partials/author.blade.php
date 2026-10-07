<aside class="blog-author" aria-label="About the author">
  <p class="blog-author__name">{{ $post->author_name ?: config('blog.author_name') }}</p>
  <p class="blog-author__role">Owner, Zanini Renovations</p>
  <p class="blog-author__bio">{{ config('seo.service_area_text') }}</p>
</aside>
