<?php

namespace App\DataTables;

use App\Models\Address;
use App\Models\User;
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
     * Whether the two columns that only speak to the owner are dropped.
     *
     * A reader of the whole book is looking at somebody else's profile. The
     * default marker there is that owner's own business and moving it is theirs
     * to do, not the reader's. And the pin state is the same fact the Pins panel
     * beside the table already answers, so as a column it is a second copy of
     * it. On a Customer's own book both stay.
     */
    private function hidesMapAndDefault(): bool
    {
        return $this->scopedToOwner() && (bool) $this->request()->user()?->seesEveryAddress();
    }

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        // Which cells hold markup. Collected and set once at the end, because
        // rawColumns() replaces the list rather than adding to it.
        $raw = ['actions'];

        $table = (new EloquentDataTable($query))
            ->addColumn('actions', fn (Address $address) => view(
                'addresses.partials.actions',
                ['address' => $address],
            )->render())
            ->setRowId('id');

        // An addColumn value rides along in the row payload whether or not the
        // column is declared, so hiding one has to skip building it at all
        // rather than just leaving it out of getColumns().
        if (! $this->hidesMapAndDefault()) {
            $table
                ->editColumn('is_default', fn (Address $address) => $address->is_default
                    ? '<span class="badge text-bg-success">Default</span>'
                    : '<span class="badge text-bg-light text-muted">No</span>')
                // The pin carries its state in words as well as in an icon. The icon
                // alone says nothing to a screen reader, and the export strips the
                // markup, so an icon-only cell would leave the spreadsheet empty.
                ->addColumn('map', fn (Address $address) => $address->hasCoordinates()
                    ? '<span class="badge text-bg-success" title="Shown on the map"><i class="bi bi-geo-alt"></i><span class="visually-hidden">Pinned</span></span>'
                    : '<span class="badge text-bg-light text-muted" title="The dataset has no coordinates for this city">No location</span>')
                ->orderColumn('map', fn (QueryBuilder $query, string $order) => $query->orderByRaw(
                    'latitude IS NULL '.($order === 'desc' ? 'desc' : 'asc'),
                ));

            $raw[] = 'is_default';
            $raw[] = 'map';
        }

        // The Owner column is dropped for the same reason, but by the scope
        // rather than the viewer: one owner's page would repeat a single name.
        if (! $this->scopedToOwner()) {
            $table
                ->addColumn('owner', fn (Address $address) => $address->user?->name ?? '-')
                ->filterColumn('owner', function (QueryBuilder $query, string $keyword) {
                    $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$keyword}%"));
                })
                ->orderColumn('owner', fn (QueryBuilder $query, string $order) => $query->orderBy(
                    User::select('name')->whereColumn('users.id', 'addresses.user_id'),
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
                'btn btn-sm btn-primary',
            );

            $buttons[] = $this->linkButton(
                route('addresses.import.create', $this->owner ? ['user' => $this->owner->id] : []),
                '<i class="bi bi-upload"></i> Import Excel',
                'btn btn-sm btn-outline-secondary',
            );
        }

        if ($user?->can('addresses.export')) {
            $buttons[] = Button::make('excel')
                ->className('btn btn-sm btn-outline-success')
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

        // Dropped on the same page, and together, for the reasons
        // hidesMapAndDefault() gives.
        $personal = $this->hidesMapAndDefault()
            ? []
            : [
                Column::computed('map')
                    ->title('Map')
                    ->orderable(true)
                    // Kept out of the global search: the cell holds markup, and the
                    // other columns are what a reader types a place name into.
                    ->searchable(false)
                    ->addClass('text-center'),
                Column::make('is_default')->title('Default'),
            ];

        return [
            ...$owner,
            Column::make('label'),
            Column::make('line1')->title('Address'),
            Column::make('city'),
            Column::make('country'),
            ...$personal,
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
