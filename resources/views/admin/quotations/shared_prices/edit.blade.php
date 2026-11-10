@extends('admin.layouts.app')

@section('heading', 'Shared prices')

@push('head')
<style>
/* Crew rates: compact inline row */
.sp-crew { margin-bottom: 1.5rem; }
.sp-crew .sp-crew-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem 1.5rem;
  align-items: flex-end;
}
.sp-crew .sp-crew-field {
  flex: 0 0 auto;
}
.sp-crew .sp-crew-field label {
  display: block;
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: var(--admin-muted);
  margin-bottom: 4px;
}
.sp-crew .sp-crew-field .admin-input {
  width: 110px;
  padding: 0.35rem 0.5rem;
  font-size: 0.85rem;
  height: 34px;
}
.sp-heading { display: block; margin: 0 0 0.5rem; font-size: 1.1rem; }
.sp-group {
  --sp-grid-columns:
    minmax(11rem, 2.8fr)
    minmax(5.5rem, 1fr)
    minmax(6.6rem, 1.05fr)
    minmax(3.5rem, 0.75fr)
    minmax(5.5rem, 1fr)
    minmax(5.25rem, 0.95fr)
    2.75rem
    minmax(5.5rem, 1fr);
  margin-bottom: 1rem;
}
/* Desktop grid from 68.75em (1100px at 16px root). Larger browser fonts
   stay on stacked cards longer so rem tracks cannot blow the page width.
   If the grid is still wider than the card, scroll inside the card. */
@media (min-width: 68.75em) and (max-width: 75em) {
  .admin-content:has(.sp-table) {
    padding-left: 0.65rem;
    padding-right: 0.65rem;
  }
}
details.sp-group.admin-card {
  padding: 1rem 1.25rem 1.25rem;
}
@media (min-width: 768px) {
  details.sp-group.admin-card {
    padding: 1.25rem 1.5rem 1.5rem;
  }
}
@media (min-width: 68.75em) {
  .sp-group .admin-table-scroll {
    overflow-x: auto;
    overflow-y: visible;
  }
}
.sp-group__summary {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  font-weight: 600;
  font-size: 1rem;
  padding: 0.75rem 0;
  cursor: pointer;
  color: var(--admin-heading);
}
.sp-group__title { min-width: 0; }
.sp-group__sell-tip { display: none; }
.sp-group__summary::-webkit-details-marker { display: none; }
.sp-group__summary::before {
  content: '▶';
  display: inline-block;
  margin-right: 8px;
  font-size: 0.7em;
  transition: transform 0.15s;
}
.sp-group[open] > .sp-group__summary::before { transform: rotate(90deg); }

/*
 * Desktop rows are CSS grid (not table-layout:fixed) so leftover width
 * goes to the wrapping work-item column, Rate is a fixed 6.6rem so
 * 99,999.9900 / 1,234.5678 fit, Sell and Mat. price fit 99,999.9999,
 * Mat. name is 7.5–8rem, and catalog / Used-by sit in separate columns.
 */
.sp-table {
  width: 100%;
  max-width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  font-size: 0.85rem;
}
.sp-table thead,
.sp-table tbody {
  display: block;
  width: 100%;
}
@media (min-width: 68.75em) {
  .sp-table thead tr,
  .sp-table tbody tr {
    display: grid;
    grid-template-columns: var(--sp-grid-columns);
    column-gap: 0.08rem;
    width: 100%;
    min-height: 44px;
  }
  .sp-table thead tr {
    align-items: end;
  }
  .sp-table tbody tr {
    align-items: start;
  }
  .sp-table th {
    display: flex;
    align-items: center;
    align-self: stretch;
    min-height: 36px;
    padding: 0.15rem 0.1rem 0.35rem;
  }
  .sp-table tbody td {
    padding: 0.35rem 0.1rem;
  }
  /* Beat `.sp-table td { display:block }` so the search button and chips
     flex-center on the same midline as the inputs. */
  .sp-table th.sp-col-catalog,
  .sp-table td.sp-col-catalog {
    display: flex;
    align-items: center;
    justify-content: center;
    align-self: stretch;
    vertical-align: middle;
    min-height: 44px;
    overflow: hidden;
  }
  .sp-table td.sp-col-catalog:focus-within {
    overflow: visible;
  }
  .sp-table th.sp-col-consumers,
  .sp-table td.sp-col-consumers {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    align-self: stretch;
    vertical-align: middle;
    padding-right: 0.75rem;
  }
  .sp-table .sp-consumers {
    flex-direction: row;
    flex-wrap: wrap;
    align-items: center;
    align-content: center;
    gap: 4px 6px;
  }
  .sp-table td.sp-col-label,
  .sp-table td.sp-col-rate,
  .sp-table td.sp-col-unit,
  .sp-table td.sp-col-price,
  .sp-table td.sp-col-name,
  .sp-table td.sp-col-sell {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    justify-content: flex-start;
    align-self: start;
    vertical-align: top;
  }
  .sp-table td.sp-col-catalog,
  .sp-table td.sp-col-consumers {
    align-self: center;
  }
  .sp-work-label-input,
  .sp-mat-name-input {
    min-height: 28px;
    height: auto;
    padding: 0.3rem 0.4rem;
    line-height: 1.25;
  }
}
.sp-table th,
.sp-table td {
  display: block;
  min-width: 0;
  padding: 0.4rem 0.1rem;
  border: 0;
  box-shadow: none;
  background-image: none;
  vertical-align: middle;
}
.sp-col-label,
.sp-col-name {
  overflow: hidden;
}
.sp-col-catalog {
  overflow: hidden;
}
.sp-col-catalog:focus-within,
.sp-col-consumers {
  overflow: visible;
}
.sp-table,
.sp-table thead,
.sp-table tbody,
.sp-table th,
.sp-table td {
  background: transparent;
  background-color: transparent;
}
.sp-table thead tr,
.sp-table tbody tr {
  background: transparent;
  box-shadow: inset 0 -1px 0 var(--admin-border);
}
.sp-table tr:hover > td,
.sp-table tr:hover > th {
  background: transparent;
}
.sp-table tbody tr:last-child { box-shadow: none; }
.sp-table th {
  text-align: left;
  font-size: 0.65rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--admin-muted);
  white-space: nowrap;
}
.sp-col-label,
.sp-col-rate,
.sp-col-sell,
.sp-col-unit,
.sp-col-price,
.sp-col-name,
.sp-col-catalog,
.sp-col-consumers {
  min-width: 0;
}
.sp-col-name .sp-input,
.sp-mat-name-input { width: 100%; max-width: none; box-sizing: border-box; }
.sp-col-name { position: relative; }
.sp-col-name:focus-within {
  overflow: visible;
  z-index: 14;
}
.sp-col-rate .sp-input,
.sp-col-sell .sp-input,
.sp-col-price .sp-input {
  width: 100%;
  min-width: 0;
  padding-left: 0.25rem;
  padding-right: 0.25rem;
  box-sizing: border-box;
  font-variant-numeric: tabular-nums;
}
.sp-col-unit .sp-input {
  width: 100%;
  min-width: 0;
  padding-left: 0.2rem;
  padding-right: 0.2rem;
  box-sizing: border-box;
}

