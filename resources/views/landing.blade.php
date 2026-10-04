@extends('layouts.app')

@section('content')
    @php
        $map = \App\ArchipelagoMap::points();

        // Twelve pins pop up across the map like markers being dropped, chosen fresh on
        // every render so the map is never quite the same twice. Each pin carries its own
        // delay, so they arrive one at a time rather than all together.
        $pinDelay = [];

        foreach ((array) array_rand($map['points'], 12) as $index) {
            $pinDelay[$index] = mt_rand(0, 60) / 10;
        }
    @endphp

    <div class="landing">
        <div class="landing__nav">
            <span class="app-brand mb-0">
                <span class="app-brand__mark"><svg class="app-brand__pin" viewBox="6 5 20 20" aria-hidden="true"><path d="M16 25 C13 21 9 18 9 12 A7 7 0 1 1 23 12 C23 18 19 21 16 25 Z"/></svg></span>
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
                    <p class="eyebrow mb-3">Pinpoint</p>

                    <h1 class="landing__title">Every address your team needs, in one place.</h1>

                    <p class="landing__lede">
                        A shared list of customer, branch and site addresses - added one at a time,
                        or imported from a spreadsheet, and kept straight as the list grows.
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
                            @foreach ($map['points'] as $index => $point)
                                @php
                                    $dotClass = 'atlas__dot';

                                    if ($point['approximate']) {
                                        $dotClass .= ' atlas__dot--approx';
                                    }
                                @endphp
                                <circle class="{{ $dotClass }}"
                                        cx="{{ $point['x'] }}" cy="{{ $point['y'] }}"
                                        r="{{ $point['approximate'] ? 3.4 : 3.2 }}" />
                            @endforeach

                            @foreach ($pinDelay as $index => $delay)
                                <g class="atlas__pin" transform="translate({{ $map['points'][$index]['x'] }}, {{ $map['points'][$index]['y'] }})">
                                    <path style="animation-delay: {{ $delay }}s"
                                          d="M 0 0 C -6 -8 -14 -14 -14 -26 A 14 14 0 1 1 14 -26 C 14 -14 6 -8 0 0 Z" />
                                </g>
                            @endforeach
                        </svg>
                    </div>

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
                    <h2 class="feature__title">Checked before it saves</h2>
                    <p class="feature__body">
                        A customer cannot change the book directly. They file a request instead, and a
                        reader approves or rejects it - so their edit lands only once someone has
                        agreed to it.
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
