@php
    // The stored payload, not a re-read of the request: a notification has to
    // keep making sense after the thing it is about has been decided, or the
    // address behind it deleted. Everything it needs was copied in when it was
    // written.
    $data = $notification->data;
    $unread = $notification->read_at === null;
@endphp

<tr>
    <td class="text-nowrap">
        @if ($unread)
            <span class="badge badge-amber">New</span>
        @endif
    </td>

    <td>
        <div>
            @switch($data['kind'] ?? '')
                @case('request.raised')
                    {{ $data['actor'] ?? 'Someone' }} asked to {{ $data['action'] ?? 'change an address' }}.
                    @break
                @case('request.decided')
                    {{ $data['actor'] ?? 'A reviewer' }} {{ $data['decision'] ?? 'decided' }} your request
                    to {{ $data['action'] ?? 'change an address' }}.
                    @break
                @case('request.settled')
                    {{ $data['actor'] ?? 'A reviewer' }} {{ $data['decision'] ?? 'decided' }} a request
                    to {{ $data['action'] ?? 'change an address' }}.
                    @break
                @default
                    There is an update on a request.
            @endswitch
        </div>

        @if (! empty($data['subject']))
            <div class="text-dim small">{{ $data['subject'] }}</div>
        @endif

        @if (! empty($data['note']))
            <div class="text-dim small fst-italic">&ldquo;{{ $data['note'] }}&rdquo;</div>
        @endif
    </td>

    <td class="text-dim small text-nowrap">{{ $notification->created_at?->diffForHumans() }}</td>

    <td class="text-end text-nowrap">
        {{-- Opening is what marks it read, so it is a POST rather than a link. --}}
        <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
            @csrf
            <button class="btn btn-sm btn-outline-secondary">
                {{ $unread ? 'Open' : 'View' }}
            </button>
        </form>
    </td>
</tr>
