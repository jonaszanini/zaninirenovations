<script>
  document.querySelectorAll('[data-password-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var field = btn.closest('.admin-password-field');
      if (!field) { return; }
      var input = field.querySelector('input');
      if (!input) { return; }
      var icon = btn.querySelector('i');
      var reveal = input.type === 'password';
      input.type = reveal ? 'text' : 'password';
      btn.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
      btn.setAttribute('aria-pressed', reveal ? 'true' : 'false');
      if (icon) {
        icon.classList.toggle('fa-eye', !reveal);
        icon.classList.toggle('fa-eye-slash', reveal);
      }
    });
  });
</script>
