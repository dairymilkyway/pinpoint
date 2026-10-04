@extends('layouts.app')

@section('title', 'Import addresses')
@section('heading', 'Import addresses')

@section('content')
    <div class="panel">
        <div class="panel__head">
            <div>
                <p class="eyebrow mb-1">Import</p>
                <h2 class="panel__question mb-0">
                    {{ $proposing
                        ? 'Hold every valid row for approval, and still see which ones failed.'
                        : 'Land every row, or know exactly which ones failed.' }}
                </h2>
            </div>

            <div class="action-bar">
                <a href="{{ route('addresses.import.template') }}" class="btn btn-outline-secondary text-nowrap">
                    <i class="bi bi-download"></i> <span class="ms-1">Download template</span>
                </a>
            </div>
        </div>

        <div class="panel__body">
            <form method="POST" action="{{ route('addresses.import.store') }}" enctype="multipart/form-data">
                @csrf

                @if ($accounts)
                    @if ($selectedAccount)
                        {{-- Locked, because the page was opened for this account:
                             the rows are already spoken for, and a dropdown here
                             would invite reassigning them without ever leaving
                             the form. The account is carried in a hidden field
                             rather than a disabled select, which posts nothing at
                             all - and it is still written out in full, so where
                             the rows are about to land is readable before a file
                             is chosen. --}}
                        <div class="mb-4">
                            <p class="form-label mb-2">Whose addresses are these?</p>
                            <p class="mb-2">
                                <i class="bi bi-person"></i>
                                <strong>{{ $selectedAccount->name }}</strong>
                                <span class="text-dim">({{ $selectedAccount->email }})</span>
                            </p>
                            <input type="hidden" name="user_id" value="{{ $selectedAccount->id }}">
                            @error('user_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                    @else
                        <div class="mb-4">
                            <label for="user_id" class="form-label">Whose addresses are these? <span class="text-dim">*</span></label>
                            <select name="user_id" id="user_id" required
                                    class="form-select @error('user_id') is-invalid @enderror">
                                <option value="">Choose an account&hellip;</option>
                                @foreach ($accounts as $account)
                                    <option value="{{ $account->id }}" @selected((int) old('user_id') === $account->id)>
                                        {{ $account->name }} ({{ $account->email }})
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-hint">
                                Every row lands on this account. A managing role holds none of its own, so it is not listed.
                            </div>
                            @error('user_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    @endif
                @endif

                <div class="mb-4">
                    <label for="file" class="form-label">Spreadsheet <span class="text-dim">*</span></label>
                    <input type="file" name="file" id="file" required accept=".xlsx,.xls,.csv"
                           class="form-control @error('file') is-invalid @enderror">
                    <div class="form-hint">
                        One address per row; type the city by name and the rest follows.
                        {{ $proposing
                            ? 'A failed row is reported by its line number; the valid rows are submitted for approval rather than imported.'
                            : 'A failed row is reported by its line number and the others still import.' }}
                    </div>
                    @error('file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="action-bar pt-3 border-top" style="border-color: var(--line) !important;">
                    <a href="{{ route('addresses.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4">
                        {{ $proposing ? 'Submit for approval' : 'Import' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
