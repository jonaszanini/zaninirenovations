{{-- Legacy hint include — now the shared info-tip. Pass tipText or tipHtml only. Never inherit parent $id/$label/$text/$html. --}}
@include('admin.partials.info_tip', [
  'tipText' => $tipText ?? null,
  'tipHtml' => $tipHtml ?? null,
  'tipId' => $tipId ?? null,
  'tipLabel' => $tipLabel ?? null,
])
