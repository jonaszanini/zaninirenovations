@if ($paginator->hasPages())
  <ul class="pagination">
    @if ($paginator->onFirstPage())
      <li class="disabled"><span><i class="fa fa-caret-left"></i></span></li>
    @else
      <li><a href="{{ $paginator->previousPageUrl() }}" rel="prev"><i class="fa fa-caret-left"></i></a></li>
    @endif
    @foreach ($elements as $element)
      @if (is_string($element))
        <li class="disabled"><span>{{ $element }}</span></li>
      @endif
      @if (is_array($element))
        @foreach ($element as $page => $url)
          @if ($page == $paginator->currentPage())
            <li><a href="#" class="page-numbers current">{{ $page }}</a></li>
          @else
            <li><a href="{{ $url }}" class="page-numbers">{{ $page }}</a></li>
          @endif
        @endforeach
      @endif
    @endforeach
    @if ($paginator->hasMorePages())
      <li><a href="{{ $paginator->nextPageUrl() }}" rel="next" class="next page-numbers"><i class="fa fa-caret-right"></i></a></li>
    @else
      <li class="disabled"><span><i class="fa fa-caret-right"></i></span></li>
    @endif
  </ul>
@endif
