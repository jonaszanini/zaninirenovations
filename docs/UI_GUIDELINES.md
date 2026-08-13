# Zanini Renovations — Layout & UI Guidelines

Living design-system reference for **public site** and **admin panel**. All new pages, features, and Blade partials must follow these patterns.

**Related:** `Template/` HTML is the source of truth for public theme markup (see `.cursor/rules/template-source.mdc`).

---

## 1. Design principles

1. **Consistency over novelty** — reuse existing classes before inventing new ones.
2. **Compact horizontal layout** — when creating new items, forms, or admin pages, **put related fields on the same row** whenever they fit at desktop width. Prefer multi-column grids over stacking full-width rows. Goal: shorter pages, less scrolling. Stack to one column only at breakpoints (see §5–6), not by default.
3. **Mobile-first admin** — every table, form, and toolbar must work at ≤900px (drawer nav breakpoint).
4. **No inline styles** — use CSS classes and design tokens; inline `style=""` is only allowed for one-off PDF/email templates.
5. **Accessible by default** — visible labels, `aria-*` on icon-only controls, 44px minimum touch targets.
6. **Brand alignment** — primary `#024768` on both public and admin surfaces.

---

## 2. Color system

### Shared brand tokens

| Token | Hex | Usage |
|-------|-----|--------|
| Primary | `#024768` | Buttons, links, headers, active nav |
| Primary hover | `#013251` / `#013a52` | Hover states |
| Body text | `#373435` (admin) / `#3a4856` (public) | Paragraphs, headings |
| Muted text | `#5a6570` (admin) / `#737373` (public) | Labels, meta, help text |
| Surface bg | `#edf0f2` | Page background |
| Surface card | `#ffffff` | Cards, panels |
| Border | `#d8dee4` | Inputs, cards, dividers |
| Success | `#2e7d4a` | Flash success, paid status |
| Danger | `#c62828` | Errors, destructive actions |
| Warning | `#9a3412` on `#fff7ed` | Warnings, pending states |

### Admin CSS variables (`public/css/admin.css`)

Always prefer `var(--admin-*)` in new admin CSS. Do not hard-code slate grays (`#475569`, `#cbd5e1`) in new rules — use `--admin-border`, `--admin-muted`, etc.

### Public theme

Colors live in `public/css/styles.css`. Section backgrounds: `.section-primary-bg`, `.section-secondary-bg`, `.section-pure-white-bg`.

---

## 3. Typography

### Admin

| Element | Spec |
|---------|------|
| Font stack | `"Segoe UI", system-ui, -apple-system, sans-serif` (`--admin-font`) |
| Body | 15px / 1.5 line-height |
| Page title (topbar `h1`) | `clamp(1.15rem, 2.5vw, 1.35rem)`, weight 600 |
| Form labels | 0.8rem, uppercase, weight 600, `--admin-muted` |
| Table headers | Uppercase, 0.75rem, letter-spacing 0.06em |
| Monospace (SKU, code) | `.admin-code` |

### Public

| Element | Spec |
|---------|------|
| Font | Open Sans (loaded in layout) |
| Body | 16px / 28px |
| Section heading | `.heading-alt-style` — 36px, weight 800 |
| Subheading | `.subheading-alt-style` — 18px |
| Section title block | `.section-heading-wrapper h2` — 48px |

### Rules

- One `h1` per page (admin: topbar; public: hero or `page-warranty-h1`).
- Do not skip heading levels for styling — adjust size with classes.
- Max readable line length for prose: ~65ch (use `.warranty-prose`, `max-width` utilities).

---

## 4. Spacing scale

Use this scale everywhere (rem-based). Avoid arbitrary pixel values (`14px`, `16px` margins in Blade).

| Token | Value | Typical use |
|-------|-------|-------------|
| `xs` | `0.25rem` (4px) | Tight icon gaps |
| `sm` | `0.5rem` (8px) | Inline badge padding |
| `md` | `0.75rem` (12px) | Button gaps, toolbar `gap` |
| `base` | `1rem` (16px) | Default inline spacing |
| `lg` | `1.25rem` (20px) | Form group margin-bottom, flash margin |
| `xl` | `1.5rem` (24px) | Card padding (mobile), form actions margin-top |
| `2xl` | `2rem` (32px) | Card padding (desktop), section gaps |
| `3xl` | `3rem` (48px) | Empty state padding |

### Public section spacing

- `.section-content-block` → `padding: 90px 0 100px` (reduces at `max-width: 767px`).
- Utility classes: `.margin-top-24`, `.margin-bottom-48`, etc. (Template helpers).

### Admin layout spacing

