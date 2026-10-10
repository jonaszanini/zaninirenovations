# Admin UI — standing rules

Companion to `docs/UI_GUIDELINES.md`. Authenticated admin only.

## Help text uses the info-tip component

Static explanations, field hints, and muted helper paragraphs **must** use the shared info-tip — never an inline `.admin-form-help` paragraph that takes vertical space.

Canonical API (same path as any parallel fence PR — slot + `label`):

```blade
<span class="admin-label-with-tip">
  <label for="fq-style">Board style</label>
  <x-admin.info-tip label="Board style">Clear span between posts is at most the max spacing.</x-admin.info-tip>
</span>
```

`label` becomes `aria-label="About Board style"`. **Do not put the tip inside a `<label for>`** — the ⓘ must not become part of the input's accessible name. Headings may wrap the tip.

The include wrapper **does not** inherit parent `$text` / `$html` / `$id` / `$label`. Pass `tipText` / `tipHtml` / `tipLabel` / `tipId` only. The component `:text` attribute and slot still work:

```blade
@include('admin.partials.info_tip', ['tipText' => 'Plain explanation.', 'tipLabel' => 'Labour rate'])
<x-admin.info-tip :text="'Plain explanation.'" label="Labour rate" />
```

`admin.client_projects._cp_field_hint` and `search_select` `$labelHint` use the same keys (`tipText` / `tipHtml` / `tipLabel`). Do not pass `'text'` or `'html'` — those keys throw outside production (`AdminInfoTipInclude::MESSAGE`) on `@include`, `@includeFirst`, `view()`, and `->with()`, and CI fails the leftover scan.

`@include` of `admin.partials.info_tip` / `_cp_field_hint` is compiled to `AdminInfoTipInclude::render($data)` / `renderHint($data)` so parent `tipHtml` / `tipLabel` / `tipId` cannot leak (does not rely on the IsolatingFactory view override).

Field labels keep the last word and the ⓘ in `.admin-label-with-tip__end` (`white-space: nowrap`) so two-column grids stay aligned at 390px.

HTML (links, `<code>`, `<strong>`) goes in the slot (trusted Blade only):

```blade
<x-admin.info-tip label="Rates">
  Rates from <a href="{{ route('admin.quotations.shared-prices.edit') }}">Shared prices</a>.
</x-admin.info-tip>
```

Checkboxes: pass `$info` to `admin.partials.checkbox`. Form fields: pass `$help` to `admin.partials.form_field`. Dynamic JS rows: `AdminInfoTip.html('…', 'Length (feet)')`.

### Behaviour

- Click/tap toggles a popover. No hover-only behaviour. Clicking inside the bubble does not close it.
- Button is `type="button"` with `aria-label="About {label}"`, `aria-expanded`, `aria-controls`, and `aria-describedby` (all point at the bubble id).
- The bubble is a **sibling** of the button (never nested). `role="tooltip"`. Text stays in the DOM.
- Closes on Esc, outside click, or opening another tip.
- Event delegation (works on rows added after load).
- Popover stays in the viewport at 390px (`max-width: min(273px, calc(100vw - 24px))`, flip/shift). `z-index: 150` so it sits above the breakdown panel and sticky headers, below dialogs.
- Style with `--admin-*` tokens only. Do not add this to public-site CSS/JS.

### Keep visible (do not convert)

- Validation errors (`.admin-form-error`)
- Warnings that need action (catalogue re-price banner, missing email)
- Statuses, badges, live computed numbers/summaries
- Empty states
- Anything the user must see immediately to avoid a mistake

Assets: `resources/views/components/admin/info-tip.blade.php`, `public/js/admin-info-tip.js`, `public/css/admin.css` (`.admin-info-tip*`).
