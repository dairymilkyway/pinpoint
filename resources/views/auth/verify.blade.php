@extends('layouts.app')

@section('title', 'Verify your email')

@section('content')
    <div class="auth-shell">
        <div class="auth-card">
            <div class="auth-card__head">
                <a href="{{ url('/') }}" class="app-brand">
                    <span class="app-brand__mark"><svg class="app-brand__pin" viewBox="6 5 20 20" aria-hidden="true"><path d="M16 25 C13 21 9 18 9 12 A7 7 0 1 1 23 12 C23 18 19 21 16 25 Z M16 9.4 A2.6 2.6 0 1 1 16 14.6 A2.6 2.6 0 1 1 16 9.4 Z" fill-rule="evenodd"/></svg></span>
                    <span>{{ config('app.name', 'Pinpoint') }}</span>
                </a>

                <h1 class="auth-card__title">Verify your email</h1>
                <p class="auth-card__sub">We sent a verification link to your inbox.</p>
            </div>

            <p class="text-dim mb-4">
                Before proceeding, please check your email for a verification link.
            </p>

            <form method="POST" action="{{ route('verification.resend') }}">
                @csrf
                <button type="submit" class="btn btn-outline-secondary w-100">Request another link</button>
            </form>
        </div>
    </div>
@endsection
