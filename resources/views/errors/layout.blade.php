<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') - {{ config('app.name', 'Pinpoint') }}</title>

    {{-- Deliberately standalone: no @vite, no route(), no auth, no queries.
         Any of those can be the thing that failed, and an error page that
         throws while rendering shows the user nothing at all. Tokens are
         copied from app.scss :root - keep them in step if the palette moves. --}}
    <script>
        (function () {
            var theme = null;
            try { theme = window.localStorage.getItem('theme'); } catch (error) {}
            if (theme !== 'light' && theme !== 'dark') {
                theme = window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
            }
            document.documentElement.setAttribute('data-theme', theme);
            document.documentElement.setAttribute('data-bs-theme', theme);
        })();
    </script>
    <style>
        :root {
            --bg: #0a0c0f; --panel: #11151a; --line: rgba(255, 255, 255, 0.11);
            --text: #dfe4ea; --text-dim: #a2abb6; --text-faint: #8c95a2;
            --amber: #e9a23b; --amber-hot: #f7bc63; --amber-ink: #1a1204; --amber-text: #e9a23b;
            --amber-wash: rgba(233, 162, 59, 0.12);
        }
        [data-theme='light'] {
            --bg: #f4f5f7; --panel: #ffffff; --line: rgba(16, 20, 26, 0.18);
            --text: #12161b; --text-dim: #4e565f; --text-faint: #5a626c;
            --amber-text: #8a5a0e; --amber-wash: rgba(233, 162, 59, 0.14);
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body {
            display: grid; place-items: center; min-height: 100vh; padding: 1.5rem;
            background: var(--bg); color: var(--text);
            font: 400 1rem/1.55 'IBM Plex Sans Variable', system-ui, -apple-system, 'Segoe UI', sans-serif;
        }
        .error { width: 100%; max-width: 30rem; text-align: center; }
        .error__mark {
            display: inline-grid; place-items: center; width: 4.5rem; height: 4.5rem; margin-bottom: 1.5rem;
            border-radius: 50%; background: var(--amber-wash); border: 1px solid var(--line);
        }
        .error__mark svg { width: 2.25rem; height: 2.25rem; fill: var(--amber); }
        .error__code {
            margin: 0 0 .5rem; color: var(--amber-text);
            font: 600 .8rem/1 'IBM Plex Mono', ui-monospace, monospace; letter-spacing: .14em; text-transform: uppercase;
        }
        h1 { margin: 0 0 .75rem; font-size: 1.6rem; font-weight: 600; line-height: 1.25; }
        .error__message { margin: 0 0 2rem; color: var(--text-dim); }
        .error__actions { display: flex; flex-wrap: wrap; gap: .75rem; justify-content: center; }
        .btn {
            display: inline-flex; align-items: center; padding: .55rem 1.1rem; border-radius: 6px;
            border: 1px solid var(--line); background: var(--panel); color: var(--text);
            font: inherit; font-size: .925rem; text-decoration: none; cursor: pointer;
        }
        .btn:hover { border-color: var(--text-faint); }
        .btn--primary { background: var(--amber); border-color: var(--amber); color: var(--amber-ink); font-weight: 600; }
        .btn--primary:hover { background: var(--amber-hot); border-color: var(--amber-hot); }
        .btn:focus-visible { outline: 2px solid var(--amber); outline-offset: 2px; }
        .error__brand { margin-top: 3rem; color: var(--text-faint); font-size: .8rem; }
    </style>
</head>
<body>
<main class="error">
    <span class="error__mark" aria-hidden="true">
        <svg viewBox="6 5 20 20"><path d="M16 25 C13 21 9 18 9 12 A7 7 0 1 1 23 12 C23 18 19 21 16 25 Z M16 9.4 A2.6 2.6 0 1 1 16 14.6 A2.6 2.6 0 1 1 16 9.4 Z" fill-rule="evenodd"/></svg>
    </span>
    <p class="error__code">Error @yield('code')</p>
    <h1>@yield('title')</h1>
    <p class="error__message">@yield('message')</p>
    <div class="error__actions">
        @section('actions')
            <a class="btn btn--primary" href="{{ url('/') }}">Go to home</a>
            <button type="button" class="btn" onclick="history.back()">Go back</button>
        @show
    </div>
    <p class="error__brand">{{ config('app.name', 'Pinpoint') }}</p>
</main>
</body>
</html>
