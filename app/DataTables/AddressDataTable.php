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

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('is_default', fn (Address $address) => $address->is_default
                ? '<span class="badge text-bg-success">Default</span>'
                : '<span class="badge text-bg-light text-muted">No</span>')
            ->addColumn('actions', fn (Address $address) => view(
                'addresses.partials.actions',
                ['address' => $address],
            )->render())
            ->addColumn('owner', fn (Address $address) => $address->user?->name ?? '-')
            ->rawColumns(['is_default', 'actions'])
            ->filterColumn('owner', function (QueryBuilder $query, string $keyword) {
                $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$keyword}%"));
            })
            ->orderColumn('owner', fn (QueryBuilder $query, string $order) => $query->orderBy(
                User::select('name')->whereColumn('users.id', 'addresses.user_id'),
                $order,
            ))
            ->setRowId('id');
    }

    public function query(Address $model): QueryBuilder
    {
        return $model->newQuery()->with('user');
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('addresses-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->orderBy(1)
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
        return [
            Column::make('owner')->title('Owner'),
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
