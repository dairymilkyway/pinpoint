<?php

namespace App\Http\Controllers;

use App\Exports\AddressTemplateExport;
use App\Imports\AddressImport;
use App\Models\Address;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\Failure;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Writing addresses from a spreadsheet.
 *
 * Another way of creating an address, so it is gated on addresses.create rather
 * than a permission of its own: a separate addresses.import would be another row
 * in the matrix for a capability the app already names.
 */
class AddressImportController extends Controller
{
    public function create(Request $request): View
    {
        Gate::authorize('create', Address::class);

        return view('addresses.import', [
            // Null for anyone who does not read the whole book: their rows can
            // only be theirs, so there is nothing to choose.
            'accounts' => $request->user()->seesEveryAddress()
                ? User::query()->owningAccounts()->orderBy('name')->get()
                : null,
            // Carried from the page the button was clicked on, so a reader
            // importing for the account they just opened does not have to find
            // it again in the list. Resolved rather than passed through as an
            // id: the page locks itself to whatever comes back, and an id
            // naming a reader or nobody at all should lock onto nothing.
            'selectedAccount' => $this->selectedAccount($request),
        ]);
    }

    /**
     * The account the import was opened for, or null when there is none to lock
     * onto - in which case the form keeps its editable list.
     */
    private function selectedAccount(Request $request): ?User
    {
        if (! $request->user()->seesEveryAddress()) {
            return null;
        }

        $id = $request->integer('user');

        return $id === 0 ? null : User::query()->owningAccounts()->find($id);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Address::class);

        $actor = $request->user();

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:2048', 'extensions:xlsx,xls,csv'],
            'user_id' => [
                $actor->seesEveryAddress() ? 'required' : 'nullable',
                'integer',
                Rule::exists('users', 'id'),
            ],
        ]);

        $owner = $this->owner($request, $validated['user_id'] ?? null);

        $import = new AddressImport($owner);

        try {
            Excel::import($import, $validated['file']);
        } catch (Throwable $e) {
            // A file that is not a workbook at all is the reader's mistake, not
            // a server fault, so it gets a sentence rather than a stack trace.
            report($e);

            return back()->with('error', 'That file could not be read. Check it opens in a spreadsheet and try again.');
        }

        return redirect()->route('addresses.index')
            ->with('success', $this->summary($import, $owner, $actor))
            ->with('import_failures', $import->failures()
                ->map(fn (Failure $failure): string => "Row {$failure->row()}: ".implode(' ', $failure->errors()))
                ->all())
            // Rows that landed but have no coordinates. Reported rather than
            // rejected: the address is sound, the dataset simply has nowhere to
            // draw it, and leaving it unsaid is how a saved address goes missing
            // from the map with no explanation.
            ->with('import_unpinned', $import->withoutCoordinates());
    }

    /** The same headings the import reads, so the columns are not guesswork. */
    public function template(): BinaryFileResponse
    {
        Gate::authorize('create', Address::class);

        return Excel::download(new AddressTemplateExport, 'addresses-template.xlsx');
    }

    /**
     * Whose book the rows land on.
     *
     * A reader of the whole book chooses, because the rows are not theirs to
     * hold. Everyone else writes to their own account whatever the form posted,
     * so a crafted request cannot put addresses on an account the actor could
     * not otherwise reach.
     *
     * Resolved through owningAccounts() rather than trusting the id: the rule
     * above only says the account exists, and an address on a reader's account
     * is a state the rest of the app does not expect.
     */
    private function owner(Request $request, ?int $accountId): User
    {
        if (! $request->user()->seesEveryAddress()) {
            return $request->user();
        }

        return User::query()->owningAccounts()->findOrFail($accountId);
    }

    private function summary(AddressImport $import, User $owner, User $actor): string
    {
        $count = $import->imported();
        $target = $owner->is($actor) ? 'your account' : $owner->name;

        if ($count === 0) {
            return "Nothing was imported. No row in that file passed validation for {$target}.";
        }

        return $count.' '.Str::plural('address', $count)." added to {$target}.";
    }
}
