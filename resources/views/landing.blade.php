@extends('layouts.app')

@section('title', 'Pinpoint')

@section('content')
    @php
        $map = \App\ArchipelagoMap::points();
        $regionCount = count(\App\Geo\PhLocations::regions());
    @endphp

    <div class="landing">
        <div class="landing__nav">
            <span class="app-brand mb-0">
                <span class="app-brand__mark"><i class="bi bi-crosshair"></i></span>
                <span>{{ config('app.name', 'Pinpoint') }}</span>
            </span>

            <div class="d-flex align-items-center gap-2">
                @if (Route::has('login'))
                    <a href="{{ route('login') }}" class="btn btn-sm btn-outline-secondary">Sign in</a>
                @endif

                {{-- The delegated handler in the layout head finds this by
                     [data-theme-toggle]; the landing page needs no script. --}}
                <button type="button"
                        class="btn btn-sm btn-outline-secondary theme-toggle"
                        data-theme-toggle>
                    <i class="bi bi-sun theme-toggle__icon theme-toggle__icon--dark" aria-hidden="true"></i>
                    <i class="bi bi-moon-stars theme-toggle__icon theme-toggle__icon--light" aria-hidden="true"></i>
                    <span class="visually-hidden theme-toggle__label theme-toggle__label--dark">Switch to light theme</span>
                    <span class="visually-hidden theme-toggle__label theme-toggle__label--light">Switch to dark theme</span>
                </button>
            </div>
        </div>

        <div class="landing__inner">
            <div class="landing__hero">
                <div class="landing__copy">
                    <p class="eyebrow mb-3">Philippine addresses</p>

                    <h1 class="landing__title">Every address your team needs, in one place.</h1>

                    <p class="landing__lede">
                        A shared list of customer, branch and site addresses. Superadmins, admins
                        and customers each see only what they should, and every change is checked
                        before it is saved.
                    </p>

                    <div class="landing__actions">
                        @if (Route::has('login'))
                            <a href="{{ route('login') }}" class="btn btn-primary px-4">Sign in to Pinpoint</a>
                        @endif
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="btn btn-outline-secondary px-4">Create an account</a>
                        @endif
                    </div>
                </div>

                <figure class="landing__plate">
                    <div class="landing__frame">
                        <svg class="atlas"
                             viewBox="{{ $map['viewBox'] }}"
                             preserveAspectRatio="xMidYMid meet"
                             role="img"
                             aria-label="A map of the Philippines as {{ $map['counts']['total'] }} city dots: {{ $map['counts']['placed'] }} in their exact spots and {{ $map['counts']['approximate'] }} shown at the centre of their province or region.">
                            @foreach ($map['points'] as $point)
                                @if ($point['approximate'])
                                    <circle class="atlas__dot atlas__dot--approx" cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="3.4" />
                                @else
                                    <circle class="atlas__dot" cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="3.2" />
                                @endif
                            @endforeach
                        </svg>
                    </div>

                    <figcaption class="atlas-caption">
                        <span class="mono">{{ $regionCount }}</span> regions and
                        <span class="mono">{{ $map['counts']['total'] }}</span> cities.
                        <span class="mono">{{ $map['counts']['placed'] }}</span> are shown at their
                        exact location; <span class="mono">{{ $map['counts']['approximate'] }}</span>
                        have no location on record and are shown at the centre of
                        their province or region.

                        <span class="landing__key">
                            <span class="landing__key-item">
                                <span class="landing__swatch" aria-hidden="true"></span>
                                Exact location
                            </span>
                            <span class="landing__key-item">
                                <span class="landing__swatch landing__swatch--approx" aria-hidden="true"></span>
                                Approximate location
                            </span>
                        </span>
                    </figcaption>
                </figure>
            </div>

            <ol class="landing__legend">
                <li>
                    <p class="feature__index">01</p>
                    <h2 class="feature__title">Clean records</h2>
                    <p class="feature__body">
                        Create, edit and remove addresses, with checks that keep the list
                        consistent. Mark one address per owner as the default.
                    </p>
                </li>

                <li>
                    <p class="feature__index">02</p>
                    <h2 class="feature__title">Real permissions</h2>
                    <p class="feature__body">
                        Superadmins, admins and customers each see and change only what they should.
                        Hiding a button is never the only protection; the same rule applies when a
                        change is saved.
                    </p>
                </li>

                <li>
                    <p class="feature__index">03</p>
                    <h2 class="feature__title">Export what you see</h2>
                    <p class="feature__body">
                        Filter the table, then export exactly those rows to Excel. The download
                        follows the current search rather than dumping the whole table.
                    </p>
                </li>

                <li>
                    <p class="feature__index">04</p>
                    <h2 class="feature__title">Addresses you can trust</h2>
                    <p class="feature__body">
                        Pick a region, province and city and the rest of the address fills itself in.
                        Saved addresses appear as pins on the map.
                    </p>
                </li>
            </ol>

            @include('partials.attribution', ['tiles' => false])
        </div>
    </div>
@endsection
