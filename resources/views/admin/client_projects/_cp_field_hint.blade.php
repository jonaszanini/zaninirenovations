{{--
  Legacy hint include — now the shared info-tip. Pass tipText or tipHtml only.
  @include is rewritten to AdminInfoTipInclude::renderHint($data). view() /
  ->with() still hit this file; renderFromView() isolates to the passed keys
  and applies the text/html guard.
--}}
{!! \App\Support\AdminInfoTipInclude::renderFromView(isset($__data) && is_array($__data) ? $__data : []) !!}