- `.admin-content` → `1.5rem` padding (mobile), `1.75rem 2rem` (≥768px).
- `.admin-form-group` → `margin-bottom: 1.25rem`.
- `.admin-form-actions` → `margin-top: 1.5rem`, `gap: 0.75rem`.
- `.admin-toolbar` → `margin-bottom: 1.25rem`, `gap: 0.75rem`.

---

## 5. Admin layout shell

Every authenticated admin page:

```blade
@extends('admin.layouts.app')

@section('heading', 'Page Title')

@push('head')
  {{-- Page-specific CSS only --}}
  <link rel="stylesheet" href="{{ asset('css/admin-example.css') }}?v={{ filemtime(...) }}">
@endpush

@section('content')
  {{-- content --}}
@endsection
```

### Page structure (top → bottom)

1. **Flash messages** (layout handles session flash; page-level alerts use `.admin-flash`)
2. **Toolbar** — `.admin-toolbar` with lede + primary action
3. **Filters** (if list page) — `.cp-filters` or `.admin-list-filters`
4. **Main surface** — `.admin-card` wrapping table, form, or grid
5. **Pagination** — `@include('pagination.admin')` inside `.admin-pagination` wrapper

### Breakpoints

| Width | Behavior |
|-------|----------|
| ≤900px | Sidebar drawer, hamburger, `overflow-x: hidden` on body |
| ≥901px | Fixed sidebar, backdrop hidden |
| ≤700px | `.admin-form-grid-2` → single column |
| ≤768px | `.admin-form-grid-3` → single column |

---

## 6. Admin components

### Cards

```html
<!-- Full-width list / table -->
<div class="admin-card">
  <div class="admin-table-scroll">
    <table class="admin-table">...</table>
  </div>
</div>

<!-- Narrow create/edit form (max 720px) -->
<div class="admin-card admin-card--narrow">
  <form>...</form>
</div>

<!-- Wide editor (blog, AI tools) -->
<div class="admin-card admin-card--wide">
  ...
</div>
```

### Buttons

Base: `.admin-btn` uses `--admin-control-height` (44px) — **the same height as `.admin-input`**. **Only use documented modifiers:**

| Class | Purpose |
|-------|---------|
| `.admin-btn--primary` | Main action (Save, Create, Submit) |
| `.admin-btn--secondary` | Secondary filled action (Refresh, Remind) |
| `.admin-btn--ghost` | Cancel, Back, low-emphasis |
| `.admin-btn--outline` | Emphasized secondary (Pick on calendar) |
| `.admin-btn--danger` | Delete, irreversible |
| `.admin-btn--sm` | Tables, toolbars, filter bars — **same height as fields**, tighter horizontal padding |
| `.admin-btn--lg` | Hero CTAs (contract sign, generate) — intentionally taller |

**Rules:**
- Buttons beside inputs/selects must use `.admin-btn` (or `--sm`); never a taller one-off class.
- In filter bars (`.cp-filters`, `.admin-list-filters`), put action buttons in `.cp-filter--actions` — not in the same flex row as a labeled field (prevents stretch).
- Do **not** set a custom `min-height` on buttons unless it matches `--admin-control-height`.

```html
<a href="..." class="admin-btn admin-btn--ghost admin-btn--sm">
  <i class="fa fa-arrow-left" aria-hidden="true"></i> Back
</a>
<button type="submit" class="admin-btn admin-btn--primary">Save</button>
```

**Do not** create one-off button classes (e.g. `blog-admin-gemini-btn`) without adding them to `admin.css` with documented variants.

Icon-only buttons **must** have `aria-label`.

### Flash / alerts

| Class | Use |
|-------|-----|
| `.admin-flash--success` | Saved, synced, paid |
| `.admin-flash--error` | Validation / fatal errors |
| `.admin-flash--warning` | Caution, incomplete state |
| `.admin-flash--danger` | Alias of error (prefer `--error` for new code) |
| `.admin-flash--info` | Neutral information |

```html
<div class="admin-flash admin-flash--info" role="status">
  Linked to project #123.
</div>
```

### Forms

**Every** `input`, `select`, and `textarea` must have **`class="admin-input"`** (or `@class(['admin-input', ...])`).  
Styling is defined once in `public/css/admin.css`. Do not add field CSS in page-specific stylesheets.

```html
<div class="admin-form-group">
  <label for="field_id">Field label <span class="cp-required">*</span></label>
  <input type="text" id="field_id" name="field" value="{{ old('field') }}"
         @class(['admin-input', 'is-invalid' => $errors->has('field')])>
  @error('field')
    <p class="admin-form-error">{{ $message }}</p>
  @enderror
  <p class="admin-form-help">Optional helper text.</p>
</div>
```

