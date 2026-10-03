<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Address Book') &middot; {{ config('app.name', 'Address Book') }}</title>

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
                <span class="app-brand__mark"><i class="bi bi-geo-alt-fill"></i></span>
                <span>{{ config('app.name', 'Address Book') }}</span>
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
                <h1 class="h5 mb-0">@yield('heading', 'Address Book')</h1>

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
                @include('partials.flash')
                @yield('content')
            </main>
        </div>
    </div>
@else
    @include('partials.flash')
    @yield('content')
@endauth

@stack('scripts')
</body>
</html>
