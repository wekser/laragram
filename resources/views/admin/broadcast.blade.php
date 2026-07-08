@extends('laragram::admin.layout')

@section('title', 'Broadcast — Laragram')

@section('content')
    <h1>Broadcast</h1>

    @php($mode = old('content_type', 'text'))

    <form method="POST" action="{{ route('laragram.admin.broadcast.store') }}" class="lg-form" id="lg-broadcast-form">
        @csrf

        <fieldset class="lg-modes">
            <legend>Content</legend>
            <label class="lg-check">
                <input type="radio" name="content_type" value="text" @checked($mode !== 'view') data-lg-mode>
                Text
            </label>
            <label class="lg-check">
                <input type="radio" name="content_type" value="view" @checked($mode === 'view') data-lg-mode @disabled(empty($views))>
                View {{ empty($views) ? '(no views found)' : '' }}
            </label>
        </fieldset>

        <div data-lg-pane="text" @style(['display:none' => $mode === 'view'])>
            <label>
                Message
                <textarea name="message" rows="5" maxlength="4096" placeholder="Your announcement… HTML is supported.">{{ old('message') }}</textarea>
            </label>
        </div>

        <div data-lg-pane="view" @style(['display:none' => $mode !== 'view'])>
            <label>
                View
                <select name="view">
                    <option value="">Select a view…</option>
                    @foreach ($views as $view)
                        <option value="{{ $view }}" @selected(old('view') === $view)>{{ $view }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                Data (JSON)
                <textarea name="data" rows="3" placeholder='{"version": "2.0"}'>{{ old('data') }}</textarea>
            </label>

            <p class="lg-hint">
                Rendered per recipient in their language. Buttons and media come from the view's component files.
            </p>
        </div>

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

    <script>
        (function () {
            var form = document.getElementById('lg-broadcast-form');
            if (!form) return;

            function sync() {
                var mode = form.querySelector('[data-lg-mode]:checked');
                var value = mode ? mode.value : 'text';
                form.querySelectorAll('[data-lg-pane]').forEach(function (pane) {
                    pane.style.display = pane.getAttribute('data-lg-pane') === value ? '' : 'none';
                });
            }

            form.querySelectorAll('[data-lg-mode]').forEach(function (radio) {
                radio.addEventListener('change', sync);
            });

            sync();
        })();
    </script>
@endsection
