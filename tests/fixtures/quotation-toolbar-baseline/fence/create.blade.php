Pinned from 86e68a3e0185e1af7e4793200d770ffa9b80f923:resources/views/admin/quotations/fence/create.blade.php
      @endif
      @if (! $isEdit)
        <button type="submit" form="fq-form" name="after_save" value="create_project"
                class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-folder-plus"></i> Save &amp; create project
        </button>
      @endif
