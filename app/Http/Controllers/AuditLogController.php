<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The record of what happened to the directory and who did it.
 *
 * Access is gated by the audit.view permission at the route, because there is no
 * model to write a policy about: every signed-in holder of the permission reads
 * the same list.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $event = $request->string('event')->toString();

        return view('audit.index', [
            'logs' => AuditLog::query()
                ->with('actor')
                ->when(
                    array_key_exists($event, AuditLog::EVENT_LABELS),
                    fn ($query) => $query->where('event', $event),
                )
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'event' => $event,
            'events' => AuditLog::EVENT_LABELS,
        ]);
    }
}
