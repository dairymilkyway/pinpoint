<?php

namespace App\Imports;

use App\Models\Address;
use App\Models\User;
use Maatwebsite\Excel\Concerns\ToModel;

/**
 * The write mode: a validated row becomes an Address on the owner's book.
 *
 * The row derivation and its validation live in the shared base; this class is
 * only the persistence step. A Customer importing is not this mode - they
 * cannot create, so their import goes through AddressImportProposal and holds
 * the rows back for approval. See AddressImportController.
 */
class AddressImport extends AddressImportBase implements ToModel
{
    private int $imported = 0;

    public function __construct(private readonly User $owner) {}

    /** What actually landed, since the file also counts the rows that failed. */
    public function imported(): int
    {
        return $this->imported;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function model(array $row): Address
    {
        $this->imported++;

        return new Address([
            'user_id' => $this->owner->id,
            ...$this->attributesFor($row),
        ]);
    }
}
