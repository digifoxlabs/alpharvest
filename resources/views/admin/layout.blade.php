<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Admin Panel' }}</title>
    <style>
        :root {
            --bg: #f3efe7;
            --sidebar: #173128;
            --sidebar-soft: #214237;
            --panel: rgba(255, 255, 255, 0.84);
            --panel-strong: #ffffff;
            --ink: #173128;
            --muted: #667870;
            --line: rgba(23, 49, 40, 0.12);
            --accent: #1f8a5f;
            --accent-dark: #136a47;
            --danger: #b6493c;
            --warning: #d6a13f;
            --shadow: 0 24px 60px rgba(23, 49, 40, 0.12);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            color: var(--ink);
            font-family: "Trebuchet MS", sans-serif;
            background:
                radial-gradient(circle at top left, rgba(31, 138, 95, 0.16), transparent 25%),
                linear-gradient(135deg, #eee6d8 0%, #f8f5ef 48%, #e0ebdf 100%);
        }

        a { color: inherit; text-decoration: none; }

        .admin-shell {
            display: grid;
            grid-template-columns: 280px 1fr;
            min-height: 100vh;
        }

        .sidebar {
            padding: 28px 22px;
            background: linear-gradient(180deg, var(--sidebar) 0%, var(--sidebar-soft) 100%);
            color: #eef6f1;
        }

        .brand {
            display: grid;
            gap: 8px;
            margin-bottom: 28px;
        }

        .brand small {
            color: rgba(238, 246, 241, 0.7);
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .brand strong {
            font-size: 1.35rem;
        }

        .nav {
            display: grid;
            gap: 8px;
        }

        .nav a {
            padding: 12px 14px;
            border-radius: 16px;
            color: rgba(255, 255, 255, 0.82);
        }

        .nav a.active,
        .nav a:hover {
            background: rgba(255, 255, 255, 0.12);
            color: white;
        }

        .main {
            padding: 28px;
            display: grid;
            gap: 18px;
        }

        .topbar,
        .panel {
            border-radius: 26px;
            background: var(--panel);
            backdrop-filter: blur(16px);
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.6);
        }

        .topbar {
            padding: 24px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .topbar h1,
        .panel h2,
        .panel h3,
        p {
            margin: 0;
        }

        .topbar p {
            color: var(--muted);
            margin-top: 8px;
            max-width: 60ch;
        }

        .topbar-links {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .button,
        button {
            border: none;
            border-radius: 14px;
            padding: 11px 16px;
            cursor: pointer;
            font-weight: 700;
            background: var(--accent);
            color: white;
        }

        .button.secondary {
            background: white;
            color: var(--ink);
            border: 1px solid var(--line);
        }

        .button.danger,
        button.danger {
            background: var(--danger);
        }

        .grid {
            display: grid;
            gap: 18px;
        }

        .grid.columns-2 {
            grid-template-columns: 1.05fr 0.95fr;
        }

        .panel {
            padding: 22px;
        }

        .metrics {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }

        .metric {
            padding: 18px;
            border-radius: 20px;
            background: var(--panel-strong);
            border: 1px solid var(--line);
        }

        .metric strong {
            display: block;
            font-size: 2rem;
            margin-bottom: 6px;
        }

        .muted {
            color: var(--muted);
        }

        .table {
            display: grid;
            gap: 14px;
            margin-top: 18px;
        }

        .table-row {
            display: grid;
            gap: 8px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--line);
        }

        .table-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
        }

        form.stack {
            display: grid;
            gap: 14px;
            margin-top: 18px;
        }

        label {
            display: grid;
            gap: 8px;
            font-weight: 700;
        }

        input,
        textarea,
        select {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 12px 14px;
            background: rgba(255, 255, 255, 0.92);
            color: var(--ink);
            font: inherit;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .two-up {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .checkbox {
            display: flex;
            gap: 10px;
            align-items: center;
            font-weight: 700;
        }

        .checkbox input {
            width: 18px;
            height: 18px;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .thumb {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 18px;
            border: 1px solid var(--line);
            margin-top: 12px;
            background: white;
        }

        .flash {
            padding: 14px 18px;
            border-radius: 18px;
            font-weight: 700;
        }

        .flash.success {
            background: rgba(31, 138, 95, 0.14);
            color: var(--accent-dark);
        }

        .flash.error {
            background: rgba(182, 73, 60, 0.12);
            color: var(--danger);
        }

        @media (max-width: 1024px) {
            .admin-shell {
                grid-template-columns: 1fr;
            }

            .grid.columns-2,
            .two-up {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-shell">
        <aside class="sidebar">
            <div class="brand">
                <small>AlphaHarvest</small>
                <strong>Admin Panel</strong>
            </div>

            <nav class="nav">
                <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">Overview</a>
                <a href="{{ route('admin.tenants.index') }}" class="{{ request()->routeIs('admin.tenants.*') ? 'active' : '' }}">Tenants</a>
                <a href="{{ route('admin.stores.index') }}" class="{{ request()->routeIs('admin.stores.*') ? 'active' : '' }}">Stores</a>
                <a href="{{ route('admin.categories.index') }}" class="{{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">Categories</a>
                <a href="{{ route('admin.products.index') }}" class="{{ request()->routeIs('admin.products.*') ? 'active' : '' }}">Products</a>
                <a href="{{ route('platform.home') }}">Public home</a>
            </nav>
        </aside>

        <main class="main">
            <section class="topbar">
                <div>
                    <h1>{{ $heading ?? 'Admin Panel' }}</h1>
                    <p>{{ $subheading ?? 'Manage tenants, stores, catalog structure, and sellable inventory from one backend surface.' }}</p>
                </div>
                <div class="topbar-links">
                    <a class="button secondary" href="{{ route('platform.home') }}">Open platform</a>
                    <a class="button" href="{{ route('admin.dashboard') }}">Admin home</a>
                </div>
            </section>

            @if (session('status'))
                <div class="flash success">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="flash error">
                    {{ $errors->first() }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</body>
</html>
