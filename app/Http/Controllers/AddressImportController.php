<?php

namespace App\Http\Controllers;

use App\Exports\AddressTemplateExport;
use App\Imports\AddressImport;
use App\Imports\AddressImportBase;
use App\Imports\AddressImportProposal;
use App\Models\Address;
use App\Models\AddressRequest;
use App\Models\User;
use App\Rbac;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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
 *
 * A Customer holds no create permission, so they cannot write from a file at
 * all. Rather than refuse them the page, their import runs in proposal mode: the
 * rows are validated exactly as they are here, and the valid ones become one
 * pending AddressRequest a reader approves or rejects as a whole. Nothing is
 * written until that approval.
 *
 * THE BYPASS HAZARD, because it is easy to reintroduce: owner() below returns
 * the actor for a non-reader, and AddressImport persists through model(). If the
 * gate is widened without branching to the proposal first, a Customer writes
 * straight into their own book and silently bypasses approval. So the branch is
 * derived from the actor's permissions and never from a request parameter - a
 * ?propose flag would let a Customer post propose=0 and self-approve.
 */
class AddressImportController extends Controller
{
    public function create(Request $request): View
    {
        $actor = $request->user();

        $this->authorizeImport($actor);

        $proposing = $this->proposes($actor);

        return view('addresses.import', [
            // Null for anyone who does not read the whole book: their rows can
            // only be theirs, so there is nothing to choose.
            'accounts' => ! $proposing && $actor->seesEveryAddress()
                ? User::query()->owningAccounts()->orderBy('name')->get()
                : null,
            // Carried from the page the button was clicked on, so a reader
            // importing for the account they just opened does not have to find
            // it again in the list. Resolved rather than passed through as an
            // id: the page locks itself to whatever comes back, and an id
            // naming a reader or nobody at all should lock onto nothing.
            'selectedAccount' => $proposing ? null : $this->selectedAccount($request),
            // A proposal has no account to choose and no rows land, so the page
            // says so rather than promising an import.
            'proposing' => $proposing,
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
        $actor = $request->user();

        $this->authorizeImport($actor);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:2048', 'extensions:xlsx,xls,csv'],
            'user_id' => [
                $actor->seesEveryAddress() ? 'required' : 'nullable',
                'integer',
                Rule::exists('users', 'id'),
            ],
        ]);

        if ($this->proposes($actor)) {
            return $this->propose($actor, $validated['file']);
        }

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
            ->with('import_failures', $this->failureLines($import))
            // Rows that landed but have no coordinates. Reported rather than
            // rejected: the address is sound, the dataset simply has nowhere to
            // draw it, and leaving it unsaid is how a saved address goes missing
            // from the map with no explanation.
            ->with('import_unpinned', $import->withoutCoordinates());
    }

    /**
     * Files the validated rows as one pending request instead of writing them.
     *
     * Nothing lands here - the write happens in AddressRequestController::apply()
     * when a reader approves. Invalid rows are reported to the Customer exactly
     * as they are on a direct import, and only the validated rows are proposed.
     *
     * No import_unpinned is flashed: the "saved, but not on the map" notice is
     * about rows that landed, and none did.
     */
    private function propose(User $actor, UploadedFile $file): RedirectResponse
    {
        $import = new AddressImportProposal;

        try {
            Excel::import($import, $file);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'That file could not be read. Check it opens in a spreadsheet and try again.');
        }

        $rows = $import->rows();
        $failures = $this->failureLines($import);

        if ($rows === []) {
            return back()
                ->with('error', 'Nothing was submitted. No row in that file passed validation.')
                ->with('import_failures', $failures);
        }

        AddressRequest::raise([
            'user_id' => $actor->id,
            'address_id' => null,
            'type' => AddressRequest::TYPE_IMPORT,
            'payload' => $rows,
            'before' => null,
            'note' => null,
        ]);

        return redirect()->route('requests.index')
            ->with('success', $this->proposalSummary($rows))
            ->with('import_failures', $failures);
    }

    /** The same headings the import reads, so the columns are not guesswork. */
    public function template(Request $request): BinaryFileResponse
    {
        $this->authorizeImport($request->user());

        return Excel::download(new AddressTemplateExport, 'addresses-template.xlsx');
    }

    /**
     * Whether the actor may reach the import page at all: a writer by create,
     * a Customer by the request permission their proposal is filed under.
     */
    private function authorizeImport(User $actor): void
    {
        abort_unless(
            $actor->can('create', Address::class) || $actor->can(Rbac::REQUEST_PERMISSION),
            403,
        );
    }

    /**
     * Whether the import proposes rather than writes. Derived from the actor's
     * permissions, never from the request: an actor who cannot create has no
     * write path to reach, so their rows must be held for approval.
     */
    private function proposes(User $actor): bool
    {
        return ! $actor->can('create', Address::class);
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

    /**
     * The failed rows as sentences, keyed by the line the reader sees in the
     * spreadsheet. Shared by both modes so a proposal reports failures the same
     * way a direct import does.
     *
     * @return list<string>
     */
    private function failureLines(AddressImportBase $import): array
    {
        return $import->failures()
            ->map(fn (Failure $failure): string => "Row {$failure->row()}: ".implode(' ', $failure->errors()))
            ->all();
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

    /**
     * The proposal's result line. The count is what was submitted for approval,
     * not what landed, because nothing has landed yet.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function proposalSummary(array $rows): string
    {
        $count = count($rows);

        return $count.' '.Str::plural('address', $count)
            .' submitted for approval. Nothing is added until an administrator approves the request.';
    }
}
