@php
    // Every flash shape as one toast record, so the markup below is written
    // once. .show is rendered server-side, so a message is on screen with or
    // without JavaScript; only the success toast is told to dismiss itself.
    $rejected = session('import_failures', []);
    $unpinned = session('import_unpinned', []);

    $toasts = [];

    if (session('success')) {
        $toasts[] = [
            'variant' => 'success', 'icon' => 'bi-check-circle', 'title' => 'Success',
            'text' => session('success'), 'items' => [], 'autohide' => true,
        ];
    }

    if (session('error')) {
        $toasts[] = [
            'variant' => 'danger', 'icon' => 'bi-exclamation-triangle', 'title' => 'Error',
            'text' => session('error'), 'items' => [], 'autohide' => false,
        ];
    }

    if ($rejected) {
        $toasts[] = [
            'variant' => 'warning', 'icon' => 'bi-exclamation-triangle',
            // The line number is the only handle the reader has on a row: it is
            // what they see in the spreadsheet.
            'title' => count($rejected).' '.Str::plural('row', count($rejected)).' could not be imported',
            'text' => null, 'items' => $rejected, 'autohide' => false,
        ];
    }

    if ($unpinned) {
        // Not an error. The addresses were saved and are correct; the bundled
        // coordinate dataset just has nothing to draw them at, which is a gap it
        // documents rather than a fault in the row.
        $toasts[] = [
            'variant' => 'info', 'icon' => 'bi-geo-alt', 'title' => 'Saved, but not on the map',
            'text' => 'We do not have a location for '.implode(', ', $unpinned).'. '
                .(count($unpinned) === 1 ? 'Addresses in that city are' : 'Addresses in those cities are')
                .' saved without one and will not appear on the map.',
            'items' => [], 'autohide' => false,
        ];
    }

    if ($errors->any()) {
        $toasts[] = [
            'variant' => 'danger', 'icon' => 'bi-exclamation-triangle', 'title' => 'Please fix the following',
            'text' => null, 'items' => $errors->all(), 'autohide' => false,
        ];
    }

    if (session('status')) {
        $toasts[] = [
            'variant' => 'success', 'icon' => 'bi-envelope', 'title' => 'Email sent',
            'text' => session('status'), 'items' => [], 'autohide' => true,
        ];
    }

    if (session('resent')) {
        $toasts[] = [
            'variant' => 'success', 'icon' => 'bi-envelope', 'title' => 'Verification link sent',
            'text' => 'A fresh verification link has been sent to your email address.',
            'items' => [], 'autohide' => true,
        ];
    }
@endphp

@foreach ($toasts as $toast)
    <div class="toast toast--{{ $toast['variant'] }} show"
         role="alert"
         data-toast
         data-bs-autohide="{{ $toast['autohide'] ? 'true' : 'false' }}"
         @if ($toast['autohide']) data-bs-delay="5000" @endif>
        <div class="toast__head">
            <i class="bi {{ $toast['icon'] }}" aria-hidden="true"></i>
            <span class="toast__title">{{ $toast['title'] }}</span>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast__body">
            @if ($toast['text'])
                {{ $toast['text'] }}
            @endif
            @if ($toast['items'])
                <ul class="mb-0 mt-2 ps-3">
                    @foreach ($toast['items'] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endforeach
