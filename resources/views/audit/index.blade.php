@extends('layouts.app')

@section('title', 'Audit log')
@section('heading', 'Audit log')

@section('content')
    {{-- Append-only, so there is nothing to do here but read. The filter is a
         plain GET form: the state belongs in the URL so a filtered view can be
         linked to. --}}
    <div class="panel">
        <div class="panel__head">
            <div>
                <p class="eyebrow mb-1">History</p>
                <p class="mb-0 text-dim small">
                    Every write to the directory and every decision on a request, newest first.
                </p>
            </div>

            <form method="GET" action="{{ route('audit.index') }}" class="d-flex gap-2 align-items-center">
                <label class="visually-hidden" for="audit-event">Filter by event</label>
                <select name="event" id="audit-event" class="form-select form-select-sm" style="width: 14rem;">
                    <option value="">All events</option>
                    @foreach ($events as $value => $label)
                        <option value="{{ $value }}" @selected($event === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="btn btn-sm btn-outline-secondary text-nowrap">Filter</button>
                @if ($event !== '')
                    <a href="{{ route('audit.index') }}" class="btn btn-sm btn-ghost text-nowrap">Clear</a>
                @endif
            </form>
        </div>

        <div class="panel__body panel__body--flush">
            @if ($logs->isEmpty())
                <p class="text-dim small p-4 mb-0">Nothing recorded for that filter.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Who</th>
                                <th>Event</th>
                                <th>Subject</th>
                                <th>Change</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($logs as $log)
                                @php
                                    // Two shapes reach this screen. An edit is
                                    // stored as the fields that moved, with both
                                    // sides; a creation, a deletion and every
                                    // request event only have one side, so there
                                    // is nothing to compare against.
                                    $lines = [];

                                    if ($log->before !== null && $log->after !== null) {
                                        foreach ($log->after as $field => $value) {
                                            $lines[] = [Str::headline($field), $log->before[$field] ?? null, $value];
                                        }
                                    } else {
                                        foreach (($log->after ?? $log->before ?? []) as $field => $value) {
                                            if ($value === null || $value === '') {
                                                continue;
                                            }
                                            $lines[] = [Str::headline($field), null, $value];
                                        }
                                    }

                                    $orEmpty = fn ($value) => ($value === null || $value === '') ? 'empty' : $value;
                                @endphp

                                <tr>
                                    <td class="text-dim small text-nowrap">
                                        {{ $log->created_at?->diffForHumans() }}
                                        <div>{{ $log->created_at?->format('j M Y, H:i') }}</div>
                                    </td>

                                    <td>
                                        {{-- The actor is nullable on purpose: an
                                             account can be removed without
                                             erasing the history of what it did. --}}
                                        {{ $log->actor?->name ?? 'Removed account' }}
                                    </td>

                                    <td><span class="badge badge-soft">{{ $log->label() }}</span></td>

                                    <td class="small">
                                        @if ($log->subject_type)
                                            {{ Str::headline(class_basename($log->subject_type)) }}
                                            <span class="text-dim">#{{ $log->subject_id }}</span>
                                        @else
                                            <span class="text-dim">-</span>
                                        @endif
                                    </td>

                                    <td class="small">
                                        @forelse ($lines as [$field, $from, $to])
                                            <div>
                                                <span class="text-dim">{{ $field }}:</span>
                                                @if ($from !== null)
                                                    <span class="text-dim text-decoration-line-through">{{ $orEmpty($from) }}</span>
                                                    <span class="text-dim">-&gt;</span>
                                                @endif
                                                <span>{{ $orEmpty($to) }}</span>
                                            </div>
                                        @empty
                                            <span class="text-dim">-</span>
                                        @endforelse
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="p-3">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