.sp-col-label input[type="hidden"] { display: none !important; }
.sp-table .sp-work {
  padding: 0;
  border-top: 0;
}
.sp-work {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 0.1rem;
  width: 100%;
  min-width: 0;
  border: 0;
  box-shadow: none;
}
.sp-work-label {
  font-weight: 500;
  color: var(--admin-heading);
  font-size: 0.8rem;
  line-height: 1.25;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  overflow-wrap: break-word;
  word-break: break-word;
  white-space: normal;
}
.sp-work-label-input,
.sp-mat-name-input {
  display: block;
  width: 100%;
  min-width: 0;
  font-weight: 500;
  font-size: 0.8rem;
  line-height: 1.3;
  color: var(--admin-heading);
  background: var(--admin-input-bg);
  border: 1px solid var(--admin-border);
  border-radius: 5px;
  padding: 0.35rem 0.4rem;
  min-height: 2.75rem;
  height: auto;
  overflow-y: hidden;
  overflow-wrap: anywhere;
  word-break: break-word;
  white-space: pre-wrap;
  resize: none;
  box-sizing: border-box;
  field-sizing: content;
}
.sp-work-label-input:focus {
  outline: none;
  border-color: var(--admin-primary);
  box-shadow: 0 0 0 2px var(--admin-primary-ring);
}
.sp-work-extra {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  min-height: 1.25rem;
  margin-top: 0.1rem;
}
.sp-ref-badge {
  display: inline-flex;
  align-items: center;
  min-height: 18px;
  padding: 0 0.35rem;
  border: 1px solid var(--admin-border);
  border-radius: 3px;
  background: var(--admin-surface-hover);
  color: var(--admin-muted);
  font-size: 0.62rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  line-height: 1;
}
.sp-work-meta {
  display: block;
  font-weight: 400;
  font-size: 0.68rem;
  line-height: 1.2;
  color: var(--admin-muted);
  white-space: nowrap;
}

.sp-rate-empty {
  display: inline-flex;
  align-items: center;
  min-height: 28px;
  color: var(--admin-muted);
  font-size: 0.8rem;
}

.sp-col-sell {
  position: relative;
  flex-direction: column;
  align-items: stretch;
  justify-content: center;
  gap: 0.1rem;
}
.sp-sell-wrap {
  position: relative;
  width: 100%;
  min-width: 0;
}
.sp-col-sell .sp-input {
  padding-right: 0.25rem;
}
.sp-th-label,
.sp-th-sell,
.sp-sell-phone-label {
  display: inline-flex;
  align-items: center;
  flex-wrap: nowrap;
  white-space: nowrap;
  gap: 0.15rem;
  max-width: 100%;
  min-width: 0;
}
.sp-th-label .admin-info-tip-wrap,
.sp-th-sell .admin-info-tip-wrap {
  flex-shrink: 0;
}
.sp-sell-phone-label {
  display: none;
  font-size: 0.6rem;
  font-weight: 600;
  text-transform: uppercase;
  color: var(--admin-muted);
  margin-bottom: 2px;
}
.sp-th-sell .admin-info-tip-wrap,
.sp-sell-phone-label .admin-info-tip-wrap,
.sp-group__sell-tip .admin-info-tip-wrap,
.sp-heading .admin-info-tip-wrap,
.sp-work-extra .admin-info-tip-wrap,
.sp-add > summary .admin-info-tip-wrap {
  position: relative;
  display: inline-flex;
}
.sp-th-sell .admin-info-tip,
.sp-sell-phone-label .admin-info-tip,
.sp-group__sell-tip .admin-info-tip,
.sp-heading .admin-info-tip,
.sp-work-extra .admin-info-tip,
.sp-add > summary .admin-info-tip {
  position: relative;
  width: 28px;
  height: 28px;
  padding: 8px;
  margin: -8px;
  box-sizing: content-box;
  flex-shrink: 0;
}
.sp-th-sell .admin-info-tip__bubble,
.sp-sell-phone-label .admin-info-tip__bubble,
.sp-group__sell-tip .admin-info-tip__bubble,
.sp-heading .admin-info-tip__bubble,
.sp-work-extra .admin-info-tip__bubble,
.sp-add > summary .admin-info-tip__bubble {
  z-index: 80;
}
.sp-table tbody tr {
  position: relative;
  z-index: 0;
}
.sp-table tbody tr:has(.admin-info-tip[aria-expanded="true"]),
.sp-table tbody tr:has(.sp-search-wrap--hidden:focus-within),
.sp-table tbody tr:has(.sp-chip--more[aria-expanded="true"]) {
  z-index: 12;
}
.sp-effective {
  display: block;
  font-size: 0.68rem;
  line-height: 1.25;
  min-height: 1.25rem;
  margin-top: 0.1rem;
  color: var(--admin-muted);
  white-space: normal;
  overflow: visible;
  text-overflow: unset;
  overflow-wrap: break-word;
  word-break: normal;
}

