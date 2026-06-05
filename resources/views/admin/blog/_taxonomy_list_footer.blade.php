@if ($items->total() > 0)
  <div class="admin-list-footer">
    <p class="admin-form-help admin-list-footer__summary">
      Showing {{ $items->firstItem() }}–{{ $items->lastItem() }} of {{ number_format($items->total()) }}
    </p>
    @if ($items->hasPages())
      {{ $items->links('pagination.admin') }}
    @endif
  </div>
@endif
