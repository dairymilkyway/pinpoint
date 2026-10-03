@foreach (['success' => 'success', 'error' => 'danger'] as $key => $variant)
    @if (session($key))
        <div class="alert alert-{{ $variant }} alert-dismissible fade show d-flex align-items-start gap-2" role="alert">
            <i class="bi {{ $variant === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle' }} mt-1"></i>
            <div class="flex-grow-1">{{ session($key) }}</div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
@endforeach

@php($rejected = session('import_failures', []))

@if ($rejected)
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <div class="d-flex align-items-start gap-2">
            <i class="bi bi-exclamation-triangle mt-1"></i>
            <div class="flex-grow-1">
                {{-- The line number is the only handle the reader has on a row:
                     it is what they see in the spreadsheet. --}}
                <strong>{{ count($rejected) }} {{ Str::plural('row', count($rejected)) }} could not be imported:</strong>
                <ul class="mb-0 mt-2 ps-3">
                    @foreach ($rejected as $failure)
                        <li>{{ $failure }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@php($unpinned = session('import_unpinned', []))

@if ($unpinned)
    {{-- Not an error. The addresses were saved and are correct; the bundled
         coordinate dataset just has nothing to draw them at, which is a gap it
         documents rather than a fault in the row. Saying so here is the only
         place the reader learns it, since the map simply omits them. --}}
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <div class="d-flex align-items-start gap-2">
            <i class="bi bi-geo-alt mt-1"></i>
            <div class="flex-grow-1">
                <strong>Saved, but not on the map.</strong>
                The bundled dataset has no coordinates for {{ implode(', ', $unpinned) }}.
                {{ count($unpinned) === 1 ? 'Addresses in that city are' : 'Addresses in those cities are' }}
                stored without a location and will not appear on the map.
            </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <div class="d-flex align-items-start gap-2">
            <i class="bi bi-exclamation-triangle mt-1"></i>
            <div class="flex-grow-1">
                <strong>Please fix the following:</strong>
                <ul class="mb-0 mt-2 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