.sp-col-catalog {
  position: relative;
  justify-content: center;
}
.sp-col-consumers {
  justify-content: flex-start;
}
.sp-consumers {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  align-content: center;
  justify-content: flex-start;
  gap: 4px 6px;
}
.sp-catalog-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  position: relative;
  width: 28px;
  height: 28px;
  padding: 0;
  margin: 0;
  box-sizing: border-box;
  border: 1px solid var(--admin-border);
  border-radius: 5px;
  background: var(--admin-input-bg);
  color: var(--admin-muted);
  cursor: pointer;
  font-size: 12px;
  transition: border-color 0.15s, background 0.15s;
}
.sp-catalog-btn::after {
  content: '';
  position: absolute;
  left: 50%;
  top: 50%;
  width: 44px;
  height: 44px;
  transform: translate(-50%, -50%);
}
.sp-catalog-btn:hover {
  border-color: var(--admin-primary);
  color: var(--admin-primary);
}
.sp-catalog-btn.is-selected {
  background: var(--admin-primary-soft);
  border-color: var(--admin-primary);
  color: var(--admin-primary);
}
.sp-search-wrap.sp-search-wrap--hidden {
  position: absolute;
  width: 200px;
  left: 0;
  right: auto;
  top: 0;
  height: 0;
  overflow: hidden;
  z-index: 100;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.1s;
}
@media (min-width: 68.75em) {
  .sp-search-wrap.sp-search-wrap--hidden {
    left: auto;
    right: 0;
  }
}
.sp-search-wrap--hidden:focus-within {
  top: 100%;
  height: auto;
  overflow: visible;
  opacity: 1;
  pointer-events: auto;
}

.sp-consumers {
  min-width: 0;
  width: 100%;
}
.sp-consumers:has(.sp-chip--more) .sp-chip:not(.sp-chip--more) {
  max-width: 100%;
}
@media (max-width: 414px) {
  .sp-col-consumers {
    min-width: 0;
    max-width: 100%;
    overflow: hidden;
  }
  .sp-consumers {
    min-width: 0;
    max-width: 100%;
  }
  .sp-consumers:has(.sp-chip--more) .sp-chip:not(.sp-chip--more) {
    max-width: min(140px, calc(100% - 52px));
  }
}
.sp-chip {
  display: inline-flex;
  align-items: center;
  justify-content: flex-start;
  box-sizing: border-box;
  flex: 0 0 auto;
  width: max-content;
  min-width: 0;
  max-width: 100%;
  min-height: 32px;
  padding: 0 0.4rem;
  font-size: 0.65rem;
  font-weight: 600;
  line-height: 1;
  text-align: left;
  background: var(--admin-primary-soft);
  color: var(--admin-primary);
  border: 1px solid var(--admin-primary);
  border-radius: 3px;
  white-space: nowrap;
  overflow: hidden;
}
.sp-chip__label {
  display: block;
  min-width: 0;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  line-height: 1.2;
}
.sp-chip--more {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  position: relative;
  flex: 0 0 auto;
  overflow: visible;
  max-width: none;
  min-width: 32px;
  min-height: 32px;
  padding: 0;
  margin: 0;
  box-sizing: border-box;
  cursor: pointer;
  font-family: inherit;
}
.sp-chip--more::after {
  content: '';
  position: absolute;
  left: 50%;
  top: 50%;
  width: 44px;
  height: 44px;
  transform: translate(-50%, -50%);
}
.sp-chip-more__tip {
  display: none;
  position: absolute;
  right: 0;
  left: auto;
  top: calc(100% + 4px);
  z-index: 40;
  min-width: 7.5rem;
  max-width: min(16rem, calc(100vw - 1.5rem));
  padding: 0.35rem 0.5rem;
  border: 1px solid var(--admin-border);
  border-radius: 5px;
  background: var(--admin-elevated);
  color: var(--admin-text);
  font-size: 0.7rem;
  font-weight: 500;
  line-height: 1.35;
  letter-spacing: 0;
  text-transform: none;
  text-align: left;
  white-space: normal;
  overflow-wrap: anywhere;
  box-shadow: var(--admin-shadow-sm);
}
.sp-chip--more[aria-expanded="true"] .sp-chip-more__tip {
  display: block;
}

/* Compact inputs */
.sp-input {
  width: 100%;
  padding: 0.3rem 0.4rem;
  border: 1px solid var(--admin-border);
  border-radius: 5px;
  background: var(--admin-input-bg);
  font-size: 0.8rem;
  color: var(--admin-text);
  height: 28px;
  box-sizing: border-box;
}
.sp-input:focus {
  outline: none;
  border-color: var(--admin-primary);
  box-shadow: 0 0 0 2px var(--admin-primary-ring);
}
.sp-input--sm { max-width: 100%; }

/* Catalog search: match input height */
.sp-search-wrap { position: relative; }
.sp-search-wrap .admin-search-select { font-size: 0.8rem; }
.sp-search-wrap .admin-search-select__input {
  padding: 0.25rem 0.4rem;
  min-height: 28px;
  height: 28px;
  font-size: 0.8rem;
}

/* Stacked cards below 68.75em — not all and (min-width) so nothing sits
   between this query and min-width: 68.75em, and iOS < 16.4 still matches. */