Toolbar / filter (no label wrapper):

```html
<input type="search" name="q" class="admin-input" placeholder="Search…">
<select name="category" class="admin-input">…</select>
```

**Shortcut partial** for new labeled fields:

```blade
@include('admin.partials.form_field', [
    'label' => 'Project name',
    'name' => 'name',
    'required' => true,
    'value' => old('name'),
])
```

**Rules:**

- Always use `<label for="...">` matching input `id` (except toolbar-only controls).
- Always **`admin-input`** on the control — never rely on bare tags inside `.admin-form-group`.
- Validation errors: **`.admin-form-error`**.
- Help text: **`.admin-form-help`**.
- Checkboxes: `@include('admin.partials.checkbox', [...])` — do **not** use `admin-input` on checkboxes.
- Searchable dropdowns: `@include('admin.partials.search_select', [...])` — do **not** add Tom Select.
- Required fields: `<span class="cp-required">*</span>`.
- Compact toolbars may override height via context (e.g. `.mat-toolbar-search .admin-input` at 38px).

### Form grids

**Default mindset:** group fields that belong together on **one line** (2–4+ columns) before adding another row. Full-width rows are for long text (URLs, descriptions, rich text), not for short dropdowns, numbers, SKUs, or toggles.

```html
<div class="admin-form-grid-2">
  <div class="admin-form-group">...</div>
  <div class="admin-form-group">...</div>
</div>
```

Three columns (vehicles): `.admin-form-grid-3` in `admin-vehicles.css`.

**Patterns in production (copy before inventing):**

| Pattern | Where | Use |
|---------|--------|-----|
| `.admin-form-grid-2` | `admin-client-projects.css` | Two equal columns (category + vendor, URLs side by side) |
| `.mat-form-grid-details` | `admin-materials.css` | Eight compact fields on one row (material edit) |
| `.mat-form-url-row` | `admin-materials.css` | Label row + control row: Product URL · Image URL · Active |
| `.fq-catalog-picker-wrap` | `admin-client-projects.css` | Icon + search-select on one line (fence / low-deck config) |
| Search-select | `admin.partials.search_select` | Type-in-field combobox for clients, catalog, projects — never Tom Select |

**Rules:**

- Align controls on a shared grid row (labels row 1, inputs row 2) when mixing text fields with checkboxes or status badges.
- Move helper text **below** a grouped row, or omit it when the label is obvious — do not let help text push the next field to a new line.
- Use `minmax(0, 1fr)` in CSS grid so columns shrink instead of wrapping prematurely.
- Page-specific grid classes belong in the page CSS file (`admin-materials.css`, `admin-client-projects.css`), not scattered inline.

### Form actions

```html
<div class="admin-form-actions">
  <button type="submit" class="admin-btn admin-btn--primary">Save</button>
  <a href="..." class="admin-btn admin-btn--ghost">Cancel</a>
</div>
```

### Tables

```html
<div class="admin-card">
  <div class="admin-table-scroll">
    <table class="admin-table">
      <thead>
        <tr><th scope="col">Name</th></tr>
      </thead>
      <tbody>...</tbody>
    </table>
  </div>
</div>
```

- Always wrap tables in `.admin-table-scroll` inside cards.
- Domain modifiers: `.cp-table`, `.mat-table`, `.dash-table` — do not mix unnecessarily.
- Empty state: `.admin-empty` with message + primary action.

### Toolbars

```html
<div class="admin-toolbar">
  <div class="admin-toolbar-lede">
    <p>Short description of this screen.</p>
  </div>
  <div class="admin-toolbar-actions">
    <a href="..." class="admin-btn admin-btn--primary">
      <i class="fa fa-plus" aria-hidden="true"></i> New item
    </a>
  </div>
</div>
```

---

## 7. Public site layout

### Master layout

All public pages extend `site.layouts.public` with `@section('page_title')`, meta sections, and optional `@push('styles')`.

### Section pattern

```html
<section id="section-id" class="section-content-block section-secondary-bg">
  <div class="container">
    <div class="row">
      <div class="col-lg-12">
        <div class="section-heading-wrapper">
          <h2 class="heading-alt-style">Section Title</h2>
          <p class="subheading-alt-style">Subtitle</p>
          <span class="heading-separator-horizontal"></span>
        </div>
      </div>
    </div>
    <!-- content -->
  </div>
</section>
```

### Public forms

Use Bootstrap 3 patterns: `.form-group`, `.form-control`, `.btn.btn-theme`.

**Every input must have a visible `<label>`** (can use `.sr-only` for visually hidden but screen-reader accessible):

