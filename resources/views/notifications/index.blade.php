@extends('layouts.app')

@section('title', 'Notifications')
@section('heading', 'Notifications')

@section('content')
    @php($unread = $notifications->getCollection()->whereNull('read_at')->count())

    <div class="panel">
        <div class="panel__head">
            <p class="eyebrow mb-0">Inbox</p>

            @if ($unread > 0)
                <form method="POST" action="{{ route('notifications.readAll') }}" class="action-bar">
                    @csrf
                    <button class="btn btn-outline-secondary text-nowrap">
                        Mark all as read
                    </button>
                </form>
            @endif
        </div>

        <div class="panel__body panel__body--flush">
            @if ($notifications->isEmpty())
                <div class="table-state">
                    {{-- An empty tray, for the first-run case with nothing here yet. --}}
                    <i class="bi bi-inbox d-block mb-2" aria-hidden="true"></i>
                    <p class="mb-1">Nothing yet.</p>
                    <p class="mb-0 small">Updates on the requests you are part of appear here.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Update</th>
                                <th>When</th>
                                <th class="text-end"><span class="visually-hidden">Open</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($notifications as $notification)
                                @include('notifications.partials.line', ['notification' => $notification])
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="p-3">
                    {{ $notifications->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
