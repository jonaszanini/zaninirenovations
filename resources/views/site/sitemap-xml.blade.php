<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>{{ url('/') }}</loc>
    <lastmod>{{ now()->toAtomString() }}</lastmod>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
  </url>
  <url>
    <loc>{{ url('/warranty') }}</loc>
    <lastmod>{{ now()->toAtomString() }}</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
  </url>
  <url>
    <loc>{{ url('/privacy-policy') }}</loc>
    <lastmod>{{ now()->toAtomString() }}</lastmod>
    <changefreq>yearly</changefreq>
    <priority>0.5</priority>
  </url>
  <url>
    <loc>{{ url('/terms-and-conditions') }}</loc>
    <lastmod>{{ now()->toAtomString() }}</lastmod>
    <changefreq>yearly</changefreq>
    <priority>0.5</priority>
  </url>
  <url>
    <loc>{{ url('/blog') }}</loc>
    <lastmod>{{ now()->toAtomString() }}</lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.85</priority>
  </url>
  @foreach ($blogPosts ?? [] as $blogPost)
    <url>
      <loc>{{ route('blog.show', $blogPost->slug) }}</loc>
      <lastmod>{{ ($blogPost->updated_at ?? now())->toAtomString() }}</lastmod>
      <changefreq>monthly</changefreq>
      <priority>0.7</priority>
    </url>
  @endforeach
</urlset>
