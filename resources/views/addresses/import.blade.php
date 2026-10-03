@extends('layouts.app')

@section('title', 'Import addresses')
@section('heading', 'Import addresses')

@section('content')
    <div class="panel" style="max-width: 60rem;">
        <div class="panel__head">
            <div>
                <p class="eyebrow mb-1">Import</p>
                <p class="mb-0 text-dim small">
                    One address per row. The headings come from the template.
                    Write the city by name - "Cebu City", "Vigan" - and the province,
                    region, postal code and map coordinates follow from it, the same
                    way they do on the create form. Leave the city blank and type the
                    rest yourself to add one by hand. A name several places share takes
                    the province after a comma: "San Isidro, Nueva Ecija".
                    A few cities have no map coordinates in the bundled dataset - those
                    rows still import, and the result will say so.
                </p>
            </div>

            <a href="{{ route('addresses.import.template') }}" class="btn btn-outline-secondary btn-sm text-nowrap">
                <i class="bi bi-download"></i> <span class="ms-1">Download template</span>
            </a>
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
                            <div class="form-hint">
                                Every row lands on this account. To import for someone
                                else, open their addresses from the users list first.
                            </div>
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
                                Every row lands on this account, whichever name the sheet carries.
                                The managing roles do not hold addresses of their own, so they are not listed.
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
                        xlsx, xls or csv, up to 2 MB. A row that fails is reported by its line number
                        and the rest are still imported.
                    </div>
                    @error('file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="d-flex gap-2 pt-3 border-top" style="border-color: var(--line) !important;">
                    <button type="submit" class="btn btn-primary px-4">Import</button>
                    <a href="{{ route('addresses.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
