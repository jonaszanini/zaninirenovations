@extends('admin.layouts.app')

@section('heading', 'Team & roles')

@section('content')
  <div class="admin-toolbar">
    <p class="admin-toolbar-lede">Admins have full access, including deleting records. Staff accounts can use the CRM day-to-day but are blocked from delete actions.</p>
  </div>

  <div class="admin-card">
    <div class="admin-table-scroll">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($users as $user)
            <tr>
              <td><strong>{{ $user->name }}</strong>{{ $user->id === auth()->id() ? ' (you)' : '' }}</td>
              <td>{{ $user->email }}</td>
              <td>
                @if ($user->isAdmin())
                  <span class="admin-badge" style="background:#16a34a1a;color:#16a34a;border:1px solid #16a34a55;">Admin</span>
                @else
                  <span class="admin-badge" style="background:#6b72801a;color:#6b7280;border:1px solid #6b728055;">Staff</span>
                @endif
              </td>
              <td style="white-space:nowrap;">
                <a href="{{ route('admin.users.edit', $user) }}" class="admin-btn admin-btn--ghost admin-btn--sm">Edit</a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endsection
