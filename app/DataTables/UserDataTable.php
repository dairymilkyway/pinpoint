<?php

namespace App\DataTables;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

/**
 * The front door of the addresses section for a directory reader: every account
 * that holds addresses, with a link into the addresses each one holds.
 *
 * The Superadmin and the Admin are not owners - they manage the book - so they
 * are not listed. Owning nothing, their rows here would be a zero and a link to
 * an empty page.
 */
class UserDataTable extends DataTable
{
    /**
     * No export action at all. The parent registers one by default, and it is
     * reachable through the same ?action=excel query string even with no button
     * on the page - which would hand out an ungated dump of every user.
     */
    protected array $actions = [];

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('addresses', fn (User $user) => $user->addresses_count)
            // A deactivated owner is listed like any other, so the row has to say
            // so. 'name' is user input and this cell is now raw HTML, so it is
            // escaped here - an unescaped name would be an XSS hole.
            ->editColumn('name', fn (User $user) => e($user->name)
                .($user->trashed() ? ' <span class="badge badge-soft ms-2">Deactivated</span>' : ''))
            ->addColumn('actions', fn (User $user) => view(
                'addresses.partials.user-actions',
                ['user' => $user],
            )->render())
            ->rawColumns(['name', 'actions'])
            // Dim the closed row through the existing .is-deactivated rule.
            ->setRowClass(fn (User $user) => $user->trashed() ? 'is-deactivated' : '')
            // The count is a withCount alias, so the column has to be ordered
            // by that alias rather than by a real table column.
            ->orderColumn('addresses', fn (QueryBuilder $query, string $order) => $query->orderBy(
                'addresses_count',
                $order,
            ))
            ->setRowId('id');
    }

    public function query(User $model): QueryBuilder
    {
        return $model->newQuery()
            // A deactivated owner stays listed: their addresses remain in the book.
            ->withTrashed()
            ->owningAccounts()
            ->withCount('addresses');
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('users-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->orderBy(0)
            ->lengthMenu([10, 25, 50, 100])
            // No 'B' in the dom string: there is no export button here.
            ->dom('frtip')
            ->processing(false);
    }

    protected function getColumns(): array
    {
        return [
            Column::make('name'),
            Column::make('email'),
            Column::computed('addresses')
                ->title('Addresses')
                ->searchable(false)
                ->addClass('text-end'),
            Column::computed('actions')
                ->title('Actions')
                ->orderable(false)
                ->searchable(false)
                ->printable(false)
                ->addClass('text-end text-nowrap'),
        ];
    }
}
