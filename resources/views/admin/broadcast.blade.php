@extends('laragram::admin.layout')

@section('title', 'Broadcast — Laragram')

@section('content')
    <h1>Broadcast</h1>

    <form method="POST" action="{{ route('laragram.admin.broadcast.store') }}" class="lg-form">
        @csrf

        <label>
            Message
            <textarea name="message" rows="5" maxlength="4096" required placeholder="Your announcement… HTML is supported.">{{ old('message') }}</textarea>
        </label>

        <label>
            Role filter
            <select name="role">
                <option value="">All roles</option>
                @foreach ($roles as $role)
                    <option value="{{ $role }}" @selected(old('role') === $role)>{{ $role }}</option>
                @endforeach
            </select>
        </label>

        <label class="lg-check">
            <input type="checkbox" name="include_inactive" value="1" @checked(old('include_inactive'))>
            Include inactive users
        </label>

        <div class="lg-form-actions">
            <button name="action" value="preview">Dry run</button>
            <button name="action" value="send" class="lg-btn-primary"
                    onclick="return confirm('Send this broadcast to every matching user?')">Send</button>
        </div>
    </form>

    <p class="lg-hint">
        Delivered through the same path as <code>laragram:broadcast</code> — queued when
        <code>queue.enabled</code>, otherwise sent synchronously. Use <strong>Dry run</strong> to
        see the recipient count before sending.
    </p>
@endsection
