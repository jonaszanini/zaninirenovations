{{-- Legacy hint include — now the shared info-tip. Pass $text or $html. Never inherit parent $id/$label. --}}
@include('admin.partials.info_tip', [
  'text' => $text ?? null,
  'html' => $html ?? null,
  'tipId' => $tipId ?? null,
  'tipLabel' => $tipLabel ?? null,
])
