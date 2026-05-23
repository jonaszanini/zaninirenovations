@php
  $mapsKey = config('google_maps.api_key');
  $mapsConfigured = filled($mapsKey);
@endphp
<script>
  window.ZR_GOOGLE_MAPS = {
    configured: @json($mapsConfigured),
    apiKey: @json($mapsConfigured ? $mapsKey : null)
  };
</script>
<script src="{{ asset('js/admin-google-maps.js') }}?v={{ @filemtime(public_path('js/admin-google-maps.js')) ?: time() }}" defer></script>
