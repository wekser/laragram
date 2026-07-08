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
            font: 14px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased; }
        .lg-login { width: 100%; max-width: 360px; background: var(--panel);
            border: 1px solid var(--border); border-radius: 16px; padding: 32px 28px;
            box-shadow: var(--shadow); }
        .lg-brand { display: flex; align-items: center; gap: 10px; font-weight: 700;
            letter-spacing: .2px; font-size: 19px; }
        .lg-brand-mark { width: 24px; height: 24px; border-radius: 50%; flex: 0 0 auto;
            background: var(--brand-gradient); }
        .lg-sub { color: var(--muted); margin: 6px 0 24px; }
        .lg-alert-error { background: var(--off-bg); color: var(--off-text);
            border: 1px solid transparent; border-left: 3px solid var(--off-text);
            padding: 11px 14px; border-radius: 10px; margin-bottom: 18px; }
        form { display: flex; flex-direction: column; gap: 14px; }
        label { display: flex; flex-direction: column; gap: 6px; }
        .lg-label { color: var(--muted); font-size: 12px; text-transform: uppercase; letter-spacing: .4px; }
        input:not([type=checkbox]) { font: inherit; background: var(--field); color: var(--text);
            border: 1px solid var(--field-border); border-radius: 10px; padding: 10px 12px;
            transition: border-color .15s, box-shadow .15s; }
        input:focus { outline: none; border-color: var(--accent); box-shadow: var(--ring); }
        .lg-check { flex-direction: row; align-items: center; gap: 8px; }
        .lg-check input { width: auto; }
        button { font: inherit; cursor: pointer; margin-top: 4px;
            background: var(--accent); color: var(--accent-text); border: 1px solid var(--accent);
            border-radius: 10px; padding: 11px 12px; font-weight: 600;
            transition: background .15s, box-shadow .15s, transform .1s; }
        button:hover { background: var(--accent-hover); border-color: var(--accent-hover);
            box-shadow: var(--accent-glow); }
        button:active { transform: translateY(1px); }
    </style>
</head>
<body>
    <div class="lg-login">
        <div class="lg-brand"><span class="lg-brand-mark"></span>Laragram</div>
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
