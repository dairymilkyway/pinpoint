@extends('layouts.app')

@section('title', 'Account settings')
@section('heading', 'Account settings')

@section('content')
    <div class="panel mb-4">
        <div class="panel__head">
            <div>
                <p class="eyebrow mb-1">Profile</p>
                <h2 class="panel__question mb-0">Your details.</h2>
            </div>
        </div>

        <div class="panel__body">
            <form method="POST" action="{{ route('account.update') }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="name" class="form-label">Name</label>
                    <input id="name" type="text"
                           class="form-control @error('name') is-invalid @enderror"
                           name="name" value="{{ old('name', $user->name) }}"
                           required autocomplete="name">
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input id="email" type="email"
                           class="form-control @error('email') is-invalid @enderror"
                           name="email" value="{{ old('email', $user->email) }}"
                           required autocomplete="email">
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="phone" class="form-label">Mobile number</label>
                    <input id="phone" type="tel"
                           class="form-control @error('phone') is-invalid @enderror"
                           name="phone" value="{{ old('phone', $user->phone) }}"
                           required autocomplete="tel" placeholder="0917 123 4567">
                    @error('phone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="action-bar action-bar--end pt-3 border-top" style="border-color: var(--line) !important;">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>

    <div class="panel mb-4">
        <div class="panel__head">
            <div>
                <p class="eyebrow mb-1">Password</p>
                <h2 class="panel__question mb-0">Change your password.</h2>
            </div>
        </div>

        <div class="panel__body">
            <form method="POST" action="{{ route('account.password') }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="current_password" class="form-label">Current password</label>
                    <input id="current_password" type="password"
                           class="form-control @error('current_password') is-invalid @enderror"
                           name="current_password" required autocomplete="current-password">
                    @error('current_password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">New password</label>
                    <input id="password" type="password"
                           class="form-control @error('password') is-invalid @enderror"
                           name="password" required autocomplete="new-password">
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="password-confirm" class="form-label">Confirm new password</label>
                    <input id="password-confirm" type="password" class="form-control"
                           name="password_confirmation" required autocomplete="new-password">
                </div>

                <div class="action-bar action-bar--end pt-3 border-top" style="border-color: var(--line) !important;">
                    <button type="submit" class="btn btn-primary">Update password</button>
                </div>
            </form>
        </div>
    </div>

    <div class="panel">
        <div class="panel__head">
            <div>
                <p class="eyebrow mb-1">Deactivate</p>
                <h2 class="panel__question mb-0">Turn off your account.</h2>
            </div>
        </div>

        <div class="panel__body">
            <p class="text-dim">
                Deactivating signs you out and stops you signing in. Your addresses
                stay in the book with your name still attached, and an administrator
                can restore the account later. This is not a delete.
            </p>

            <button type="button" class="btn btn-danger"
                    data-bs-toggle="modal" data-bs-target="#accountDeactivateModal">
                <i class="bi bi-person-slash"></i> <span class="ms-1">Deactivate account</span>
            </button>
        </div>
    </div>

    <div class="modal fade" id="accountDeactivateModal" tabindex="-1" aria-labelledby="accountDeactivateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('account.destroy') }}">
                @csrf
                @method('DELETE')

                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <p class="eyebrow mb-1">Confirm</p>
                            <h5 class="modal-title" id="accountDeactivateModalLabel">Deactivate account</h5>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <p class="mb-0">
                            Deactivate your account? You will be signed out and cannot
                            sign in again.
                        </p>
                        <p class="mb-0 text-dim small mt-2">
                            Your addresses stay in the directory with your name still
                            attached. An administrator can restore the account.
                        </p>
                    </div>

                    <div class="modal-footer action-bar">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-person-slash"></i> <span class="ms-1">Deactivate</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
