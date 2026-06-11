@if ($filterUnlinkedOnly ?? false)
  @if ($linkProject ?? null)
    <div class="admin-flash admin-flash--info">
      Showing unlinked quotations only for project <strong>{{ $linkProject->name }}</strong> ({{ $linkProject->client_name }}).
      Pick one below or create a new quotation — it will attach to this project when saved.
    </div>
  @endif
@endif
