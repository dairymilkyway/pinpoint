<?php

namespace App\Notifications;

use App\Models\AddressRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Tells the requester what became of their request. */
class AddressRequestDecided extends Notification
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
            'kind' => 'request.decided',
            'request_id' => $request->id,
            'actor' => $request->decider?->name ?? 'A reviewer',
            'action' => $request->actionLabel(),
            'subject' => $request->subjectLabel(),
            'decision' => $request->status,
            'note' => $request->decision_note,
            'url' => route('requests.show', $request->id),
        ];
    }
}
