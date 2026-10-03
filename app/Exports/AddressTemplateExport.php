<?php

namespace App\Exports;

use App\Imports\AddressImport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * The import's headings and nothing else, so the column names are read off the
 * file rather than guessed at from the code.
 */
class AddressTemplateExport implements FromArray, WithHeadings
{
    /** @return array<int, array<int, string>> */
    public function array(): array
    {
        return [];
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return AddressImport::COLUMNS;
    }
}
