<?php

namespace App\DataTables;

use App\Models\Address;
use App\Models\User;
use App\Rbac;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Support\Facades\Gate;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class AddressDataTable extends DataTable
{
    /**
     * Only the Excel export is exposed. Leaving print/csv/pdf out of this list
     * makes render() ignore those actions entirely.
     */
    protected array $actions = ['excel'];

    /** Set when the table is showing one owner's addresses rather than the book. */
    private ?User $owner = null;

    /**
     * Narrow the table to a single owner. It is the same builder the directory
     * uses with one more where, so the export, the search and the counts all
     * follow without a second scoping rule.
     */
    public function forUser(User $user): static
    {
        $this->owner = $user;

        return $this;
    }

    private function scopedToOwner(): bool
    {
        return $this->owner !== null;
    }

    /**
     * Whether the default marker is dropped from the label cell.
     *
     * A reader of the whole book is looking at somebody else's profile. The
     * default marker there is that owner's own business and moving it is theirs
     * to do, not the reader's. On a Customer's own book it stays.
     */
    private function hidesDefaultMarker(): bool
    {
        return $this->scopedToOwner() && (bool) $this->request()->user()?->seesEveryAddress();
    }

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        // Which cells hold markup. Collected and set once at the end, because
        // rawColumns() replaces the list rather than adding to it. label joins
        // the list because the Default badge renders inline in that cell.
        $raw = ['actions', 'label'];

        $table = (new EloquentDataTable($query))
            ->addColumn('actions', fn (Address $address) => view(
                'addresses.partials.actions',
                ['address' => $address],
            )->render())
            // The Default badge rides inline with the name rather than in a column
            // of its own. label is user input and this cell is now raw HTML, so it
            // is escaped here - an unescaped label is an XSS hole. The leading
            // space lives inside the badge branch so the export reads "Home
            // Default" rather than "HomeDefault".
            ->editColumn('label', fn (Address $address) => e($address->label)
                .($address->is_default && ! $this->hidesDefaultMarker()
                    ? ' <span class="badge badge-amber ms-2">Default</span>'
                    : ''))
            ->setRowId('id');

        // The Owner column is dropped for the same reason, but by the scope
        // rather than the viewer: one owner's page would repeat a single name.
        //
        // A deactivated owner keeps their rows and their name, so every path the
        // Owner column reaches a user through must see a trashed one. The value
        // and the whereHas both follow the relation, which carries withTrashed;
        // the closure states it anyway so the search does not silently depend on
        // the relation keeping it. orderColumn builds its own User query and has
        // no relation to inherit from, so it must say so itself - without that
        // the trashed owner's name is NULL and their rows sort to the top.
        if (! $this->scopedToOwner()) {
            $table
                ->addColumn('owner', fn (Address $address) => $address->user?->name ?? '-')
                ->filterColumn('owner', function (QueryBuilder $query, string $keyword) {
                    $query->whereHas('user', fn ($q) => $q->withTrashed()->where('name', 'like', "%{$keyword}%"));
                })
                ->orderColumn('owner', fn (QueryBuilder $query, string $order) => $query->orderBy(
                    User::withTrashed()->select('name')->whereColumn('users.id', 'addresses.user_id'),
                    $order,
                ));
        }

        return $table->rawColumns($raw);
    }

    /**
     * Scoped to what the viewer may see. The Excel export is built from this
     * same query, so it inherits the scoping without a second rule.
     */
    public function query(Address $model): QueryBuilder
    {
        return $model->newQuery()
            ->with('user')
            ->when($this->owner, fn (QueryBuilder $query) => $query->where('user_id', $this->owner->id))
            ->visibleTo($this->request()->user());
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('addresses-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            // Label, whichever index it lands on once the Owner column is in or out.
            ->orderBy($this->scopedToOwner() ? 0 : 1)
            ->lengthMenu([10, 25, 50, 100])
            ->dom('Bfrtip')
            // DataTables 2 ignores the dom string for its indicator and injects a
            // four-dot div before the table whenever processing is on. The panel
            // has its own shimmer, so the dots are switched off at the source
            // rather than hidden with CSS.
            ->processing(false)
            ->buttons($this->getButtons());
    }

    /**
     * The table's toolbar. Each button is drawn only for a user who may use it,
     * so a role that can do none of it gets no row at all.
     *
     * A writer gets the write actions; a Customer, who cannot create but may
     * ask, gets the proposal paths instead - a new-address request and the
     * import page in its proposal mode. Both are the same capability for that
     * role seen from the other side.
     */
    protected function getButtons(): array
    {
        $user = $this->request()->user();

        $buttons = [];

        if ($user?->can('addresses.create')) {
            // Carries the account the table is already showing, so a reader who
            // opened someone's addresses adds to that account without being
            // asked whose it is again. Unscoped, the button lands on the picker.
            $buttons[] = $this->linkButton(
                route('addresses.create', $this->owner ? ['user' => $this->owner->id] : []),
                '<i class="bi bi-plus-lg"></i> New address',
                'btn btn-primary',
            );

            $buttons[] = $this->linkButton(
                route('addresses.import.create', $this->owner ? ['user' => $this->owner->id] : []),
                '<i class="bi bi-upload"></i> Import Excel',
                'btn btn-outline-secondary',
            );
        } elseif ($user?->can(Rbac::REQUEST_PERMISSION)) {
            // A Customer writes nothing directly, so both actions lead to the
            // proposal paths rather than to addresses.create or a writing
            // import. Route names are spelled out because these are not the
            // create routes and must not be confused with them.
            $buttons[] = $this->linkButton(
                route('requests.create', ['type' => 'create']),
                '<i class="bi bi-plus-lg"></i> New address',
                'btn btn-primary',
            );

            $buttons[] = $this->linkButton(
                route('addresses.import.create'),
                '<i class="bi bi-upload"></i> Import Excel',
                'btn btn-outline-secondary',
            );
        }

        if ($user?->can('addresses.export')) {
            $buttons[] = Button::make('excel')
                ->className('btn btn-outline-success')
                ->text('<i class="bi bi-file-earmark-excel"></i> Export to Excel');
        }

        return $buttons;
    }

    /**
     * A toolbar button that goes somewhere rather than running a DataTables
     * action.
     *
     * The href alone is not enough. DataTables calls preventDefault() on every
     * click it handles, so an anchor rendered in this row looks right and is
     * inert; the action is what performs the navigation. Both are set so the
     * link is still a link to copy, and still opens in a new tab.
     */
    private function linkButton(string $url, string $text, string $class): Button
    {
        return Button::raw()
            ->tag('a')
            ->attr(['href' => $url])
            ->className($class)
            ->text($text)
            ->action('window.location.href = '.json_encode($url, JSON_UNESCAPED_SLASHES).';');
    }

    /** Gate the export action itself, not just the button that triggers it. */
    public function excel()
    {
        Gate::authorize('export', Address::class);

        return parent::excel();
    }

    protected function getColumns(): array
    {
        // Scoped to one owner, an Owner column would repeat the same name on
        // every row, so it is dropped rather than filled with a constant.
        $owner = $this->scopedToOwner()
            ? []
            : [Column::make('owner')->title('Owner')];

        return [
            ...$owner,
            Column::make('label'),
            Column::make('line1')->title('Address'),
            Column::make('city'),
            Column::make('country'),
            Column::computed('actions')
                ->title('Actions')
                ->orderable(false)
                ->searchable(false)
                ->exportable(false)
                ->printable(false)
                ->addClass('text-end text-nowrap'),
        ];
    }
}
