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
  .pq-db-stone-list { min-width: 1000px; border: 1px solid var(--admin-border); border-radius: 8px; overflow: hidden; }
  .pq-db-stone-grid {
    display: grid;
    grid-template-columns:
      minmax(120px, 1.15fr)
      minmax(120px, 1.2fr)
      minmax(60px, 0.5fr)
      minmax(60px, 0.5fr)
      minmax(60px, 0.5fr)
      minmax(100px, 0.8fr)
      minmax(75px, 0.6fr)
      minmax(75px, 0.6fr)
      auto;
    gap: 8px 10px;
    align-items: center;
    padding: 0.65rem 1.1rem;
  }
  .pq-db-stone-grid > * {
    min-width: 0;
  }
  .pq-db-stone-head {
    background: var(--admin-surface-hover);
    border-bottom: 1px solid var(--admin-border);
    align-items: end;
    padding-top: 0.55rem;
    padding-bottom: 0.5rem;
  }
  .pq-db-stone-head-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--admin-muted);
    font-weight: 600;
    line-height: 1.2;
  }
  .pq-db-stone-row {
    border-bottom: 1px solid var(--admin-border);
    background: #f8fafc;
  }
  .pq-db-stone-row:last-child { border-bottom: none; }
  .pq-db-stone-row .pq-db-stone-grid { padding-top: 0.55rem; padding-bottom: 0.7rem; }
  .pq-db-stone-link-wrap {
    display: flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
  }
  .pq-db-stone-link-wrap .admin-input { flex: 1 1 auto; min-width: 0; }
  .pq-db-stone-open-link {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
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
    min-width: 0;
  }
  .pq-db-stone-sq-wrap .pq-db-stone-sq { flex: 1 1 auto; min-width: 0; }
  .pq-db-stone-grid--add { margin-top: 4px; }
  .pq-db-stone-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    justify-content: flex-end;
    padding-top: 1px;
  }
  .pq-db-hidden-form { display: none; }
  @media (max-width: 720px) {
    .pq-db-stone-grid {
      grid-template-columns: 1fr;
    }
    .pq-db-stone-actions {
      justify-content: flex-start;
    }
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
</style>
