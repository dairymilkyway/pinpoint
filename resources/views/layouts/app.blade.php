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
@auth
    <div class="app-shell">
        <aside class="app-sidebar">
            <a href="{{ route('addresses.index') }}" class="app-brand">
                <span class="app-brand__mark"><i class="bi bi-geo-alt-fill"></i></span>
                <span>{{ config('app.name', 'Address Book') }}</span>
            </a>

            <ul class="app-nav">
                <li>
                    <a class="app-nav__link {{ request()->routeIs('addresses.*') ? 'is-active' : '' }}"
                       href="{{ route('addresses.index') }}">
                        <i class="bi bi-geo-alt"></i>
                        <span>Addresses</span>
                        <span class="app-nav__index">01</span>
                    </a>
                </li>
                @can('rbac.manage')
                    <li>
                        <a class="app-nav__link {{ request()->routeIs('rbac.*') ? 'is-active' : '' }}"
                           href="{{ route('rbac.index') }}">
                            <i class="bi bi-shield-lock"></i>
                            <span>RBAC</span>
                            <span class="app-nav__index">02</span>
                        </a>
                    </li>
                @endcan
            </ul>
        </aside>

        <div class="app-main">
            <header class="app-header">
                <h1 class="h5 mb-0">@yield('heading', 'Address Book')</h1>

                <div class="app-header__user">
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
                @include('partials.attribution')
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
