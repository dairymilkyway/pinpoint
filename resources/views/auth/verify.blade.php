@extends('layouts.app')

@section('title', 'Verify your email')

@section('content')
    <div class="auth-shell">
        <div class="auth-card">
            <div class="auth-card__head">
                <a href="{{ url('/') }}" class="app-brand">
                    <span class="app-brand__mark"><i class="bi bi-crosshair"></i></span>
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
