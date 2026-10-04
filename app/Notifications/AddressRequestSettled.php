<?php

namespace App\Notifications;

use App\Models\AddressRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells the *other* approvers that a request has been dealt with, so a shared
 * queue does not get worked twice. With two approvers this is the difference
 * between a queue and a race.
 */
class AddressRequestSettled extends Notification
{
    use Queueable;

    public function __construct(private readonly AddressRequest $addressRequest) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $request = $this->addressRequest;

        return [
            'kind' => 'request.settled',
            'request_id' => $request->id,
            'actor' => $request->decider?->name ?? 'A reviewer',
            'action' => $request->actionLabel(),
            'subject' => $request->subjectLabel(),
            'decision' => $request->status,
            'url' => route('requests.show', $request->id),
        ];
    }
}
