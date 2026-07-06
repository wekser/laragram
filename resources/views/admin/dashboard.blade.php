@extends('laragram::admin.layout')

@section('title', 'Dashboard — Laragram')

@section('content')
    <h1>Dashboard</h1>

    <div class="lg-cards">
        <div class="lg-card">
            <div class="lg-card-value">{{ number_format($metrics['total']) }}</div>
            <div class="lg-card-label">Total users</div>
        </div>
        <div class="lg-card">
            <div class="lg-card-value">{{ number_format($metrics['active']) }}</div>
            <div class="lg-card-label">Active</div>
        </div>
        <div class="lg-card">
            <div class="lg-card-value">{{ number_format($metrics['inactive']) }}</div>
            <div class="lg-card-label">Inactive / blocked</div>
        </div>
        <div class="lg-card">
            <div class="lg-card-value">{{ number_format($metrics['new_today']) }}</div>
            <div class="lg-card-label">New today</div>
        </div>
        <div class="lg-card">
            <div class="lg-card-value">{{ number_format($metrics['new_week']) }}</div>
            <div class="lg-card-label">New this week</div>
        </div>
        <div class="lg-card">
            <div class="lg-card-value">{{ number_format($metrics['active_sessions']) }}</div>
            <div class="lg-card-label">Active sessions</div>
        </div>
    </div>

    <h2>Users by role</h2>
    <div class="lg-table-wrap">
        <table>
            <thead><tr><th>Role</th><th>Users</th></tr></thead>
            <tbody>
                @forelse ($metrics['roles'] as $role => $count)
                    <tr>
                        <td>{{ $role }}</td>
                        <td>{{ number_format($count) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="lg-muted">No users yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
