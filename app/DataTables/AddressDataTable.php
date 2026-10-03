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

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        $table = (new EloquentDataTable($query))
            ->editColumn('is_default', fn (Address $address) => $address->is_default
                ? '<span class="badge text-bg-success">Default</span>'
                : '<span class="badge text-bg-light text-muted">No</span>')
            ->addColumn('actions', fn (Address $address) => view(
                'addresses.partials.actions',
                ['address' => $address],
            )->render())
            ->rawColumns(['is_default', 'actions'])
            ->setRowId('id');

        // An addColumn value rides along in the row payload whether or not the
        // column is declared, so scoping has to skip building it at all rather
        // than just leaving it out of getColumns().
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

        return $table;
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

    /** The export button only exists for users holding addresses.export. */
    protected function getButtons(): array
    {
        if (! $this->request()->user()?->can('addresses.export')) {
            return [];
        }

        return [
            Button::make('excel')
                ->className('btn btn-sm btn-outline-success')
                ->text('<i class="bi bi-file-earmark-excel"></i> Export to Excel'),
        ];
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
            Column::make('is_default')->title('Default'),
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
