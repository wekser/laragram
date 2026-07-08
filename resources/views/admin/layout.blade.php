<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Laragram Admin')</title>
    <style>
@include('laragram::admin.partials.theme')
        body { margin: 0; background: var(--bg); color: var(--text);
            font: 14px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased; }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .lg-nav { display: flex; align-items: center; gap: 24px; padding: 10px 20px;
            background: color-mix(in srgb, var(--panel) 85%, transparent);
            backdrop-filter: saturate(180%) blur(12px); -webkit-backdrop-filter: saturate(180%) blur(12px);
            border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 10; }
        .lg-brand { display: inline-flex; align-items: center; gap: 8px; font-weight: 700;
            letter-spacing: .2px; font-size: 15px; }
        .lg-brand-mark { width: 20px; height: 20px; border-radius: 50%; flex: 0 0 auto;
            background: var(--brand-gradient); }
        .lg-nav nav { display: flex; gap: 4px; flex-wrap: wrap; }
        .lg-nav nav a { color: var(--muted); padding: 6px 12px; border-radius: 999px;
            font-weight: 500; transition: background .15s, color .15s; }
        .lg-nav nav a:hover, .lg-nav nav a.active { background: var(--accent-soft); color: var(--accent); }
        .lg-nav nav a:hover { text-decoration: none; }
        .lg-main { max-width: 1040px; margin: 0 auto; padding: 28px 20px 64px; }
        h1 { font-size: 24px; font-weight: 700; margin: 4px 0 22px; letter-spacing: -.2px; }
        h2 { font-size: 16px; margin: 30px 0 12px; }
        .lg-alert { background: var(--ok-bg); color: var(--ok-text); border: 1px solid transparent;
            border-left: 3px solid var(--ok-text); padding: 11px 14px; border-radius: 10px; margin-bottom: 18px; }
        .lg-alert-error { background: var(--off-bg); color: var(--off-text); border-left-color: var(--off-text); }
        .lg-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 14px; }
        .lg-card { background: var(--panel); border: 1px solid var(--border); border-radius: var(--radius);
            padding: 18px; box-shadow: var(--shadow); transition: transform .15s, box-shadow .15s; }
        .lg-card:hover { transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0,0,0,.05), 0 12px 24px rgba(0,0,0,.05); }
        .lg-card-value { font-size: 28px; font-weight: 700; letter-spacing: -.4px; }
        .lg-card-label { color: var(--muted); font-size: 12px; text-transform: uppercase; letter-spacing: .4px; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; background: var(--panel); border-radius: var(--radius); overflow: hidden; }
        th, td { text-align: left; padding: 12px 14px; border-bottom: 1px solid var(--border); vertical-align: middle; }
        th { color: var(--muted); font-size: 12px; text-transform: uppercase; letter-spacing: .4px;
            background: color-mix(in srgb, var(--accent-soft) 60%, transparent); font-weight: 600; }
        tbody td { transition: background .12s; }
        tbody tr:hover td { background: var(--row-hover); }
        tr:last-child td { border-bottom: 0; }
        .lg-table-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: var(--radius); }
        input, select, textarea, button { font: inherit; }
        input:not([type=checkbox]):not([type=radio]), select, textarea {
            background: var(--field); color: var(--text);
            border: 1px solid var(--field-border); border-radius: 10px; padding: 9px 12px;
            transition: border-color .15s, box-shadow .15s; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: var(--accent); box-shadow: var(--ring); }
        textarea { width: 100%; resize: vertical; }
        button { cursor: pointer; border: 1px solid var(--border); background: var(--panel);
            color: var(--text); border-radius: 10px; padding: 8px 14px; font-weight: 500;
            transition: background .15s, border-color .15s, transform .1s, box-shadow .15s; }
        button:hover { background: var(--row-hover); border-color: var(--accent); }
        button:active { transform: translateY(1px); }
        .lg-btn-primary { background: var(--accent); color: var(--accent-text); border-color: var(--accent); }
        .lg-btn-primary:hover { background: var(--accent-hover); border-color: var(--accent-hover);
            box-shadow: var(--accent-glow); }
        .lg-filters, .lg-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .lg-actions form { display: inline; }
        .lg-badge-ok, .lg-badge-off { display: inline-flex; align-items: center; gap: 6px;
            padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 500; }
        .lg-badge-ok::before, .lg-badge-off::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .lg-badge-ok { background: var(--ok-bg); color: var(--ok-text); }
        .lg-badge-off { background: var(--off-bg); color: var(--off-text); }
        .lg-pager { display: flex; gap: 16px; align-items: center; margin-top: 16px; color: var(--muted); }
        .lg-form { display: flex; flex-direction: column; gap: 14px; max-width: 560px; }
        .lg-form label { display: flex; flex-direction: column; gap: 6px; }
        .lg-check { flex-direction: row !important; align-items: center; gap: 8px !important; }
        .lg-form-actions { display: flex; gap: 10px; margin-top: 4px; }
        .lg-hint { color: var(--muted); margin-top: 16px; }
        .lg-segment { border: 1px solid var(--border); border-radius: var(--radius); padding: 5px;
            background: var(--field); display: flex; gap: 5px; margin: 0; }
        .lg-segment legend { color: var(--muted); font-size: 12px; text-transform: uppercase;
            letter-spacing: .4px; padding: 0 6px; }
        .lg-segment .lg-check { flex: 1; margin: 0; padding: 8px 12px;
            border-radius: calc(var(--radius) - 5px); border: 1px solid transparent;
            cursor: pointer; transition: background .15s, border-color .15s, box-shadow .15s; }
        .lg-segment .lg-check:hover { background: var(--row-hover); }
        .lg-segment .lg-check:has(input:checked) { background: var(--accent-soft);
            color: var(--accent); border-color: color-mix(in srgb, var(--accent) 25%, transparent); }
        code { background: var(--bg); border: 1px solid var(--border); border-radius: 6px; padding: 1px 5px; }
        .lg-muted { color: var(--muted); }
        @media (max-width: 640px) {
            .lg-nav { flex-wrap: wrap; gap: 10px 14px; padding: 10px 16px; }
            .lg-brand { order: 1; }
            .lg-nav > form { order: 2; margin-left: auto; }
            .lg-nav nav { order: 3; width: 100%; gap: 6px;
                overflow-x: auto; flex-wrap: nowrap; -webkit-overflow-scrolling: touch;
                scrollbar-width: none; }
            .lg-nav nav::-webkit-scrollbar { display: none; }
            .lg-nav nav a { white-space: nowrap; }
            .lg-main { padding: 20px 16px 48px; }
            h1 { font-size: 20px; margin-bottom: 18px; }
            h2 { font-size: 15px; }
            .lg-form { max-width: none; }
            .lg-segment { flex-direction: column; gap: 5px; }
            .lg-form-actions, .lg-filters, .lg-actions { width: 100%; }
            .lg-form-actions button { flex: 1; }
        }
    </style>
</head>
<body>
    <header class="lg-nav">
        <div class="lg-brand"><span class="lg-brand-mark"></span>Laragram</div>
        <nav>
            <a href="{{ route('laragram.admin.dashboard') }}" @class(['active' => request()->routeIs('laragram.admin.dashboard')])>Dashboard</a>
            <a href="{{ route('laragram.admin.users') }}" @class(['active' => request()->routeIs('laragram.admin.users')])>Users</a>
            <a href="{{ route('laragram.admin.sessions') }}" @class(['active' => request()->routeIs('laragram.admin.sessions')])>Sessions</a>
            <a href="{{ route('laragram.admin.broadcast') }}" @class(['active' => request()->routeIs('laragram.admin.broadcast')])>Broadcast</a>
        </nav>
        @if (auth()->guard(config('laragram.admin.guard', 'laragram_admin'))->check())
            <form method="POST" action="{{ route('laragram.admin.logout') }}" style="margin-left:auto">
                @csrf
                <button type="submit">Sign out</button>
            </form>
        @endif
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
