<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Zanini Renovations Blog</title>
    <link>{{ route('blog.index') }}</link>
    <description>Renovation tips and news from Zanini Renovations</description>
    <language>en-ca</language>
    @foreach ($posts as $post)
      <item>
        <title>{{ $post->title }}</title>
        <link>{{ $post->publicUrl() }}</link>
        <guid isPermaLink="true">{{ $post->publicUrl() }}</guid>
        <description><![CDATA[{{ $post->displayExcerpt(500) }}]]></description>
        <pubDate>{{ ($post->published_at ?? $post->created_at)->toRfc2822String() }}</pubDate>
      </item>
    @endforeach
  </channel>
</rss>