```html
<div class="form-group">
  <label for="user_name" class="sr-only">Your name</label>
  <input type="text" class="form-control" id="user_name" name="user_name"
         placeholder="Your name" required>
</div>
```

Submit: `.btn.btn-theme` or `.btn.btn-theme.btn-custom`.

### Public breakpoints

| Width | Notes |
|-------|-------|
| ≤991px | Mobile nav drawer (white menu), top bar hidden |
| ≤767px | Reduced section padding, stacked columns |
| Hash anchors | `scroll-margin-top: 100px` on `#home`, `#services`, etc. |

---

## 8. CSS file organization

### Admin — load only what you need

| File | Scope |
|------|-------|
| `admin.css` | Global (always via layout) |
| `admin-client-projects.css` | Project/client/quotation routes only (loaded conditionally in layout) |
| `admin-blog.css` | Blog admin routes only (loaded conditionally in layout) |
| `admin-dashboard.css` | Dashboard only (`@push`) |
| `admin-materials.css` | Materials pages (`@push`) |
| `admin-vehicles.css` | Vehicles + commute (`@push`) |
| `admin-utilities.css` | Calculator tools (`@push`) |

**New feature CSS:** create `admin-{feature}.css`, load via `@push('head')` on that feature's views only.

### Public

| File | Scope |
|------|-------|
| `styles.css` | Global theme + Zanini overrides |
| `blog.css` | Blog index/show only |

Add Zanini-specific public overrides to the bottom of `styles.css` (or a dedicated `site-{feature}.css` if large).

---

## 9. Accessibility checklist

Before merging UI work:

- [ ] All form fields have associated `<label>` or `aria-label`
- [ ] Icon-only links/buttons have accessible names
- [ ] Mobile menu toggle has `aria-expanded` and `aria-label`
- [ ] Focus states visible on inputs and buttons
- [ ] Color contrast ≥ 4.5:1 for body text (watch `#a6a6a6` on white)
- [ ] Tables use `<th scope="col">`
- [ ] Modals/dialogs: `role="dialog"`, `aria-labelledby`, focus trap
- [ ] Live regions for AJAX feedback (`aria-live="polite"`)
- [ ] Touch targets ≥ 44×44px on mobile

---

## 10. Anti-patterns (do not)

| Avoid | Use instead |
|-------|-------------|
| `style="margin-bottom:16px"` on flashes | `.admin-flash` (has margin) or utility class |
| `admin-flash--danger` without CSS | `admin-flash--error` or add CSS for alias |
| `admin-btn--secondary` without CSS | Define in `admin.css` or use `--ghost` |
| Placeholder-only labels (public contact) | `<label>` + placeholder |
| Bare `admin-table-wrap` without scroll | `admin-card` + `admin-table-scroll` |
| New button class per page | Extend `admin-btn` modifiers |
| Loading 5000-line CSS on every page | `@push` feature CSS |
| Mixing `admin-error` and `admin-form-error` | `admin-form-error` only |
| `hidden-xs` without testing at 320px | Test iPhone SE width |

---

## 11. New page checklist

### Admin list page

1. `@extends('admin.layouts.app')`
2. `@section('heading')`
3. `.admin-toolbar` with create action
4. Optional `.cp-filters` / search with `.admin-input`
5. `.admin-card` > `.admin-table-scroll` > `.admin-table`
6. `@include('pagination.admin')`
7. Page CSS via `@push` if needed

### Admin form page

1. `.admin-card.admin-card--narrow` (or `--wide` for rich editors)
2. `<form>` with `.admin-form-group` fields
3. `@csrf`, `@error` blocks
4. `.admin-form-actions` at bottom
5. Primary + ghost buttons only

### Public content page

1. `@extends('site.layouts.public')`
2. Match `warranty.blade.php` or `blog/show.blade.php` structure
3. Use Template section classes
4. `@push('styles')` only when necessary

### Test templates

Copy from `tests/Templates/` for backend; for UI, copy nearest neighbor Blade file from the same area.

---

## 12. Quick reference

```
Admin shell:     admin-body → admin-app → admin-sidebar + admin-main
Surfaces:        admin-card | admin-card--narrow | admin-card--wide
Forms:           admin-form-group → label + .admin-input + admin-form-error
Grids:           admin-form-grid-2 | admin-form-grid-3
Actions:         admin-btn + --primary|--secondary|--ghost|--danger|--sm|--lg
Data:            admin-table-scroll → admin-table
Feedback:        admin-flash--success|error|warning|info
Public sections: section-content-block → container → row → col-*
Public buttons:  btn btn-theme
Brand primary:   #024768
```

---

*Last updated: June 2026. Update this document when adding new global components or CSS variables.*
