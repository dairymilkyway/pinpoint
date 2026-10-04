@extends('layouts.app')

@section('title', 'Confirm password')

@section('content')
    <div class="auth-shell">
        <div class="auth-card">
            <div class="auth-card__head">
                <a href="{{ url('/') }}" class="app-brand">
                    <span class="app-brand__mark"><svg class="app-brand__pin" viewBox="6 5 20 20" aria-hidden="true"><path d="M16 25 C13 21 9 18 9 12 A7 7 0 1 1 23 12 C23 18 19 21 16 25 Z"/></svg></span>
                    <span>{{ config('app.name', 'Pinpoint') }}</span>
                </a>

                <h1 class="auth-card__title">Confirm your password</h1>
                <p class="auth-card__sub">Please confirm your password before continuing.</p>
            </div>

            <form method="POST" action="{{ route('password.confirm') }}">
                @csrf

                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <input id="password" type="password"
                           class="form-control @error('password') is-invalid @enderror"
                           name="password" required autocomplete="current-password" autofocus>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2">Confirm password</button>
            </form>

            @if (Route::has('password.request'))
                <div class="auth-card__foot text-center">
                    <a href="{{ route('password.request') }}" class="text-decoration-none">Forgot your password?</a>
                </div>
            @endif
        </div>
    </div>
@endsection
