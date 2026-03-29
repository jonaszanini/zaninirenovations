@if ($paginator->hasPages())
  <nav class="admin-pagination-nav" role="navigation" aria-label="Pagination">
    @if ($paginator->onFirstPage())
      <span class="is-disabled">Previous</span>
    @else
      <a href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
    @endif

    @foreach ($elements as $element)
      @if (is_string($element))
        <span class="is-disabled">{{ $element }}</span>
      @endif

      @if (is_array($element))
        @foreach ($element as $page => $url)
          @if ($page == $paginator->currentPage())
            <span aria-current="page">{{ $page }}</span>
          @else
            <a href="{{ $url }}">{{ $page }}</a>
          @endif
        @endforeach
      @endif
    @endforeach

    @if ($paginator->hasMorePages())
      <a href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
    @else
      <span class="is-disabled">Next</span>
    @endif
  </nav>
@endif
