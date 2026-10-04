@extends('layouts.app')

@section('title', 'Notification')
@section('heading', 'Notification')

@section('content')
    @php
        // The stored payload, not a re-read of the request: this page has to keep
        // making sense after the thing it is about has been decided, or the
        // address behind it deleted. Everything it needs was copied in when it
        // was written.
        $data = $notification->data;
        $unread = $notification->read_at === null;

        // A stored url rendered as a link is the same open-redirect risk as a
        // stored url used as a redirect, so it is only trusted when it points
        // into this app. Otherwise the page renders with no onward button.
        $onward = $data['url'] ?? null;
        $onward = is_string($onward) && str_starts_with($onward, url('/')) ? $onward : null;
    @endphp

    <div class="panel">
        <div class="panel__head">
            <div>
                <p class="eyebrow mb-1">Update</p>
                <h2 class="panel__question mb-0">
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
                </h2>
            </div>
        </div>

        <div class="panel__body">
            <div class="d-flex align-items-center gap-2 mb-3">
                @if ($unread)
                    <span class="badge badge-amber">New</span>
                @else
                    <span class="badge badge-soft">Read</span>
                @endif
                <span class="text-dim small">{{ $notification->created_at?->diffForHumans() }}</span>
            </div>

            <dl class="row mb-0">
                @if (filled($data['actor'] ?? null))
                    <dt class="col-sm-3 text-dim small">Who acted</dt>
                    <dd class="col-sm-9 mb-2">{{ $data['actor'] }}</dd>
                @endif

                @if (filled($data['action'] ?? null))
                    <dt class="col-sm-3 text-dim small">Action</dt>
                    <dd class="col-sm-9 mb-2">{{ $data['action'] }}</dd>
                @endif

                @if (filled($data['subject'] ?? null))
                    <dt class="col-sm-3 text-dim small">Subject</dt>
                    <dd class="col-sm-9 mb-2">{{ $data['subject'] }}</dd>
                @endif

                @if (filled($data['decision'] ?? null))
                    <dt class="col-sm-3 text-dim small">Decision</dt>
                    <dd class="col-sm-9 mb-2">{{ Str::headline($data['decision']) }}</dd>
                @endif

                @if (filled($data['note'] ?? null))
                    <dt class="col-sm-3 text-dim small">Note</dt>
                    <dd class="col-sm-9 mb-0">&ldquo;{{ $data['note'] }}&rdquo;</dd>
                @endif
            </dl>

            @if ($onward)
                <a href="{{ $onward }}" class="btn btn-primary mt-4">
                    <i class="bi bi-box-arrow-up-right"></i> <span class="ms-1">Open the request</span>
                </a>
            @endif
        </div>
    </div>
@endsection
