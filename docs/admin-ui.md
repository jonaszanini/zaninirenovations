# Admin UI — standing rules

Companion to `docs/UI_GUIDELINES.md`. Authenticated admin only.

## Help text uses the info-tip component

Static explanations, field hints, and muted helper paragraphs **must** use the shared info-tip — never an inline `.admin-form-help` paragraph that takes vertical space.

Canonical API (keep this path and slot usage so other PRs merge cleanly):

```blade
<label for="fq-style">
  Board style
  <x-admin.info-tip>Clear span between posts is at most the max spacing.</x-admin.info-tip>
</label>
```

Existing includes still work:

```blade
@include('admin.partials.info_tip', ['text' => 'Plain explanation.'])
```

HTML (links, `<code>`, `<strong>`) goes in the slot:

```blade
<x-admin.info-tip>
  Rates from <a href="{{ route('admin.quotations.shared-prices.edit') }}">Shared prices</a>.
</x-admin.info-tip>
```

Checkboxes: pass `$info` to `admin.partials.checkbox`. Form fields: pass `$help` to `admin.partials.form_field`. Dynamic JS rows: `AdminInfoTip.html('…')`.

### Behaviour

- Click/tap toggles a popover. No hover-only behaviour.
- Button is `type="button"` with `aria-label="More info"`, `aria-expanded`, and `aria-controls`.
- Popover has `role="tooltip"` and an `id`. The text stays in the DOM.
- Closes on Esc, outside click, or opening another tip.
- Event delegation (works on rows added after load).
- Popover stays in the viewport at 390px (`max-width: calc(100vw - 24px)`, flip/shift).
- Style with `--admin-*` tokens only. Do not add this to public-site CSS/JS.

### Keep visible (do not convert)

- Validation errors (`.admin-form-error`)
- Warnings that need action (catalogue re-price banner, missing email)
- Statuses, badges, live computed numbers/summaries
- Empty states
- Anything the user must see immediately to avoid a mistake

Assets: `resources/views/components/admin/info-tip.blade.php`, `public/js/admin-info-tip.js`, `public/css/admin.css` (`.admin-info-tip*`).