@media not all and (min-width: 68.75em) {
  .sp-group .admin-table-scroll {
    overflow: visible;
  }
  .sp-table { display: block; }
  .sp-table thead { display: none; }
  .sp-table tbody { display: block; }
  .sp-table,
  .sp-table tbody,
  .sp-table tr,
  .sp-table td {
    background: var(--admin-surface);
  }
  .sp-table tr {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    grid-template-areas:
      "label label"
      "catalog consumers"
      "search search"
      "rate sell"
      "unit price"
      "name name";
    gap: 0.5rem 0.75rem;
    padding: 0.85rem 0;
    border-bottom: 1px solid var(--admin-border);
    box-shadow: none;
    align-items: center;
  }
  .sp-table tr:last-child { border-bottom: none; }
  .sp-table td {
    display: block;
    padding: 0;
    border: none;
    overflow: visible;
  }
  .sp-table td::before {
    content: attr(data-label);
    display: block;
    font-size: 0.6rem;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--admin-muted);
    margin-bottom: 2px;
  }

  .sp-col-label { grid-area: label; }
  .sp-col-rate { grid-area: rate; }
  .sp-col-sell { grid-area: sell; }
  .sp-col-unit { grid-area: unit; }
  .sp-col-price { grid-area: price; }
  .sp-col-name { grid-area: name; }
  .sp-table td.sp-col-catalog {
    display: contents;
  }
  .sp-catalog-btn { grid-area: catalog; justify-self: start; }
  .sp-search-wrap { grid-area: search; width: 100%; max-width: 100%; }
  .sp-search-wrap.sp-search-wrap--hidden {
    position: static;
    left: auto;
    right: auto;
    top: auto;
    width: 100%;
    height: 0;
    overflow: hidden;
    opacity: 0;
    pointer-events: none;
  }
  .sp-search-wrap--hidden:focus-within {
    height: auto;
    overflow: visible;
    opacity: 1;
    pointer-events: auto;
  }
  .sp-col-consumers {
    grid-area: consumers;
    justify-self: stretch;
    min-width: 0;
    max-width: 100%;
    overflow: hidden;
  }
  .sp-consumers {
    min-width: 0;
    max-width: 100%;
    overflow: hidden;
  }
  .sp-consumers:has(.sp-chip--more) .sp-chip:not(.sp-chip--more) {
    max-width: min(140px, calc(100% - 52px));
  }

  .sp-col-label {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--admin-heading);
  }
  .sp-col-label::before { display: none !important; }
  .sp-col-catalog::before { display: none !important; }
  .sp-work-label { font-size: 0.9rem; -webkit-line-clamp: 3; }
  .sp-work-meta { font-size: 0.75rem; }

  .sp-input { width: 100%; max-width: none; height: 44px; }
  .sp-input--sm { max-width: 100%; }
  .sp-rate-empty { min-height: 44px; }
  .sp-catalog-btn {
    width: 44px;
    height: 44px;
    padding: 0;
    margin: 0;
    box-sizing: border-box;
  }
  .sp-col-catalog,
  .sp-col-consumers { min-height: 44px; }
  .sp-sell-phone-label { display: inline-flex; }
  .sp-group__sell-tip { display: inline-flex; }
  .sp-col-sell::before { display: none !important; }
  .sp-th-sell .admin-info-tip,
  .sp-sell-phone-label .admin-info-tip,
  .sp-group__sell-tip .admin-info-tip,
  .sp-heading .admin-info-tip,
  .sp-work-extra .admin-info-tip,
  .sp-add > summary .admin-info-tip {
    width: 28px;
    height: 28px;
    padding: 8px;
    margin: -8px 0 -8px -4px;
    box-sizing: content-box;
  }
  .sp-chip {
    min-height: 44px;
  }
  .sp-chip--more {
    min-width: 44px;
    min-height: 44px;
    padding: 0;
    margin: 0;
    box-sizing: border-box;
  }
  .sp-chip-more__tip {
    right: auto;
    left: 0;
  }
  .sp-mat-name-input:focus {
    position: static;
    transform: none;
    width: 100%;
    max-width: none;
  }

  .sp-crew,
  .sp-crew .sp-crew-grid,
  .sp-crew .sp-crew-field {
    min-width: 0;
    max-width: 100%;
  }
  .sp-crew .sp-crew-grid {
    flex-direction: column;
    gap: 0.75rem;
  }
  .sp-crew .sp-crew-field .admin-input {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
  }
  .sp-work-label-input {
    min-height: 44px;
    height: 44px;
  }
}

.sp-add {
  margin-top: 0.75rem;
  padding-top: 0.5rem;
  border-top: 1px solid var(--admin-border);
}
.sp-add > summary {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  min-height: 44px;
  cursor: pointer;
  list-style: none;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--admin-primary);
}
.sp-add > summary::-webkit-details-marker { display: none; }
.sp-add__grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 0.5rem 0.75rem;
  padding: 0.25rem 0 0.5rem;
}
.sp-add__name { grid-column: 1 / -1; }
.sp-add__consumers { grid-column: 1 / -1; }
.sp-add__field > label {
  display: block;
  font-size: 0.6rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--admin-muted);
  margin-bottom: 2px;
}
.sp-add__consumers {
  margin: 0;
  padding: 0;
  border: 0;
  min-width: 0;
}
.sp-add__consumers > legend {
  display: block;
  padding: 0;
  font-size: 0.6rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--admin-muted);
  margin-bottom: 2px;
}
.sp-add .sp-input,
.sp-add .admin-input {
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
}
.sp-add__checks {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem 0.75rem;
}
.sp-add__checks .admin-checkbox {
  min-height: 44px;
}
.sp-add__cost-hint {
  font-weight: 500;
  text-transform: none;
  letter-spacing: 0;
  color: var(--admin-muted);
}
@media (min-width: 68.75em) {
  .sp-add__grid {
    grid-template-columns: var(--sp-grid-columns);
    column-gap: 0.08rem;
    align-items: end;
  }
  .sp-add__name { grid-column: 1; }
  .sp-add__field--productivity { grid-column: 2; }
  .sp-add__field--sell { grid-column: 3; }
  .sp-add__field--unit { grid-column: 4; }
  .sp-add__consumers { grid-column: 1 / -1; margin-top: 0.35rem; }
  .sp-add .sp-input,
  .sp-add .admin-input { height: 28px; min-height: 28px; }
  .sp-add__checks .admin-checkbox { min-height: 32px; }
}
</style>
@endpush

