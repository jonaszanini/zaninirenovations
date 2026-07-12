{{--
  Shared "Link to project" card — existing project + create project & link.

  Required:
    $quotation              — quotation model (summaryDescription, client_name)
    $linkUrl                — POST URL to link an existing project
    $createProjectUrl       — POST URL to create a new project and link
    $idPrefix                — unique prefix for field IDs (e.g. pv, fl, sq)
    $projectNamePlaceholder  — placeholder for project name input

  Optional:
    $linkableProjects           — collection of linkable projects (default empty)
    $title                      — card heading (default: Link to project)
    $helpText                   — paragraph under the heading (HTML allowed)
    $wrapAdminCard              — wrap in admin-card (default false)
    $cardStyle                  — inline style on card wrapper
    $cardClassExtra             — extra classes on card wrapper
    $projectPickerId            — id for _link_project_picker select
    $emptyProjectsMessage       — message when no linkable projects
    $linkButtonLabel            — default: Link
    $createButtonLabel          — default: Create project & link
    $linkSmallButton            — use admin-btn--sm on link button (default true)
    $showExistingLineDescription — line description on existing-project form (default true)
    $createLineDescription       — hidden | field | none (default hidden)
    $sharedLineDescription       — array for standard quotation shared field above grid:
                                   id, form, label, value, placeholder, help
    $createFormOnsubmit          — onsubmit attribute for create form
    $createHiddenLineDescriptionId — hidden input id when using sharedLineDescription
    $existingFormId              — id for existing-project form
    $usePlacesAddress            — use Google Places address field (default false)
    $validateCreateProject        — show @error blocks (default true)
    $clientNameDefault            — default client name value
    $projectNameValue             — default project name value (old input)
--}}
@php
  use App\Models\ClientProject;

  $linkableProjects = $linkableProjects ?? collect();
  $title = $title ?? 'Link to project';
  $wrapAdminCard = $wrapAdminCard ?? false;
  $showExistingLineDescription = $showExistingLineDescription ?? true;
  $createLineDescription = $createLineDescription ?? 'hidden';
  $linkButtonLabel = $linkButtonLabel ?? 'Link';
  $createButtonLabel = $createButtonLabel ?? 'Create project & link';
  $linkSmallButton = $linkSmallButton ?? true;
  $validateCreateProject = $validateCreateProject ?? true;
  $usePlacesAddress = $usePlacesAddress ?? false;
  $emptyProjectsMessage = $emptyProjectsMessage ?? 'No active projects to link. Create a new one below.';
  $clientNameDefault = $clientNameDefault ?? old('fp_client_name', $quotation->client_name ?? '');
  $projectNameValue = $projectNameValue ?? old('fp_project_name');
  $lineDescriptionPlaceholder = $quotation->summaryDescription();

  $projectNameId = $idPrefix.'-new-project-name';
  $clientNameId = $idPrefix.'-new-client-name';
  $clientEmailId = $idPrefix.'-new-client-email';
  $clientPhoneId = $idPrefix.'-new-client-phone';
  $clientAddressId = $idPrefix.'-new-client-address';
  $pickerId = $idPrefix.'-fp-client-picker';

  $cardClasses = trim(($wrapAdminCard ? 'admin-card ' : '').'fq-link-project-card '.($cardClassExtra ?? ''));
@endphp

