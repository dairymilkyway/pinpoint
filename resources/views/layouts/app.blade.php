<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@hasSection('title')@yield('title') &middot; {{ config('app.name', 'Pinpoint') }}@else{{ config('app.name', 'Pinpoint') }}@endif</title>

    {{-- The stored theme choice lives in localStorage, so the server cannot
         render it. This runs before the stylesheet and the bundle below, so
         <html> carries the resolved theme before first paint and there is no
         flash of the wrong one. It sets BOTH data-theme (our tokens) and
         data-bs-theme (Bootstrap's own dark rules) as one state: flipping only
         one leaves .form-select, .form-switch and .navbar-toggler-icon half
         themed. Order is stored choice, then prefers-color-scheme, then dark.

         The toggle handler rides along here rather than in the bundle: it is
         the one control that must keep working if the bundle fails to load. --}}
    <script>
        (function () {
            var root = document.documentElement;
            var KEY = 'theme';

            function read() {
                try {
                    return window.localStorage.getItem(KEY);
                } catch (error) {
                    return null;
                }
            }

            function write(value) {
                try {
                    window.localStorage.setItem(KEY, value);
                } catch (error) {
                    // Storage can be blocked or throw in private mode. The
                    // choice still applies for this page; it is just not
                    // remembered. Never let it stop the page rendering.
                }
            }

            function apply(theme) {
                root.setAttribute('data-theme', theme);
                root.setAttribute('data-bs-theme', theme);
            }

            var stored = read();
            var theme = stored === 'light' || stored === 'dark'
                ? stored
                : (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');

            apply(theme);

            // The icon and the visible name both follow [data-theme] from CSS,
            // so the control is already correct at first paint. The handler
            // only flips the state and remembers it.
            document.addEventListener('click', function (event) {
                var button = event.target.closest && event.target.closest('[data-theme-toggle]');

                if (!button) {
                    return;
                }

                var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                apply(next);
                write(next);
            });
        })();
    </script>

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <meta name="theme-color" content="#0d1014">

    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body>
{{-- Every full page load that the user starts themselves - sign in, save,
     delete - is a wait with nothing on screen otherwise. One element, shown by
     the submit handler in app.js, reused on every page. --}}
<div class="page-progress" aria-hidden="true"></div>

@auth
    <div class="app-shell">
        <aside class="app-sidebar">
            <a href="{{ route('addresses.index') }}" class="app-brand">
                <span class="app-brand__mark"><svg class="app-brand__pin" viewBox="6 5 20 20" aria-hidden="true"><path d="M16 25 C13 21 9 18 9 12 A7 7 0 1 1 23 12 C23 18 19 21 16 25 Z M16 9.4 A2.6 2.6 0 1 1 16 14.6 A2.6 2.6 0 1 1 16 9.4 Z" fill-rule="evenodd"/></svg></span>
                <span>{{ config('app.name', 'Pinpoint') }}</span>
            </a>

            <ul class="app-nav">
                <li>
                    <a class="app-nav__link {{ request()->routeIs('home') ? 'is-active' : '' }}"
                       href="{{ route('home') }}">
                        <i class="bi bi-grid-1x2"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a class="app-nav__link {{ request()->routeIs('addresses.*') ? 'is-active' : '' }}"
                       href="{{ route('addresses.index') }}">
                        <i class="bi bi-geo-alt"></i>
                        <span>Addresses</span>
                    </a>
                </li>
                <li>
                    <a class="app-nav__link {{ request()->routeIs('requests.*') ? 'is-active' : '' }}"
                       href="{{ route('requests.index') }}">
                        <i class="bi bi-inbox"></i>
                        <span>Requests</span>
                    </a>
                </li>
                @if (auth()->user()?->hasRole(\App\Rbac::CUSTOMER_ROLE))
                    <li>
                        <a class="app-nav__link {{ request()->routeIs('account.*') ? 'is-active' : '' }}"
                           href="{{ route('account.edit') }}">
                            <i class="bi bi-person-gear"></i>
                            <span>Account</span>
                        </a>
                    </li>
                @endif
                @can('audit.view')
                    <li>
                        <a class="app-nav__link {{ request()->routeIs('audit.*') ? 'is-active' : '' }}"
                           href="{{ route('audit.index') }}">
                            <i class="bi bi-clock-history"></i>
                            <span>Audit log</span>
                        </a>
                    </li>
                @endcan
                @can('rbac.manage')
                    <li>
                        <a class="app-nav__link {{ request()->routeIs('rbac.*') ? 'is-active' : '' }}"
                           href="{{ route('rbac.index') }}">
                            <i class="bi bi-shield-lock"></i>
                            <span>Roles &amp; permissions</span>
                        </a>
                    </li>
                @endcan
            </ul>
        </aside>

        <div class="app-main">
            <header class="app-header">
                <h1 class="h5 mb-0">@yield('heading', 'Pinpoint')</h1>

                <div class="app-header__user">
                    @php($unread = auth()->user()->unreadNotifications->count())

                    {{-- A count on a plain link rather than a dropdown: the whole
                         list is one page, and a menu would need JavaScript to
                         fetch what a link already has. --}}
                    <a href="{{ route('notifications.index') }}"
                       class="btn btn-sm btn-outline-secondary position-relative {{ request()->routeIs('notifications.*') ? 'active' : '' }}"
                       title="{{ $unread > 0 ? $unread.' unread notifications' : 'No unread notifications' }}">
                        <i class="bi {{ $unread > 0 ? 'bi-bell-fill' : 'bi-bell' }}"></i>
                        <span class="visually-hidden">Notifications</span>
                        {{-- On screen at zero as well. A badge that disappears
                             when there is nothing to count reads as decoration,
                             so the first time it does appear it looks like an
                             alert rather than a total. --}}
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill {{ $unread > 0 ? 'badge-amber' : 'badge-soft' }}">
                            {{ $unread }}
                        </span>
                    </a>

                    {{-- Theme is one choice, not two. The icon is the theme the
                         click leads to; the name says the action in words, so
                         the control never depends on the glyph. Both follow the
                         resolved [data-theme] in CSS, so the control is correct
                         at first paint rather than corrected once the bundle
                         loads. The inactive name is display:none, so assistive
                         tech announces only the action on offer. --}}
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary theme-toggle"
                            data-theme-toggle>
                        <i class="bi bi-sun theme-toggle__icon theme-toggle__icon--dark" aria-hidden="true"></i>
                        <i class="bi bi-moon-stars theme-toggle__icon theme-toggle__icon--light" aria-hidden="true"></i>
                        <span class="visually-hidden theme-toggle__label theme-toggle__label--dark">Switch to light theme</span>
                        <span class="visually-hidden theme-toggle__label theme-toggle__label--light">Switch to dark theme</span>
                    </button>

                    <span class="app-avatar">{{ Str::of(auth()->user()->name)->substr(0, 2)->upper() }}</span>
                    <span class="d-none d-sm-flex flex-column lh-1">
                        <span class="small">{{ auth()->user()->name }}</span>
                        <span class="eyebrow mt-1">{{ auth()->user()->getRoleNames()->first() ?? 'No role' }}</span>
                    </span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="Log out">
                            <i class="bi bi-box-arrow-right"></i>
                            <span class="d-none d-md-inline ms-1">Log out</span>
                        </button>
                    </form>
                </div>
            </header>

            <main class="app-content">
                <div class="toast-container" aria-live="polite" aria-atomic="false">
                    @include('partials.flash')
                </div>
                @yield('content')
            </main>
        </div>
    </div>
@else
    <div class="toast-container" aria-live="polite" aria-atomic="false">
        @include('partials.flash')
    </div>
    @yield('content')
@endauth

@stack('scripts')
</body>
</html>
