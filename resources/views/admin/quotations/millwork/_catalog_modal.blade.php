<div id="mw-catalog-modal" class="admin-modal mw-catalog-modal" hidden>
  <div class="admin-modal-backdrop" data-mw-catalog-dismiss tabindex="-1"></div>
  <div class="admin-modal-panel mw-catalog-modal__panel" role="dialog" aria-modal="true" aria-labelledby="mw-catalog-modal-title">
    <h2 id="mw-catalog-modal-title" class="admin-modal-title">Manage list</h2>
    <p class="admin-modal-hint">Add, edit, or remove items. Saved quotations keep their own copy.</p>
    <div class="admin-table-scroll mw-catalog-modal__scroll">
      <div id="mw-catalog-modal-rows" class="mw-catalog-modal__rows"></div>
    </div>
    <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="mw-catalog-add">
      <i class="fa fa-plus" aria-hidden="true"></i> Add item
    </button>
    <div class="admin-modal-actions mw-catalog-modal__actions">
      <button type="button" class="admin-btn admin-btn--ghost" data-mw-catalog-dismiss>Cancel</button>
      <button type="button" class="admin-btn admin-btn--primary" id="mw-catalog-save">Save list</button>
    </div>
  </div>
</div>
<template id="mw-catalog-modal-row-tpl">
  <article class="mw-catalog-modal__card" data-mw-catalog-item>
    <input type="hidden" data-mw-cat-field="id">
    <div class="mw-catalog-modal__grid" data-mw-catalog-fields></div>
    <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm mw-catalog-modal__remove" data-mw-catalog-remove aria-label="Remove item">
      <i class="fa fa-trash" aria-hidden="true"></i>
    </button>
  </article>
</template>
