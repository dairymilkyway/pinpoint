<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Row;

/**
 * The proposal mode: the same validation as a write, but a validated row is
 * collected instead of persisted.
 *
 * It must not implement ToModel. Maatwebsite persists whatever model() returns,
 * so a draft sharing that class would write exactly the rows it exists to hold
 * back - the one point where a Customer's import would bypass approval.
 *
 * onRow() is only reached for a row that passed validation, because the reader
 * validates before dispatching the row, so the collected rows are the ones a
 * direct import would have written.
 */
class AddressImportProposal extends AddressImportBase implements OnEachRow
{
    /** @var list<array<string, mixed>> */
    private array $rows = [];

    public function onRow(Row $row): void
    {
        $this->rows[] = $this->attributesFor($row->toArray());
    }

    /**
     * The validated rows, as address attributes ready to be applied on
     * approval. Nothing is written here.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(): array
    {
        return $this->rows;
    }
}
