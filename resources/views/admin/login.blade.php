<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · Laragram Admin</title>
    <style>
@include('laragram::admin.partials.theme')
        body { margin: 0; background: var(--bg); color: var(--text); min-height: 100vh;
            display: flex; align-items: center; justify-content: center; padding: 24px;
            font: 14px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        .lg-login { width: 100%; max-width: 360px; background: var(--panel);
            border: 1px solid var(--border); border-radius: 12px; padding: 28px 24px;
            box-shadow: var(--shadow); }
        .lg-brand { font-weight: 700; letter-spacing: .3px; font-size: 18px; }
        .lg-sub { color: var(--muted); margin: 4px 0 22px; }
        .lg-alert-error { background: var(--off-bg); color: var(--off-text);
            border: 1px solid var(--border); padding: 10px 14px; border-radius: 8px; margin-bottom: 18px; }
        form { display: flex; flex-direction: column; gap: 14px; }
        label { display: flex; flex-direction: column; gap: 6px; }
        .lg-label { color: var(--muted); font-size: 12px; text-transform: uppercase; letter-spacing: .4px; }
        input { font: inherit; background: var(--bg); color: var(--text);
            border: 1px solid var(--border); border-radius: 8px; padding: 9px 11px; }
        input:focus { outline: none; border-color: var(--accent); }
        .lg-check { flex-direction: row; align-items: center; gap: 8px; }
        .lg-check input { width: auto; }
        button { font: inherit; cursor: pointer; margin-top: 4px;
            background: var(--accent); color: var(--accent-text); border: 1px solid var(--accent);
            border-radius: 8px; padding: 10px 12px; font-weight: 600; }
        button:hover { opacity: .92; }
    </style>
</head>
<body>
    <div class="lg-login">
        <div class="lg-brand">Laragram</div>
        <div class="lg-sub">Sign in to the admin panel</div>

        @if ($errors->any())
            <div class="lg-alert-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('laragram.admin.login.attempt') }}">
            @csrf
            <label>
                <span class="lg-label">Username</span>
                <input type="text" name="username" value="{{ old('username') }}" autofocus required>
            </label>
            <label>
                <span class="lg-label">Password</span>
                <input type="password" name="password" required>
            </label>
            <label class="lg-check">
                <input type="checkbox" name="remember" value="1">
                <span>Remember me</span>
            </label>
            <button type="submit">Sign in</button>
        </form>
    </div>
</body>
</html>
