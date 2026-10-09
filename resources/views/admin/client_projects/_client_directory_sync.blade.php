@if (!empty($clientFieldsLocked) && isset($project))
  <div class="admin-flash admin-flash--info" role="status">
    <i class="fa fa-lock" aria-hidden="true"></i>
    <strong>Client information as of completion</strong>
    @if ($project->clientSnapshotCapturedLabel())
      ({{ $project->clientSnapshotCapturedLabel() }})
    @endif
    — this record is frozen and will not change if the client directory is updated elsewhere.
  </div>
@else
  <div class="admin-form-group cp-client-sync-toggle">
    <span class="admin-label-with-tip">
    <label class="admin-checkbox cp-checkbox-row">
      <input type="hidden" name="sync_client_directory" value="0">
      <input type="checkbox" class="admin-checkbox__input" name="sync_client_directory" value="1"
             @checked(old('sync_client_directory', '1') !== '0')>
      <span class="admin-checkbox__mark" aria-hidden="true"></span>
      <span class="admin-checkbox__text">
        Update central client record when saving
      </span>
    </label>
    @include('admin.client_projects._cp_field_hint', [
      'tipText' => 'When checked, changes to the fields below are saved to the linked client in the Clients directory. Uncheck to keep this project’s contact details separate.',
      'tipLabel' => 'Update central client record when saving',
    ])
    </span>
  </div>
@endif
