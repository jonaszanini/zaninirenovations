{{-- Legacy hint include — now the shared info-tip. Pass $text or $html. --}}
@include('admin.partials.info_tip', ['text' => $text ?? null, 'html' => $html ?? null, 'id' => $id ?? null])
