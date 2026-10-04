@extends('layouts.app')

@section('title', 'Reset password')

@section('content')
    <div class="auth-shell">
        <div class="auth-card">
            <div class="auth-card__head">
                <a href="{{ url('/') }}" class="app-brand">
                    <span class="app-brand__mark"><svg class="app-brand__pin" viewBox="6 5 20 20" aria-hidden="true"><path d="M16 25 C13 21 9 18 9 12 A7 7 0 1 1 23 12 C23 18 19 21 16 25 Z M16 9.4 A2.6 2.6 0 1 1 16 14.6 A2.6 2.6 0 1 1 16 9.4 Z" fill-rule="evenodd"/></svg></span>
                    <span>{{ config('app.name', 'Pinpoint') }}</span>
                </a>

                <h1 class="auth-card__title">Reset your password</h1>
                <p class="auth-card__sub">We will email you a link to choose a new one.</p>
            </div>

            <form method="POST" action="{{ route('password.email') }}">
                @csrf

                <div class="mb-4">
                    <label for="email" class="form-label">Email address</label>
                    <input id="email" type="email"
                           class="form-control @error('email') is-invalid @enderror"
                           name="email" value="{{ old('email') }}"
                           required autocomplete="email" autofocus>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2">Send reset link</button>
            </form>

            <div class="auth-card__foot text-center">
                <a href="{{ route('login') }}" class="text-decoration-none">Back to sign in</a>
            </div>
        </div>
    </div>
@endsection
