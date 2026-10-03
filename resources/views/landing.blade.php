@extends('layouts.app')

@section('title', 'Address Book')

@section('content')
    <div class="landing">
        <div class="landing__nav">
            <span class="app-brand mb-0">
                <span class="app-brand__mark"><i class="bi bi-geo-alt-fill"></i></span>
                <span>{{ config('app.name', 'Address Book') }}</span>
            </span>

            @if (Route::has('login'))
                <a href="{{ route('login') }}" class="btn btn-sm btn-outline-secondary">Sign in</a>
            @endif
        </div>

        <div class="landing__inner">
            <p class="eyebrow mb-3">Address Book</p>

            <h1 class="landing__title">Every address your team needs, in one place.</h1>

            <p class="landing__lede">
                A shared directory of customer, branch and site addresses. Role-based access decides
                who can see, change and export the records, and every rule is enforced on the server
                rather than hidden in the interface.
            </p>

            <div class="landing__actions">
                @if (Route::has('login'))
                    <a href="{{ route('login') }}" class="btn btn-primary px-4">Sign in to the directory</a>
                @endif
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="btn btn-outline-secondary px-4">Create an account</a>
                @endif
            </div>

            <div class="landing__grid">
                <div>
                    <p class="feature__index">01</p>
                    <h2 class="feature__title">Clean records</h2>
                    <p class="feature__body">
                        Create, edit and retire addresses with validation that keeps the directory
                        consistent. Mark one address per owner as the default.
                    </p>
                </div>

                <div>
                    <p class="feature__index">02</p>
                    <h2 class="feature__title">Real permissions</h2>
                    <p class="feature__body">
                        Superadmins, admins and customers each get the controls they are entitled to.
                        Buttons are hidden and the underlying routes are gated, so a hidden button
                        is never the only thing protecting a record.
                    </p>
                </div>

                <div>
                    <p class="feature__index">03</p>
                    <h2 class="feature__title">Export what you see</h2>
                    <p class="feature__body">
                        Filter the table, then export exactly those rows to Excel. The download
                        follows the current search rather than dumping the whole table.
                    </p>
                </div>

                <div>
                    <p class="feature__index">04</p>
                    <h2 class="feature__title">Addresses you can trust</h2>
                    <p class="feature__body">
                        Pick a region, province and city from the official Philippine geographic
                        codes and the rest of the address fills itself in. Saved locations appear
                        as pins on the map.
                    </p>
                </div>
            </div>

        </div>
    </div>
@endsection