<div class="{{ $cardClasses }}"@if (! empty($cardStyle)) style="{{ $cardStyle }}"@endif>
  <h4 style="margin:0 0 8px;">{{ $title }}</h4>
  @if (! empty($helpText))
    <p class="admin-form-help cp-form-help-flush" style="margin:0 0 12px;">{!! $helpText !!}</p>
  @endif

  @if (! empty($sharedLineDescription))
    <div class="admin-form-group fq-link-shared-line-desc">
      <label for="{{ $sharedLineDescription['id'] }}">{{ $sharedLineDescription['label'] ?? 'Line description (optional)' }}</label>
      <input type="text"
             id="{{ $sharedLineDescription['id'] }}"
             name="link_line_description"
             class="admin-input"
             maxlength="500"
             @if (! empty($sharedLineDescription['form'])) form="{{ $sharedLineDescription['form'] }}" @endif
             value="{{ $sharedLineDescription['value'] ?? '' }}"
             placeholder="{{ $sharedLineDescription['placeholder'] ?? $lineDescriptionPlaceholder }}">
      @if (! empty($sharedLineDescription['help']))
        <p class="admin-form-help">{{ $sharedLineDescription['help'] }}</p>
      @endif
    </div>
  @endif

  <div class="fq-link-grid">
    <div class="fq-link-col">
      <h5>Existing project</h5>
      <form method="post"
            action="{{ $linkUrl }}"
            @if (! empty($existingFormId)) id="{{ $existingFormId }}" @endif>
        @csrf
        @if ($linkableProjects->isNotEmpty())
          <div class="fq-link-existing-row">
            @include('admin.quotations._link_project_picker', [
                'projects' => $linkableProjects,
                'id' => $projectPickerId ?? ($idPrefix.'-link-project'),
            ])
            <button type="submit" class="admin-btn admin-btn--primary{{ $linkSmallButton ? ' admin-btn--sm' : '' }}">{{ $linkButtonLabel }}</button>
          </div>
        @else
          <p class="admin-form-help cp-form-help-flush">{{ $emptyProjectsMessage }}</p>
        @endif
        @if ($showExistingLineDescription && empty($sharedLineDescription))
          <div class="admin-form-group cp-form-group-spaced">
            <label>Line description (optional)</label>
            <input type="text" name="link_line_description" class="admin-input" maxlength="500" placeholder="{{ $lineDescriptionPlaceholder }}">
          </div>
        @endif
      </form>
    </div>

    <hr class="fq-link-divider" aria-hidden="true">

    <div class="fq-link-col fq-link-col--stack">
      <h5>Create project &amp; link</h5>
      <form method="post"
            action="{{ $createProjectUrl }}"
            class="fq-create-project-form"
            @if (! empty($createFormOnsubmit)) onsubmit="{{ $createFormOnsubmit }}" @endif>
        @csrf
        @if (! empty($createHiddenLineDescriptionId))
          <input type="hidden" name="link_line_description" value="" id="{{ $createHiddenLineDescriptionId }}">
        @elseif ($createLineDescription === 'hidden')
          <input type="hidden" name="link_line_description" value="">
        @endif

        <div class="admin-form-grid-2 fq-create-project-top">
          <div class="admin-form-group">
            <label for="{{ $projectNameId }}">Project name</label>
            <input type="text"
                   id="{{ $projectNameId }}"
                   name="fp_project_name"
                   class="admin-input"
                   maxlength="255"
                   value="{{ $projectNameValue }}"
                   placeholder="{{ $projectNamePlaceholder }}">
          </div>
          @include('admin.quotations._fp_client_picker', [
              'clientsSearchUrl' => route('admin.clients.search'),
              'pickerId' => $pickerId,
              'nameInputId' => $clientNameId,
              'emailInputId' => $clientEmailId,
              'phoneInputId' => $clientPhoneId,
              'addressInputId' => $clientAddressId,
              'hideDivider' => true,
          ])
        </div>
        <div class="fp-cp-divider">or fill manually</div>

        <div class="admin-form-grid-2">
          <div class="admin-form-group">
            <label for="{{ $clientNameId }}">Client name <span class="cp-required">*</span></label>
            <input type="text"
                   id="{{ $clientNameId }}"
                   name="fp_client_name"
                   class="admin-input{{ $validateCreateProject && $errors->has('fp_client_name') ? ' is-invalid' : '' }}"
                   required
                   maxlength="255"
                   value="{{ $clientNameDefault }}">
            @if ($validateCreateProject)
              @error('fp_client_name') <p class="admin-form-error">{{ $message }}</p> @enderror
            @endif
          </div>
          <div class="admin-form-group">
            <label for="{{ $clientEmailId }}">Email</label>
            <input type="email"
                   id="{{ $clientEmailId }}"
                   name="fp_client_email"
                   class="admin-input{{ $validateCreateProject && $errors->has('fp_client_email') ? ' is-invalid' : '' }}"
                   maxlength="255"
                   value="{{ old('fp_client_email') }}">
            @if ($validateCreateProject)
              @error('fp_client_email') <p class="admin-form-error">{{ $message }}</p> @enderror
            @endif
          </div>
        </div>

        <div class="fq-create-project-contact">
          <div class="admin-form-group">
            <label for="{{ $clientPhoneId }}">Phone (10 digits)</label>
            <input type="text"
                   id="{{ $clientPhoneId }}"
                   name="fp_client_phone"
                   class="admin-input cp-phone-input{{ $validateCreateProject && $errors->has('fp_client_phone') ? ' is-invalid' : '' }}"
                   inputmode="tel"
                   autocomplete="tel"
                   maxlength="14"
                   placeholder="(613) 555-0100"
                   value="{{ old('fp_client_phone') ? ClientProject::formatPhone(old('fp_client_phone')) : '' }}">
            @if ($validateCreateProject)
              @error('fp_client_phone') <p class="admin-form-error">{{ $message }}</p> @enderror
            @endif
          </div>
          @if ($usePlacesAddress)
            @include('admin.partials.places_address_field', [
                'inputId' => $clientAddressId,
                'addressName' => 'fp_client_address',
                'label' => 'Address',
                'value' => old('fp_client_address'),
                'placeIdName' => 'fp_client_address_place_id',
                'placeIdValue' => old('fp_client_address_place_id'),
                'latName' => 'fp_client_address_latitude',
                'latValue' => old('fp_client_address_latitude'),
                'lngName' => 'fp_client_address_longitude',
                'lngValue' => old('fp_client_address_longitude'),
            ])
          @else
            <div class="admin-form-group">
              <label for="{{ $clientAddressId }}">Address</label>
              <input type="text"
                     id="{{ $clientAddressId }}"
                     name="fp_client_address"
                     class="admin-input{{ $validateCreateProject && $errors->has('fp_client_address') ? ' is-invalid' : '' }}"
                     maxlength="500"
                     value="{{ old('fp_client_address') }}">
              @if ($validateCreateProject)
                @error('fp_client_address') <p class="admin-form-error">{{ $message }}</p> @enderror
              @endif
            </div>
          @endif
        </div>

        @if ($createLineDescription === 'field')
          <div class="admin-form-group">
            <label for="{{ $idPrefix }}-new-line-desc">Line description (optional)</label>
            <input type="text"
                   id="{{ $idPrefix }}-new-line-desc"
                   name="link_line_description"
                   class="admin-input"
                   maxlength="500"
                   value="{{ old('link_line_description') }}"
                   placeholder="{{ $lineDescriptionPlaceholder }}">
          </div>
        @endif

        <button type="submit" class="admin-btn admin-btn--primary">{{ $createButtonLabel }}</button>
      </form>
    </div>
  </div>
</div>

@if ($usePlacesAddress)
  @push('scripts')
    @include('admin.partials.google_maps_scripts')
  @endpush
@endif
