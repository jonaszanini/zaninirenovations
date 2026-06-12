@once
  @push('scripts')
    <script src="{{ asset('js/admin-ai-text-history.js') }}?v={{ @filemtime(public_path('js/admin-ai-text-history.js')) ?: time() }}"></script>
  @endpush
@endonce
