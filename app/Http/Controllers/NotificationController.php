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

    /** Marks one read and lands on its detail page. */
    public function read(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user()->notifications()->whereKey($notification)->firstOrFail();

        $record->markAsRead();

        return redirect()->route('notifications.show', $record->id);
    }

    public function show(Request $request, string $notification): View
    {
        return view('notifications.show', [
            'notification' => $request->user()->notifications()->whereKey($notification)->firstOrFail(),
        ]);
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }
}
