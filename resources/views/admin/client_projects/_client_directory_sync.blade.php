@if (!empty($clientFieldsLocked) && isset($project))
  <div class="admin-flash admin-flash--info" style="margin-bottom:14px;" role="status">
    <i class="fa fa-lock" aria-hidden="true"></i>
    <strong>Client information as of completion</strong>
    @if ($project->clientSnapshotCapturedLabel())
      ({{ $project->clientSnapshotCapturedLabel() }})
    @endif
    — this record is frozen and will not change if the client directory is updated elsewhere.
  </div>
@else
  <div class="admin-form-group cp-client-sync-toggle">
    <label class="admin-checkbox cp-checkbox-row">
      <input type="hidden" name="sync_client_directory" value="0">
      <input type="checkbox" class="admin-checkbox__input" name="sync_client_directory" value="1"
             @checked(old('sync_client_directory', '1') !== '0')>
      <span class="admin-checkbox__mark" aria-hidden="true"></span>
      <span class="admin-checkbox__text">Update central client record when saving</span>
    </label>
    <p class="admin-form-help" style="margin:6px 0 0;">
      When checked, changes to the fields below are saved to the linked client in the
      <a href="{{ route('admin.clients.index') }}">Clients</a> directory. Uncheck to keep this project’s contact details separate.
    </p>
  </div>
@endif
