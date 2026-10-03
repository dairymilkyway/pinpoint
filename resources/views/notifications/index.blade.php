@extends('layouts.app')

@section('title', 'Notifications')
@section('heading', 'Notifications')

@section('content')
    @php($unread = $notifications->getCollection()->whereNull('read_at')->count())

    <div class="panel">
        <div class="panel__head">
            <div>
                <p class="eyebrow mb-1">Inbox</p>
                <p class="mb-0 text-dim small">
                    What happened on the requests you are part of - raised, approved or rejected.
                </p>
            </div>

            @if ($unread > 0)
                <form method="POST" action="{{ route('notifications.readAll') }}">
                    @csrf
                    <button class="btn btn-sm btn-outline-secondary text-nowrap">
                        Mark all as read
                    </button>
                </form>
            @endif
        </div>

        <div class="panel__body panel__body--flush">
            @if ($notifications->isEmpty())
                <p class="text-dim small p-4 mb-0">Nothing yet.</p>
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
