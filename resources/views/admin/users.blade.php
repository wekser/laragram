@extends('laragram::admin.layout')

@section('title', 'Users — Laragram')

@section('content')
    <h1>Users</h1>

    <form method="GET" class="lg-filters" style="margin-bottom:16px">
        <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Search name, @username or uid">
        <select name="role">
            <option value="">All roles</option>
            @foreach ($roles as $role)
                <option value="{{ $role }}" @selected($filters['role'] === $role)>{{ $role }}</option>
            @endforeach
        </select>
        <select name="status">
            <option value="">Any status</option>
            <option value="active" @selected($filters['status'] === 'active')>Active</option>
            <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
        </select>
        <button>Filter</button>
    </form>

    <div class="lg-table-wrap">
        <table>
            <thead>
                <tr><th>UID</th><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>{{ $user->uid }}</td>
                        <td>{{ trim($user->first_name . ' ' . $user->last_name) }}</td>
                        <td>{{ $user->username ? '@' . $user->username : '—' }}</td>
                        <td>{{ $user->role }}</td>
                        <td>
                            @if ($user->is_active)
                                <span class="lg-badge-ok">active</span>
                            @else
                                <span class="lg-badge-off">inactive</span>
                            @endif
                        </td>
                        <td class="lg-actions">
                            <form method="POST" action="{{ route('laragram.admin.users.role', $user->id) }}">
                                @csrf
                                <input name="role" value="{{ $user->role }}" size="8" aria-label="Role">
                                <button>Set role</button>
                            </form>
                            <form method="POST" action="{{ route('laragram.admin.users.toggle', $user->id) }}">
                                @csrf
                                <button>{{ $user->is_active ? 'Deactivate' : 'Activate' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="lg-muted">No users match the current filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('laragram::admin.pager', ['paginator' => $users])
@endsection
