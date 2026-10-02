@extends('layouts.app')

@section('title', 'Sign in')

@section('content')
    @php($demoAccounts = \App\DemoLogin::accounts())

    <div class="auth-shell">
        <div class="auth-card">
            <div class="auth-card__head">
                <a href="{{ url('/') }}" class="app-brand">
                    <span class="app-brand__mark"><i class="bi bi-geo-alt-fill"></i></span>
                    <span>{{ config('app.name', 'Address Book') }}</span>
                </a>

                <h1 class="auth-card__title">Sign in</h1>
                <p class="auth-card__sub">Use the credentials issued to you.</p>
            </div>

            @if ($demoAccounts)
                {{-- Showcase affordance: fills the form below and submits it, so the
                     login still runs through the normal guard. Not rendered at all
                     in production - see App\DemoLogin. --}}
                <div class="demo-picker" data-demo-picker>
                    <div class="demo-picker__head">
                        <span class="eyebrow">Demo accounts</span>
                        <span class="demo-picker__note">Switch roles without typing</span>
                    </div>

                    <div class="demo-picker__row">
                        <select class="form-select" data-demo-select aria-label="Choose a demo account">
                            <option value="">Choose a role&hellip;</option>
                            @foreach ($demoAccounts as $account)
                                <option value="{{ $loop->index }}"
                                        data-email="{{ $account['email'] }}"
                                        data-password="{{ $account['password'] }}">
                                    {{ $account['role'] }} &middot; {{ $account['name'] }}
                                </option>
                            @endforeach
                        </select>

                        <button type="button" class="btn btn-ghost" data-demo-submit>
                            Sign in
                        </button>
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
            if (!picker) return;

            const select = picker.querySelector('[data-demo-select]');
            const form = document.getElementById('loginForm');

            if (!select || !form) return;

            picker.querySelector('[data-demo-submit]').addEventListener('click', () => {
                const option = select.selectedOptions[0];

                if (!option || !option.dataset.email) {
                    select.focus();
                    return;
                }

                form.querySelector('#email').value = option.dataset.email;
                form.querySelector('#password').value = option.dataset.password;
                form.submit();
            });
        })();
    </script>
@endpush
