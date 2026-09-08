@if ($filterUnlinkedOnly ?? false)
  @if ($linkProject ?? null)
    <div class="admin-flash admin-flash--info">
      Showing unlinked quotations only for project <strong>{{ $linkProject->name }}</strong> (@include('admin.partials.client_directory_link', ['project' => $linkProject])).
      Pick one below or create a new quotation — it will attach to this project when saved.
    </div>
  @endif
@endif
