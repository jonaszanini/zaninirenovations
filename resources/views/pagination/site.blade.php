@if ($paginator->hasPages())
  <nav aria-label="Blog pagination">
    <ul class="pagination">
      @if ($paginator->onFirstPage())
        <li class="disabled"><span aria-hidden="true"><i class="fa fa-caret-left"></i></span><span class="sr-only">Previous page</span></li>
      @else
        <li><a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page"><i class="fa fa-caret-left" aria-hidden="true"></i></a></li>
      @endif
      @foreach ($elements as $element)
        @if (is_string($element))
          <li class="disabled"><span>{{ $element }}</span></li>
        @endif
        @if (is_array($element))
          @foreach ($element as $page => $url)
            @if ($page == $paginator->currentPage())
              <li><span class="page-numbers current" aria-current="page">{{ $page }}</span></li>
            @else
              <li><a href="{{ $url }}" class="page-numbers" aria-label="Page {{ $page }}">{{ $page }}</a></li>
            @endif
          @endforeach
        @endif
      @endforeach
      @if ($paginator->hasMorePages())
        <li><a href="{{ $paginator->nextPageUrl() }}" rel="next" class="next page-numbers" aria-label="Next page"><i class="fa fa-caret-right" aria-hidden="true"></i></a></li>
      @else
        <li class="disabled"><span aria-hidden="true"><i class="fa fa-caret-right"></i></span><span class="sr-only">Next page</span></li>
      @endif
    </ul>
  </nav>
@endif
