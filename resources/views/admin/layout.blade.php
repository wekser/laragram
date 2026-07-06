<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Laragram Admin')</title>
    <style>
        :root {
            --bg: #f6f7f9; --panel: #ffffff; --text: #1c2024; --muted: #6b7280;
            --border: #e5e7eb; --accent: #2563eb; --accent-text: #ffffff;
            --ok-bg: #dcfce7; --ok-text: #166534; --off-bg: #fee2e2; --off-text: #991b1b;
            --shadow: 0 1px 2px rgba(0,0,0,.06);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0f1115; --panel: #171a21; --text: #e6e8eb; --muted: #9aa4b2;
                --border: #262b36; --accent: #3b82f6; --accent-text: #ffffff;
                --ok-bg: #14351f; --ok-text: #86efac; --off-bg: #3a1517; --off-text: #fca5a5;
                --shadow: none;
            }
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text);
            font: 14px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .lg-nav { display: flex; align-items: center; gap: 24px; padding: 12px 20px;
            background: var(--panel); border-bottom: 1px solid var(--border); position: sticky; top: 0; }
        .lg-brand { font-weight: 700; letter-spacing: .3px; }
        .lg-nav nav { display: flex; gap: 16px; flex-wrap: wrap; }
        .lg-nav nav a { color: var(--muted); padding: 4px 2px; }
        .lg-nav nav a.active { color: var(--text); border-bottom: 2px solid var(--accent); }
        .lg-main { max-width: 1040px; margin: 0 auto; padding: 24px 20px 64px; }
        h1 { font-size: 22px; margin: 4px 0 20px; }
        h2 { font-size: 16px; margin: 28px 0 12px; }
        .lg-alert { background: var(--ok-bg); color: var(--ok-text); border: 1px solid var(--border);
            padding: 10px 14px; border-radius: 8px; margin-bottom: 18px; }
        .lg-alert-error { background: var(--off-bg); color: var(--off-text); }
        .lg-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 14px; }
        .lg-card { background: var(--panel); border: 1px solid var(--border); border-radius: 10px;
            padding: 16px; box-shadow: var(--shadow); }
        .lg-card-value { font-size: 26px; font-weight: 700; }
        .lg-card-label { color: var(--muted); font-size: 12px; text-transform: uppercase; letter-spacing: .4px; }
        table { width: 100%; border-collapse: collapse; background: var(--panel);
            border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--border); vertical-align: middle; }
        th { color: var(--muted); font-size: 12px; text-transform: uppercase; letter-spacing: .4px; }
        tr:last-child td { border-bottom: 0; }
        .lg-table-wrap { overflow-x: auto; }
        input, select, textarea, button { font: inherit; }
        input, select, textarea { background: var(--bg); color: var(--text);
            border: 1px solid var(--border); border-radius: 8px; padding: 7px 10px; }
        textarea { width: 100%; resize: vertical; }
        button { cursor: pointer; border: 1px solid var(--border); background: var(--panel);
            color: var(--text); border-radius: 8px; padding: 7px 12px; }
        button:hover { border-color: var(--accent); }
        .lg-btn-primary { background: var(--accent); color: var(--accent-text); border-color: var(--accent); }
        .lg-filters, .lg-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .lg-actions form { display: inline; }
        .lg-badge-ok { background: var(--ok-bg); color: var(--ok-text); padding: 2px 8px; border-radius: 999px; font-size: 12px; }
        .lg-badge-off { background: var(--off-bg); color: var(--off-text); padding: 2px 8px; border-radius: 999px; font-size: 12px; }
        .lg-pager { display: flex; gap: 16px; align-items: center; margin-top: 16px; color: var(--muted); }
        .lg-form { display: flex; flex-direction: column; gap: 14px; max-width: 560px; }
        .lg-form label { display: flex; flex-direction: column; gap: 6px; }
        .lg-check { flex-direction: row !important; align-items: center; gap: 8px !important; }
        .lg-form-actions { display: flex; gap: 10px; }
        .lg-hint { color: var(--muted); margin-top: 16px; }
        code { background: var(--bg); border: 1px solid var(--border); border-radius: 4px; padding: 1px 5px; }
        .lg-muted { color: var(--muted); }
    </style>
</head>
<body>
    <header class="lg-nav">
        <div class="lg-brand">Laragram</div>
        <nav>
            <a href="{{ route('laragram.admin.dashboard') }}" @class(['active' => request()->routeIs('laragram.admin.dashboard')])>Dashboard</a>
            <a href="{{ route('laragram.admin.users') }}" @class(['active' => request()->routeIs('laragram.admin.users')])>Users</a>
            <a href="{{ route('laragram.admin.sessions') }}" @class(['active' => request()->routeIs('laragram.admin.sessions')])>Sessions</a>
            <a href="{{ route('laragram.admin.broadcast') }}" @class(['active' => request()->routeIs('laragram.admin.broadcast')])>Broadcast</a>
        </nav>
    </header>
    <main class="lg-main">
        @if (session('status'))
            <div class="lg-alert">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="lg-alert lg-alert-error">{{ $errors->first() }}</div>
        @endif
        @yield('content')
    </main>
</body>
</html>
