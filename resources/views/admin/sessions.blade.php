@extends('laragram::admin.layout')

@section('title', 'Sessions — Laragram')

@section('content')
    <h1>Sessions</h1>

    <form method="POST" action="{{ route('laragram.admin.sessions.prune') }}" style="margin-bottom:16px"
          onsubmit="return confirm('Delete all sessions older than the lifetime window?')">
        @csrf
        <button>Prune expired (older than {{ number_format($lifetime) }} min)</button>
    </form>

    <div class="lg-table-wrap">
        <table>
            <thead>
                <tr><th>User</th><th>Station</th><th>Last activity</th></tr>
            </thead>
            <tbody>
                @forelse ($sessions as $session)
                    <tr>
                        <td>{{ optional($session->user)->first_name ?? '#' . $session->user_id }}</td>
                        <td><code>{{ $session->station }}</code></td>
                        <td>{{ $session->last_activity }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="lg-muted">No sessions recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('laragram::admin.pager', ['paginator' => $sessions])
@endsection
