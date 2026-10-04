@extends('layouts.app')

@section('title', 'Create an account')

@section('content')
    <div class="auth-shell">
        <div class="auth-card">
            <div class="auth-card__head">
                <a href="{{ url('/') }}" class="app-brand">
                    <span class="app-brand__mark"><svg class="app-brand__pin" viewBox="6 5 20 20" aria-hidden="true"><path d="M16 25 C13 21 9 18 9 12 A7 7 0 1 1 23 12 C23 18 19 21 16 25 Z"/></svg></span>
                    <span>{{ config('app.name', 'Pinpoint') }}</span>
                </a>

                <h1 class="auth-card__title">Create an account</h1>
                <p class="auth-card__sub">You start with the Customer role, which sees only the addresses you add. A Superadmin can widen it.</p>
            </div>

            <form method="POST" action="{{ route('register') }}">
                @csrf

                <div class="mb-3">
                    <label for="name" class="form-label">Name</label>
                    <input id="name" type="text"
                           class="form-control @error('name') is-invalid @enderror"
                           name="name" value="{{ old('name') }}"
                           required autocomplete="name" autofocus>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input id="email" type="email"
                           class="form-control @error('email') is-invalid @enderror"
                           name="email" value="{{ old('email') }}"
                           required autocomplete="email">
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label">Mobile number</label>
                    <input id="phone" type="tel"
                           class="form-control @error('phone') is-invalid @enderror"
                           name="phone" value="{{ old('phone') }}"
                           required autocomplete="tel" placeholder="0917 123 4567">
                    @error('phone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input id="password" type="password"
                           class="form-control @error('password') is-invalid @enderror"
                           name="password" required autocomplete="new-password">
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="password-confirm" class="form-label">Confirm password</label>
                    <input id="password-confirm" type="password" class="form-control"
                           name="password_confirmation" required autocomplete="new-password">
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2">Create account</button>
            </form>

            <div class="auth-card__foot text-center">
                Already have an account?
                <a href="{{ route('login') }}" class="text-decoration-none">Sign in</a>
            </div>
        </div>
    </div>
@endsection
