Pinned from 86e68a3e0185e1af7e4793200d770ffa9b80f923:resources/views/admin/quotations/basement/create.blade.php
                    title="Email quotation to client"
                    aria-label="Email quotation to client"
                    onclick='return confirm(@json($sendEmailConfirm));'>
              <i class="fa fa-paper-plane" aria-hidden="true"></i>
            </button>
          @else
            <span class="br-toolbar-disabled-tip" title="{{ $sendEmailDisabledTitle }}">
              <button type="button"
                      class="admin-btn admin-btn--ghost admin-btn--sm admin-btn--icon"
                      disabled
                      aria-label="Email quotation to client. {{ $sendEmailDisabledTitle }}">
