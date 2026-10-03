<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The bell. Every route here reads through the signed-in user's own relation,
 * so another account's notification is a 404 rather than something to guard
 * against with a policy.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->paginate(20),
        ]);
    }

    /** Marks one read and follows the link it carries. */
    public function read(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user()->notifications()->whereKey($notification)->firstOrFail();

        $record->markAsRead();

        $url = (string) ($record->data['url'] ?? '');

        // Only ever back into this app. The links are ours, but a redirect built
        // from stored data is the shape of an open redirect, and it costs one
        // comparison to close.
        return redirect()->to(
            str_starts_with($url, url('/')) ? $url : route('home'),
        );
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }
}