@section('content')
  @php
    $catalogById = collect($catalogPickerOptions ?? [])->keyBy('id');
    $worksByGroup = $works->groupBy('group_key');
    $kindLabels = [
      \App\Models\SharedWork::KIND_PER_HOUR => 'Units/hr',
      \App\Models\SharedWork::KIND_HOURS_EACH => 'Hrs each',
      \App\Models\SharedWork::KIND_FLAT => 'Flat',
    ];
    $spProductivityTipText = 'How many units you finish per hour (e.g. sf or lf per hour). A rough estimate is owner hourly rate ÷ sell rate. Filling Sell does not clear Productivity.';
    $spProductivityTipLabel = 'productivity field';
    $spSellTipText = 'Used when Sell rate is empty. Quotations compute this from the crew rate and this row’s Productivity.';
    $spSellTipLabel = 'effective sell';
  @endphp

  <div class="admin-toolbar fq-list-toolbar">
    <div class="admin-toolbar-group">
      <a href="{{ route('admin.quotations.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-arrow-left"></i> Back to quotations
      </a>
    </div>
    <div class="admin-toolbar-group admin-toolbar-actions">
      <button type="submit" form="sp-lib-form" class="admin-btn admin-btn--primary admin-btn--sm" data-hotkey-save>
        <i class="fa fa-save"></i> Save shared prices
      </button>
    </div>
  </div>

  <script type="application/json" id="admin-ss-catalog-options">@json($catalogPickerOptions ?? [])</script>

  <form method="post" action="{{ route('admin.quotations.shared-prices.update') }}" id="sp-lib-form">
    @csrf
    @method('PUT')

    <div class="admin-card sp-crew">
      <h3 class="sp-heading">
        Crew rates
        <x-admin.info-tip label="Crew rates">Used by Basement, Trimming, Shed, and Millwork when a quote does not override them.</x-admin.info-tip>
      </h3>
      <div class="sp-crew-grid">
        <div class="sp-crew-field">
          <label for="owner_hourly_rate">Owner hourly (CAD$)</label>
          <input type="text" inputmode="decimal" autocomplete="off"
                 class="admin-input cp-money-input" id="owner_hourly_rate" name="owner_hourly_rate"
                 value="{{ old('owner_hourly_rate', number_format((float) $crew->owner_hourly_rate, 2, '.', '')) }}" required data-cp-money-entry="direct">
        </div>
        <div class="sp-crew-field">
          <label for="helper_hourly_rate">Helper hourly (CAD$)</label>
          <input type="text" inputmode="decimal" autocomplete="off"
                 class="admin-input cp-money-input" id="helper_hourly_rate" name="helper_hourly_rate"
                 value="{{ old('helper_hourly_rate', number_format((float) $crew->helper_hourly_rate, 2, '.', '')) }}" required data-cp-money-entry="direct">
        </div>
        <div class="sp-crew-field">
          <label for="hours_per_day">Hours / day</label>
          <input type="number" step="0.25" min="0.5" max="16" class="admin-input" id="hours_per_day" name="hours_per_day"
                 value="{{ old('hours_per_day', $crew->hours_per_day) }}" required>
        </div>
      </div>
    </div>

    @foreach ($groups as $groupKey => $groupLabel)
      @php $groupWorks = $worksByGroup->get($groupKey, collect()); @endphp
      @if ($groupWorks->isEmpty())
        @continue
      @endif
      <details class="admin-card sp-group" id="group-{{ $groupKey }}" open>
        <summary class="sp-group__summary">
          <span class="sp-group__title">{{ $groupLabel }} ({{ $groupWorks->count() }})</span>
          <span class="sp-group__sell-tip" onclick="event.preventDefault(); event.stopPropagation();">
            <x-admin.info-tip :text="$spSellTipText" :label="$spSellTipLabel" />
          </span>
        </summary>
        <div class="admin-table-scroll">
          <table class="sp-table">
            <thead>
              <tr>
                <th class="sp-col-label">
                  <span class="sp-th-sell">
                    Work item
                    <x-admin.info-tip text="Rename here — saved quotes keep using a hidden key, not this name. Names must be unique inside this group. Custom items are reference-only." label="work item name" />
                  </span>
                </th>
                <th class="sp-col-rate">
                  <span class="sp-th-label">
                    Productivity
                    <x-admin.info-tip :text="$spProductivityTipText" :label="$spProductivityTipLabel" />
                  </span>
                </th>
                <th class="sp-col-sell">
                  <span class="sp-th-sell">
                    Sell rate
                    <x-admin.info-tip :text="$spSellTipText" :label="$spSellTipLabel" />
                  </span>
                </th>
                <th class="sp-col-unit"><span class="sp-th-label">Unit</span></th>
                <th class="sp-col-price"><span class="sp-th-label">Mat. price</span></th>
                <th class="sp-col-name"><span class="sp-th-label">Mat. name</span></th>
                <th class="sp-col-catalog" title="Catalog"><span class="sp-th-label"><i class="fa fa-search" aria-hidden="true"></i></span></th>
                <th class="sp-col-consumers"><span class="sp-th-label">Used by</span></th>
              </tr>
            </thead>
            <tbody>
              @foreach ($groupWorks as $work)
                @php
                  $catalogId = (int) old('works.'.$work->id.'.catalog_material_id', $work->catalog_material_id ?? 0);
                  $selected = $catalogId > 0 ? ($catalogById->get($catalogId) ?? null) : null;
                  $isArea = $work->isPricedPerArea();
                  $effective = \App\Services\SharedWorkCatalog::displayUnitPrice($work);
                  $isCustom = $work->isCustom();
                  $consumers = is_array($work->consumers) ? $work->consumers : [];
                  $consumerLabels = [];
                  foreach ($consumers as $consumer) {
                      $consumerLabels[] = \App\Services\SharedWorkCatalog::CONSUMER_LABELS[$consumer] ?? $consumer;
                  }
                  $firstConsumer = $consumerLabels[0] ?? null;
                  $extraConsumerCount = max(count($consumerLabels) - 1, 0);
                  $savedSell = old('works.'.$work->id.'.flat_rate', $work->flat_rate !== null ? number_format((float) $work->flat_rate, 4, '.', '') : '');
                  $sellPlaceholder = $effective !== null
                      ? number_format((float) $effective, 4, '.', '')
                      : '0.0000';
                  $blankSell = null;
                  if (! $isCustom && ($def = \App\Services\SharedWorkCatalog::definition($work->key))) {
                      if (isset($def['flat_rate']) && (float) $def['flat_rate'] > 0) {
                          $blankSell = (float) $def['flat_rate'];
                      }
                  }
                  $workMeta = $work->costUnitLabel();
                @endphp
                <tr id="work-{{ $work->key }}" class="sp-row"
                    data-sp-kind="{{ $work->labor_kind }}"
                    data-sp-unit="{{ $work->unit }}"
                    data-sp-area="{{ $isArea ? '1' : '0' }}"
                    data-sp-blank-sell="{{ $blankSell !== null ? number_format($blankSell, 4, '.', '') : '' }}"
                    data-sp-default-sell="{{ $effective !== null ? number_format((float) $effective, 4, '.', '') : '' }}"
                    data-sp-initial-rate="{{ $work->rate !== null ? number_format((float) $work->rate, 4, '.', '') : '' }}">
                  <td class="sp-col-label" data-label="Work item">
                    <input type="hidden" name="works[{{ $work->id }}][id]" value="{{ $work->id }}">
                    <span class="sp-work">
                      <textarea rows="1" class="sp-input sp-work-label-input sp-auto-grow-textarea{{ $errors->has('works.'.$work->id.'.label') ? ' is-invalid' : '' }}" id="sp-label-{{ $work->id }}"
                             name="works[{{ $work->id }}][label]"
                             maxlength="160"
                             required
                             aria-label="Name for {{ $work->label }}">{{ old('works.'.$work->id.'.label', $work->label) }}</textarea>
                      <span class="sp-work-extra">
                        @if ($isCustom)
                          <span class="sp-ref-badge" title="Reference only — quote calculators do not use this item.">Reference</span>
                        @else
                          <small class="sp-work-meta">{{ $workMeta }}</small>
                        @endif
                      </span>
                    </span>
                  </td>
                  <td class="sp-col-rate" data-label="Productivity">
                    <input type="text" inputmode="decimal" autocomplete="off" data-cp-money-places="4"
                           class="sp-input sp-input--sm cp-money-input" id="sp-rate-{{ $work->id }}"
                           name="works[{{ $work->id }}][rate]"
                           value="{{ old('works.'.$work->id.'.rate', $work->rate !== null ? number_format((float) $work->rate, 4, '.', '') : '') }}"
                           placeholder="0.00" data-cp-money-entry="direct">
                  </td>
                  <td class="sp-col-sell" data-label="">
                    <span class="sp-sell-phone-label">Sell rate</span>
                    <div class="sp-sell-wrap">
                      <input type="text" inputmode="decimal" autocomplete="off" data-cp-money-places="4"
                             class="sp-input sp-input--sm cp-money-input" id="sp-flat-{{ $work->id }}"
                             name="works[{{ $work->id }}][flat_rate]"
                             value="{{ $savedSell }}"
                             placeholder="{{ $sellPlaceholder }}" data-cp-money-entry="direct">
                    </div>
                    <small class="sp-effective" data-sp-effective @if ($effective === null) hidden @endif>{{ $effective !== null ? \App\Models\ClientProject::formatMoney($effective)."\u{200B}/".$work->unit : '' }}</small>
                  </td>
                  <td class="sp-col-unit" data-label="Unit">
                    <input type="text" class="sp-input sp-input--sm" id="sp-mat-unit-{{ $work->id }}"
                           name="works[{{ $work->id }}][material_unit]"
                           value="{{ old('works.'.$work->id.'.material_unit', $work->material_unit) }}"
                           placeholder="—">
                  </td>
                  <td class="sp-col-price" data-label="Mat. price">
                    <input type="text" inputmode="decimal" autocomplete="off" data-cp-money-places="4"
                           class="sp-input sp-input--sm cp-money-input" id="sp-mat-price-{{ $work->id }}"
                           name="works[{{ $work->id }}][material_unit_price]"
                           value="{{ old('works.'.$work->id.'.material_unit_price', $work->material_unit_price !== null ? number_format((float) $work->material_unit_price, 4, '.', '') : '') }}"
                           placeholder="0.00" data-cp-money-entry="direct">
                  </td>
                  <td class="sp-col-name" data-label="Mat. name">
                    @php $matNameVal = old('works.'.$work->id.'.material_name', $work->material_name); @endphp
                    <textarea rows="1" class="sp-input sp-mat-name-input sp-auto-grow-textarea" id="sp-mat-name-{{ $work->id }}"
                           name="works[{{ $work->id }}][material_name]"
                           placeholder="—">{{ $matNameVal }}</textarea>
                  </td>
                  <td class="sp-col-catalog" data-label="Catalog">
                    <button type="button" class="sp-catalog-btn{{ $catalogId > 0 ? ' is-selected' : '' }}"
                            title="Search catalog materials"
                            aria-label="Search catalog materials for {{ $work->label }}"
                            onclick="event.stopPropagation(); this.nextElementSibling.querySelector('.admin-search-select__input').focus();">
                      <i class="fa fa-search" aria-hidden="true"></i>
                    </button>
                    <div class="sp-search-wrap sp-search-wrap--hidden">
                      @include('admin.partials.search_select', [
                        'id' => 'sp-mat-'.$work->id,
                        'name' => 'works['.$work->id.'][catalog_material_id]',
                        'value' => $catalogId > 0 ? $catalogId : '',
                        'hideLabel' => true,
                        'placeholder' => 'Type to search...',
                        'ariaLabel' => 'Catalog material for '.$work->label,
                        'wrapperClass' => 'sp-search-wrap--hidden-input',
                        'optionsSource' => 'admin-ss-catalog-options',
                        'selectedOption' => $selected,
                        'searchFields' => ['label', 'name', 'sku', 'model', 'vendor', 'store_name'],
                        'metaKeys' => ['sku', 'price_label'],
                        'emptyMessage' => 'No match',
                        'maxOptions' => 50,
                        'listParent' => 'body',
                        'fill' => [
                          'name' => 'sp-mat-name-'.$work->id,
                          'price' => 'sp-mat-price-'.$work->id,
                        ],
                      ])
                    </div>
                  </td>
                  <td class="sp-col-consumers" data-label="Used by">
                    <div class="sp-consumers">
                      @if ($firstConsumer)
                        <span class="sp-chip" title="{{ $firstConsumer }}"><span class="sp-chip__label">{{ $firstConsumer }}</span></span>
                      @endif
                      @if ($extraConsumerCount > 0)
                        @php $moreTipId = 'sp-more-'.$work->id; @endphp
                        <button type="button" class="sp-chip sp-chip--more"
                                aria-expanded="false"
                                aria-controls="{{ $moreTipId }}"
                                aria-describedby="{{ $moreTipId }}"
                                aria-label="+{{ $extraConsumerCount }} more: {{ implode(', ', $consumerLabels) }}">
                          +{{ $extraConsumerCount }}
                          <span id="{{ $moreTipId }}" class="sp-chip-more__tip" role="tooltip">{{ implode(', ', $consumerLabels) }}</span>
                        </button>
                      @endif
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @php
          $addHasInput = filled(old('new_works.'.$groupKey.'.0.label'));
          $addHasError = $errors->has('new_works.'.$groupKey.'.0.label');
        @endphp
        <details class="sp-add" id="sp-add-{{ $groupKey }}" @if ($addHasInput || $addHasError) open @endif>
          <summary>
            <i class="fa fa-plus" aria-hidden="true"></i> Add item
            <span onclick="event.preventDefault(); event.stopPropagation();">
              <x-admin.info-tip text="New items are reference-only. They appear on Shared lists for the types you tick so you can look up the price. Built-in quote calculators do not use them." label="add shared item" />
            </span>
          </summary>
          @php $newUnit = old('new_works.'.$groupKey.'.0.unit', 'each'); @endphp
          <div class="sp-add__grid">
            <div class="sp-add__field sp-add__name">
              <label for="sp-new-{{ $groupKey }}-label">Name</label>
              <input type="text" class="sp-input{{ $addHasError ? ' is-invalid' : '' }}" id="sp-new-{{ $groupKey }}-label"
                     name="new_works[{{ $groupKey }}][0][label]" maxlength="160"
                     value="{{ old('new_works.'.$groupKey.'.0.label') }}"
                     placeholder="e.g. Extra return">
            </div>
            <div class="sp-add__field sp-add__field--productivity">
              <label for="sp-new-{{ $groupKey }}-cost">Productivity <span class="sp-add__cost-hint" data-sp-cost-hint>{{ \App\Models\SharedWork::costUnitLabelFor($newUnit) }}</span></label>
              <input type="text" inputmode="decimal" autocomplete="off" data-cp-money-places="4"
                     data-cp-money-entry="direct"
                     class="sp-input cp-money-input" id="sp-new-{{ $groupKey }}-cost"
                     name="new_works[{{ $groupKey }}][0][rate]"
                     value="{{ old('new_works.'.$groupKey.'.0.rate') }}"
                     placeholder="0.00">
            </div>
            <div class="sp-add__field sp-add__field--sell">
              <label for="sp-new-{{ $groupKey }}-sell">Sell</label>
              <input type="text" inputmode="decimal" autocomplete="off" data-cp-money-places="4"
                     data-cp-money-entry="direct"
                     class="sp-input cp-money-input" id="sp-new-{{ $groupKey }}-sell"
                     name="new_works[{{ $groupKey }}][0][flat_rate]"
                     value="{{ old('new_works.'.$groupKey.'.0.flat_rate') }}"
                     placeholder="0.00">
            </div>
            <div class="sp-add__field sp-add__field--unit">
              <label for="sp-new-{{ $groupKey }}-unit">Unit</label>
              <select class="admin-input sp-input" id="sp-new-{{ $groupKey }}-unit"
                      name="new_works[{{ $groupKey }}][0][unit]" data-sp-new-unit>
                @foreach (['each' => 'each', 'lf' => 'lf', 'sf' => 'sf', 'opening' => 'opening'] as $unitVal => $unitLabel)
                  <option value="{{ $unitVal }}" @selected($newUnit === $unitVal)>{{ $unitLabel }}</option>
                @endforeach
              </select>
            </div>
            <fieldset class="sp-add__consumers">
              <legend>Show as reference on</legend>
              <div class="sp-add__checks">
                @php $defaultNewConsumers = old('new_works.'.$groupKey.'.0.consumers', \App\Services\SharedWorkCatalog::defaultConsumersForGroup($groupKey)); @endphp
                @foreach (\App\Services\SharedWorkCatalog::CONSUMER_LABELS as $consumerKey => $consumerLabel)
                  <label class="admin-checkbox">
                    <input type="checkbox" class="admin-checkbox__input"
                           name="new_works[{{ $groupKey }}][0][consumers][]" value="{{ $consumerKey }}"
                           @checked(in_array($consumerKey, $defaultNewConsumers, true))>
                    <span class="admin-checkbox__mark" aria-hidden="true"></span>
                    <span class="admin-checkbox__text">{{ $consumerLabel }}</span>
                  </label>
                @endforeach
              </div>
            </fieldset>
          </div>
        </details>
      </details>
    @endforeach
  </form>

  <script src="{{ asset('js/shared-prices-effective.js') }}?v={{ @filemtime(public_path('js/shared-prices-effective.js')) ?: time() }}"></script>
  <script>
    (function() {
      var form = document.getElementById('sp-lib-form');
      if (form) {
        form.addEventListener('submit', function(e) {
          if (form.getAttribute('data-submitting') === '1') {
            e.preventDefault();
            return;
          }
          form.setAttribute('data-submitting', '1');
          document.querySelectorAll('button[type="submit"][form="sp-lib-form"], #sp-lib-form button[type="submit"]').forEach(function(btn) {
            btn.disabled = true;
          });
        });
      }
      function costHintFor(unit) {
        unit = String(unit || 'each').toLowerCase();
        if (unit === 'each') return 'hrs per each';
        if (unit === 'opening') return 'hrs per opening';
        if (unit === 'lf' || unit === 'sf') return unit + ' per hour';
        return 'hrs per ' + unit;
      }
      document.querySelectorAll('[data-sp-new-unit]').forEach(function(select) {
        var hint = select.closest('.sp-add__grid') && select.closest('.sp-add__grid').querySelector('[data-sp-cost-hint]');
        if (!hint) return;
        select.addEventListener('change', function() {
          hint.textContent = costHintFor(select.value);
        });
      });
    })();

    function growSpTextarea(el) {
      if (!el || el.tagName !== 'TEXTAREA') return;
      el.style.height = 'auto';
      el.style.height = el.scrollHeight + 'px';
    }
    document.querySelectorAll('.sp-auto-grow-textarea').forEach(function(input) {
      growSpTextarea(input);
      input.addEventListener('input', function() {
        growSpTextarea(this);
        this.title = this.value;
      });
      input.addEventListener('focus', function() {
        this.title = this.value;
      });
    });

    function closeSpMoreTips(except) {
      document.querySelectorAll('.sp-chip--more').forEach(function(btn) {
        if (btn !== except) {
          btn.setAttribute('aria-expanded', 'false');
          resetSpMoreTip(btn);
        }
      });
    }

    function resetSpMoreTip(btn) {
      var tip = btn.querySelector('.sp-chip-more__tip');
      if (!tip) return;
      tip.style.position = '';
      tip.style.top = '';
      tip.style.left = '';
      tip.style.right = '';
      tip.style.maxWidth = '';
      tip.style.width = '';
    }

    function clampBox(el, anchor) {
      if (!el) return;
      var pad = 8;
      var vw = window.innerWidth || document.documentElement.clientWidth || 0;
      var vh = window.innerHeight || document.documentElement.clientHeight || 0;
      var cap = Math.min(280, vw * 0.7);
      el.style.position = 'fixed';
      el.style.right = 'auto';
      el.style.left = pad + 'px';
      el.style.top = pad + 'px';
      el.style.maxWidth = cap + 'px';
      el.style.width = '';
      var r = el.getBoundingClientRect();
      var width = Math.min(r.width || cap, cap);
      if (width > 0) el.style.width = Math.round(width) + 'px';
      var ar = anchor.getBoundingClientRect();
      r = el.getBoundingClientRect();
      var left = ar.left;
      if (left + r.width > vw - pad) left = vw - pad - r.width;
      if (left < pad) left = pad;
      var top = ar.bottom + 4;
      if (top + r.height > vh - pad) {
        top = Math.max(pad, ar.top - r.height - 4);
      }
      el.style.left = Math.round(left) + 'px';
      el.style.top = Math.round(top) + 'px';
    }

    function positionSpMoreTip(btn) {
      clampBox(btn.querySelector('.sp-chip-more__tip'), btn);
    }

    document.querySelectorAll('.sp-chip--more').forEach(function(btn) {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var open = this.getAttribute('aria-expanded') === 'true';
        closeSpMoreTips();
        this.setAttribute('aria-expanded', open ? 'false' : 'true');
        if (!open) positionSpMoreTip(this);
        this.focus();
      });
    });
    document.addEventListener('keydown', function(e) {
      if (e.key !== 'Escape') return;
      var open = document.querySelector('.sp-chip--more[aria-expanded="true"]');
      if (!open) return;
      open.setAttribute('aria-expanded', 'false');
      resetSpMoreTip(open);
      open.focus();
      e.preventDefault();
    });
    document.addEventListener('click', function(e) {
      if (e.target.closest && e.target.closest('.sp-chip--more')) {
        return;
      }
      closeSpMoreTips();
    });
    function repositionSpOpenTips() {
      document.querySelectorAll('.sp-chip--more[aria-expanded="true"]').forEach(positionSpMoreTip);
    }
    window.addEventListener('resize', repositionSpOpenTips);
    window.addEventListener('scroll', repositionSpOpenTips, true);
    document.addEventListener('click', function() {
      setTimeout(repositionSpOpenTips, 0);
    }, true);
    if (window.MutationObserver) {
      new MutationObserver(function() {
        repositionSpOpenTips();
      }).observe(document.body, { attributes: true, attributeFilter: ['aria-expanded'], subtree: true });
    }

    function updateSpEffective() {
      var lib = window.SharedPricesEffective;
      if (!lib) return;
      var owner = lib.parseSpMoney((document.getElementById('owner_hourly_rate') || {}).value);
      document.querySelectorAll('tr.sp-row').forEach(function(row) {
        var out = row.querySelector('[data-sp-effective]');
        if (!out) return;
        var rateInput = row.querySelector('.sp-col-rate input');
        var sellInput = row.querySelector('.sp-col-sell input');
        var priceInput = row.querySelector('.sp-col-price input');
        var unitInput = row.querySelector('.sp-col-unit input');
        var effective = lib.effectiveUnitPrice({
          sell: sellInput ? sellInput.value : '',
          rate: rateInput ? rateInput.value : '',
          owner: owner,
          price: priceInput ? priceInput.value : '',
          matUnit: unitInput ? unitInput.value : '',
          kind: row.getAttribute('data-sp-kind') || '',
          blankSell: row.getAttribute('data-sp-blank-sell') || '',
        });
        var unit = row.getAttribute('data-sp-unit') || '';
        if (effective == null) {
          out.hidden = true;
          out.textContent = '';
          return;
        }
        out.textContent = lib.formatEffectiveHint(effective, unit);
        out.hidden = false;
      });
    }
    document.getElementById('sp-lib-form').addEventListener('input', function(e) {
      if (!e.target) return;
      if (e.target.id === 'owner_hourly_rate' || e.target.closest('.sp-col-rate') || e.target.closest('.sp-col-sell') || e.target.closest('.sp-col-price') || e.target.closest('.sp-col-unit')) {
        updateSpEffective();
      }
    });
  </script>
@endsection
