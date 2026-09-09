{{-- TinyMCE for quotation "Project description" / work_description fields.
     Matches the standard quotation editor (toolbar, height, light/dark skins). --}}
@php
  $textareaId = $textareaId ?? 'work-description';
  $formId = $formId ?? null;
  $editorHeight = (int) ($editorHeight ?? 320);
@endphp

@once
  @push('head')
    <script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js"
            referrerpolicy="origin"></script>
  @endpush
@endonce

@push('scripts')
<script>
(function () {
  if (typeof tinymce === 'undefined') return;

  var selector = @json('#'.$textareaId);
  var formId = @json($formId);
  var height = {{ $editorHeight }};
  var isDark = document.documentElement.classList.contains('admin-dark');

  tinymce.init({
    selector: selector,
    skin: isDark ? 'oxide-dark' : 'oxide',
    content_css: isDark ? 'dark' : 'default',
    menubar: false,
    plugins: 'advlist autolink lists link table code preview searchreplace visualblocks wordcount paste',
    toolbar:
      'undo redo | blocks | bold italic underline | ' +
      'alignleft aligncenter alignright | bullist numlist outdent indent | ' +
      'link table | removeformat code',
    height: height,
    branding: false,
    promotion: false,
    paste_as_text: false,
    content_style: isDark
      ? 'body { font-family: Arial, Helvetica, sans-serif; font-size: 12pt; color: #e2e8f0; background: #0f172a; line-height: 1.55; padding: 12px; } ' +
        'h1, h2, h3, h4 { color: #7dd3fc; margin: 0.6em 0 0.35em; } ' +
        'a { color: #38bdf8; } ' +
        'p { margin: 0 0 0.65em; } ' +
        'ul, ol { margin: 0 0 0.65em 1.25em; }'
      : 'body { font-family: Arial, Helvetica, sans-serif; font-size: 12pt; color: #1f2937; line-height: 1.55; padding: 12px; } ' +
        'h1, h2, h3, h4 { color: #024768; margin: 0.6em 0 0.35em; } ' +
        'p { margin: 0 0 0.65em; } ' +
        'ul, ol { margin: 0 0 0.65em 1.25em; }',
    setup: function (editor) {
      editor.on('change keyup undo redo', function () {
        editor.save();
      });
    },
  });

  if (formId) {
    var form = document.getElementById(formId);
    if (form) {
      form.addEventListener('submit', function () {
        if (typeof tinymce !== 'undefined') {
          tinymce.triggerSave();
        }
      });
    }
  }
})();
</script>
@endpush
