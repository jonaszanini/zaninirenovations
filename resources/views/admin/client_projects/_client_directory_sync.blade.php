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
    @include('admin.partials.checkbox', [
      'name' => 'sync_client_directory',
      'hiddenFallback' => true,
      'checked' => old('sync_client_directory', '1') !== '0',
      'label' => 'Update central client record when saving',
      'info' => 'When checked, changes to the fields below are saved to the linked client in the Clients directory. Uncheck to keep this project’s contact details separate.',
      'class' => 'cp-checkbox-row',
    ])
  </div>
@endif
