@extends('layouts.app')

@section('title', 'Sign in')

@section('content')
    @php
        $demoAccounts = \App\DemoLogin::accounts();
    @endphp

    <div class="auth-shell">
        <div class="auth-card">
            <div class="auth-card__head">
                <a href="{{ url('/') }}" class="app-brand">
                    <span class="app-brand__mark"><svg class="app-brand__pin" viewBox="6 5 20 20" aria-hidden="true"><path d="M16 25 C13 21 9 18 9 12 A7 7 0 1 1 23 12 C23 18 19 21 16 25 Z"/></svg></span>
                    <span>{{ config('app.name', 'Pinpoint') }}</span>
                </a>

                <h1 class="auth-card__title">Sign in</h1>
                <p class="auth-card__sub">Sign in with the account you were given.</p>
            </div>

            @if ($demoAccounts)
                {{-- Showcase affordance: each card fills the form below and
                     submits it, so the login still runs through the normal
                     guard. Not rendered at all in production - see
                     App\DemoLogin. --}}
                <div class="demo-picker" data-demo-picker>
                    <div class="demo-picker__head">
                        <span class="eyebrow">Demo accounts</span>
                        <span class="demo-picker__note">One click signs you in</span>
                    </div>

                    <div class="demo-picker__grid">
                        @foreach ($demoAccounts as $account)
                            <button type="button"
                                    class="demo-card"
                                    data-demo-account
                                    data-email="{{ $account['email'] }}"
                                    data-password="{{ $account['password'] }}">
                                <span class="demo-card__role">{{ $account['role'] }}</span>
                                <span class="demo-card__name">{{ $account['name'] }}</span>
                                <span class="demo-card__email">{{ $account['email'] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="auth-divider"><span>or sign in manually</span></div>
            @endif

            <form method="POST" action="{{ route('login') }}" id="loginForm">
                @csrf

                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input id="email" type="email"
                           class="form-control @error('email') is-invalid @enderror"
                           name="email" value="{{ old('email') }}"
                           required autocomplete="email" autofocus>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input id="password" type="password"
                           class="form-control @error('password') is-invalid @enderror"
                           name="password" required autocomplete="current-password">
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember"
                           {{ old('remember') ? 'checked' : '' }}>
                    <label class="form-check-label" for="remember">Remember me</label>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2">Sign in</button>

                @if (Route::has('password.request'))
                    <div class="text-center mt-3">
                        <a class="form-hint text-decoration-none" href="{{ route('password.request') }}">
                            Forgot your password?
                        </a>
                    </div>
                @endif
            </form>

            @if (Route::has('register'))
                <div class="auth-card__foot text-center">
                    No account yet?
                    <a href="{{ route('register') }}" class="text-decoration-none">Create one</a>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Fills the real sign-in form and submits it. No auth is bypassed: the
        // credentials still have to be correct and the POST goes through the
        // normal LoginController.
        (() => {
            const picker = document.querySelector('[data-demo-picker]');
            const form = document.getElementById('loginForm');

            if (!picker || !form) return;

            const email = form.querySelector('#email');
            const password = form.querySelector('#password');

            picker.querySelectorAll('[data-demo-account]').forEach((card) => {
                card.addEventListener('click', () => {
                    if (!card.dataset.email || !card.dataset.password) return;

                    email.value = card.dataset.email;
                    password.value = card.dataset.password;
                    form.submit();
                });
            });
        })();
    </script>
@endpush
