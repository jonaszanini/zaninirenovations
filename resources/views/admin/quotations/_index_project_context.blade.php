@if ($filterUnlinkedOnly ?? false)
  @if ($linkProject ?? null)
    <div class="admin-flash admin-flash--info">
      Showing unlinked quotations only for project <strong>{{ $linkProject->name }}</strong> (@include('admin.partials.client_directory_link', ['project' => $linkProject])).
      Pick one below or create a new quotation — it will attach to this project when published (Save quotation, not Save as draft). Quotes already on this project are on the project Quotation tab, not this list.
    </div>
  @endif
@endif
