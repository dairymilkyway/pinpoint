<?php

namespace App\Notifications;

use App\Models\AddressRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells the approvers there is something in the queue.
 *
 * Sent synchronously, and every notification here is: QUEUE_CONNECTION is
 * database and nothing runs a queue worker in this app, so implementing
 * ShouldQueue would leave the message in the jobs table looking like a bug
 * rather than arriving. If a worker is ever run, these become queued by adding
 * the interface and nothing else.
 */
class AddressRequestRaised extends Notification
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
            'kind' => 'request.raised',
            'request_id' => $request->id,
            'actor' => $request->user->name,
            'action' => $request->actionLabel(),
            'subject' => $request->subjectLabel(),
            'url' => route('requests.show', $request->id),
        ];
    }
}
