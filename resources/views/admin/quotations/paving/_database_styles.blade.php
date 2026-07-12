<style>
  .pq-nav-tabs {
    list-style: none;
    margin: 0 0 1.1rem;
    padding: 0;
    display: flex;
    flex-wrap: wrap;
    border-bottom: 1px solid var(--admin-border);
    gap: 0;
  }
  .pq-nav-tabs .nav-item {
    margin: 0 0 -1px 0;
  }
  .pq-nav-tabs .nav-link {
    display: block;
    margin: 0;
    padding: 0.5rem 1rem;
    border: 1px solid transparent;
    border-top-left-radius: 0.25rem;
    border-top-right-radius: 0.25rem;
    background: transparent;
    color: var(--admin-primary);
    font-weight: 600;
    font-size: 0.9rem;
    font-family: inherit;
    line-height: 1.4;
    cursor: pointer;
    text-align: left;
    text-decoration: none;
    box-shadow: none;
    -webkit-appearance: none;
    appearance: none;
  }
  .pq-nav-tabs .nav-link:hover:not(.active):not(:disabled) {
    color: var(--admin-primary-hover);
    border-color: var(--admin-surface-hover) var(--admin-surface-hover) var(--admin-border);
    background: var(--admin-surface-hover);
  }
  .pq-nav-tabs .nav-link:focus {
    outline: none;
  }
  .pq-nav-tabs .nav-link:focus-visible {
    box-shadow: 0 0 0 2px var(--admin-surface), 0 0 0 4px var(--admin-primary-ring);
  }
  .pq-nav-tabs .nav-link.active {
    color: var(--admin-text);
    background: var(--admin-surface);
    border-color: var(--admin-border) var(--admin-border) var(--admin-bg);
  }
  .pq-nav-tabs--secondary {
    margin-bottom: 1rem;
  }
  .pq-nav-tabs--secondary .nav-link {
    font-size: 0.875rem;
    padding: 0.42rem 0.9rem;
  }
  .pq-db-wrap { margin-top: 0; }
  .pq-db-subpanel[hidden] { display: none !important; }
  .pq-db-stone-list { display: flex; flex-direction: column; gap: 16px; margin-top: 12px; }
  .pq-db-stone-card {
    background: #fff;
    border: 1px solid var(--admin-border);
    border-radius: 8px;
    padding: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    transition: box-shadow 0.2s;
  }
  .pq-db-stone-card:focus-within {
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    border-color: var(--admin-primary-ring);
  }
  .pq-db-stone-card-header {
    display: grid;
    grid-template-columns: minmax(120px, 1.1fr) minmax(200px, 1.5fr) minmax(120px, 1fr) auto;
    gap: 12px 16px;
    margin-bottom: 16px;
    align-items: end;
  }
  .pq-db-stone-card-body {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 16px;
    margin-bottom: 16px;
  }
  .pq-db-stone-card-footer {
    display: grid;
    grid-template-columns: 1fr 1fr 2fr;
    gap: 16px;
    background: #f8fafc;
    padding: 12px 16px;
    border-radius: 6px;
    border: 1px dashed #cbd5e1;
  }
  .pq-db-field-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }
  .pq-db-field-label {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--admin-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
  }
  .pq-db-stone-actions {
    display: flex;
    gap: 8px;
    margin-top: 22px; /* align with inputs that have labels */
  }
  .pq-db-stone-link-wrap {
    display: flex;
    align-items: center;
    gap: 6px;
  }
  .pq-db-stone-link-wrap .admin-input { flex: 1 1 auto; min-width: 0; }
  .pq-db-stone-open-link {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 6px;
    color: #024768;
    background: #e8f4f8;
    text-decoration: none;
  }
  .pq-db-stone-open-link:hover { background: #d1e9f0; color: #013a52; }
  .pq-db-stone-sq-wrap {
    display: flex;
    align-items: center;
    gap: 6px;
  }
  .pq-db-stone-sq-wrap .pq-db-stone-sq { flex: 1 1 auto; min-width: 0; }
  .pq-db-image-cell { min-width: 0; }
  .pq-db-image-label-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    min-height: 18px;
  }
  .pq-db-image-label-row .pq-db-field-label { margin: 0; }
  .pq-db-image-row {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
  }
  .pq-db-image-row .pq-db-stone-thumb-wrap {
    width: 38px;
    height: 38px;
  }
  .pq-db-image-row .pq-db-stone-thumb-wrap i {
    font-size: 16px;
  }
  .pq-db-image-row .pq-db-image-panel {
    flex: 1 1 auto;
    min-width: 0;
  }
  .pq-db-image-mode {
    display: inline-flex;
    flex-shrink: 0;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
    overflow: hidden;
    background: #fff;
  }
  .pq-db-image-mode-btn {
    margin: 0;
    padding: 3px 10px;
    border: 0;
    background: transparent;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: #64748b;
    cursor: pointer;
    line-height: 1.4;
  }
  .pq-db-image-mode-btn + .pq-db-image-mode-btn {
    border-left: 1px solid #cbd5e1;
  }
  .pq-db-image-mode-btn.is-active {
    background: #e8f4f8;
    color: #024768;
  }
  .pq-db-image-mode-btn:focus-visible {
    outline: 2px solid #024768;
    outline-offset: -2px;
  }
  .pq-db-image-panel .admin-input { width: 100%; min-width: 0; }
  .pq-db-file-picker {
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 38px;
    width: 100%;
    margin: 0;
    cursor: pointer;
  }
  .pq-db-file-picker input[type="file"] { display: none; }
  .pq-db-file-picker-btn {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    padding: 7px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: #fff;
    font-size: 13px;
    font-weight: 500;
    color: #334155;
    line-height: 1.2;
  }
  .pq-db-file-picker:hover .pq-db-file-picker-btn {
    border-color: #94a3b8;
    background: #f8fafc;
  }
  .pq-db-file-picker-name {
    flex: 1 1 auto;
    min-width: 0;
    font-size: 12px;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .pq-db-stone-price-sqft:not(:placeholder-shown) {
    background: #f8fafc;
  }
  .pq-db-add-stone.pq-db-add-stone--error { border-color: #f87171; background: #fef2f2; }
  .pq-db-add-stone-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
    padding-top: 12px;
    border-top: 1px solid #e2e8f0;
  }
  .pq-db-hidden-form { display: none; }
  @media (max-width: 860px) {
    .pq-db-stone-card-header { grid-template-columns: 1fr; }
    .pq-db-stone-actions { margin-top: 0; }
    .pq-db-stone-card-body { grid-template-columns: repeat(3, 1fr); }
    .pq-db-stone-card-footer { grid-template-columns: 1fr; }
  }
  @media (max-width: 500px) {
    .pq-db-stone-card-body { grid-template-columns: 1fr 1fr; }
  }
  .pq-db-rates-groups { display: flex; flex-direction: column; gap: 20px; }
  .pq-db-rates-group-title {
    margin: 0 0 8px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: #64748b;
  }
  .pq-db-rates-list {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
    background: #fff;
  }
  .pq-db-rate-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 12px 20px;
    align-items: center;
    padding: 12px 16px;
    border-bottom: 1px solid #e8edf2;
    background: #fafbfc;
  }
  .pq-db-rate-row:last-child { border-bottom: none; }
  .pq-db-rate-name {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
    line-height: 1.3;
  }
  .pq-db-rate-unit {
    display: block;
    margin-top: 2px;
    font-size: 12px;
    color: #64748b;
  }
  .pq-db-rate-inline {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    justify-content: flex-end;
  }
  .pq-db-rate-price-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin: 0;
  }
  .pq-db-rate-price-label-text {
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }
  .pq-db-rate-input { width: 108px; min-width: 88px; }
  @media (max-width: 640px) {
    .pq-db-rate-row { grid-template-columns: 1fr; }
    .pq-db-rate-inline { justify-content: flex-start; }
  }

  .pq-db-stone-thumb-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 64px;
    height: 64px;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    overflow: hidden;
    flex-shrink: 0;
  }
  .pq-db-stone-thumb-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
  .pq-db-stone-thumb-wrap i {
    font-size: 24px;
    color: #94a3b8;
  }
  .pq-db-stone-card-header {
    display: grid;
    grid-template-columns: minmax(120px, 1.1fr) minmax(200px, 1.5fr) minmax(120px, 1fr) auto;
    gap: 12px 16px;
    align-items: end;
    padding: 16px;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
  }
  @media (max-width: 900px) {
    .pq-db-stone-card-header {
      grid-template-columns: 1fr 1fr;
    }
    .pq-db-stone-actions {
      grid-column: 1 / -1;
      margin-top: 0;
    }
  }
  @media (max-width: 560px) {
    .pq-db-stone-card-header { grid-template-columns: 1fr; }
  }
</style>
