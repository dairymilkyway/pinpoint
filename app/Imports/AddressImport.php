<?php

namespace App\Imports;

use App\Geo\PhLocations;
use App\Models\Address;
use App\Models\User;
use Closure;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class AddressImport implements SkipsEmptyRows, SkipsOnFailure, ToModel, WithHeadingRow, WithValidation
{
    use SkipsFailures;

    /**
     * The column names, in the order the template writes them. These are the
     * headings the reader is meant to fill in.
     *
     * The three PSGC codes and the coordinates are not among them, though the
     * rules and the model still read all five. Everything they hold comes from
     * the city - the same way the create form derives it, where region and
     * province are dropdowns that exist only to narrow the city list, and the
     * city itself is picked by name. So the template asks for the city's name,
     * which is the one thing that carries the answer, and resolveCity() reads it
     * as either a name or a code for anybody who has a code to hand.
     *
     * All five are still honoured when a file supplies them under their own
     * headings, because discarding a value someone bothered to include is worse
     * than the column being there.
     */
    public const COLUMNS = [
        'label',
        'line1',
        'line2',
        'city',
        'state',
        'postal_code',
        'country',
    ];

    /**
     * The columns the rules call a string, whether or not they are advertised.
     * See prepareForValidation().
     */
    private const TEXT_COLUMNS = [
        'label', 'line1', 'line2', 'city', 'state', 'postal_code', 'country',
        'region_code', 'province_code', 'city_code',
    ];

    private int $imported = 0;

    /** @var list<string> */
    private array $withoutCoordinates = [];

    public function __construct(private readonly User $owner) {}

    /** What actually landed, since the file also counts the rows that failed. */
    public function imported(): int
    {
        return $this->imported;
    }

    /**
     * The cities that landed without a location, so the result can say which
     * addresses will not appear on the map.
     *
     * A row is not rejected for this. An address in a city the coordinate
     * dataset does not carry is a perfectly good address - it just has nowhere
     * to be drawn - so it is a fact to report, not a failure to file.
     *
     * Keyed by city rather than by row: it is the city that lacks a location,
     * so five rows in Taguig are one thing to say, not five.
     *
     * @return list<string>
     */
    public function withoutCoordinates(): array
    {
        return array_values(array_unique($this->withoutCoordinates));
    }

    /**
     * Before the rules, so a row carrying only a city code is judged on the
     * values that code decides rather than on the blank cells beside it - the
     * order the create form validates in.
     *
     * The casting is not decoration. A cell typed as a number comes back from
     * the reader as an int, and a string rule fails it for a reason nobody
     * filling in a spreadsheet could see: postal codes and city codes are both
     * numbers to whoever typed them.
     *
     * The cast is to string and nothing else. Rounding through an int first
     * looks equivalent and is not: it eats the leading zero that 1040 of the
     * city codes and all nine region codes begin with, so a code pasted in as
     * text - the correct way to enter one - would come out a digit short.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function prepareForValidation(array $row, int $index): array
    {
        foreach (self::TEXT_COLUMNS as $column) {
            if (filled($row[$column] ?? null)) {
                $row[$column] = trim((string) $row[$column]);
            }
        }

        if (filled($row['city_code'] ?? null)) {
            // A cell may hold the code or the city's name. Resolved to a code
            // here, so the merge below and the rule above both see one - and so
            // a row that named a city the dataset knows does not fail on a rule
            // about codes.
            $row['city_code'] = self::resolveCity((string) $row['city_code'])['code'] ?? $row['city_code'];
        } elseif (filled($row['city'] ?? null)) {
            // The city column is read the same way, because it is the one a
            // reader would fill in first. A name the dataset has never heard of
            // is left to the free-text path, which is a state the app already
            // has - but a name several places share is written through to the
            // city code, where the rule that knows how to explain it picks it up.
            $resolved = self::resolveCity((string) $row['city'], strict: false);

            if ($resolved['code'] !== null || $resolved['problem'] !== null) {
                $row['city_code'] = $resolved['code'] ?? $row['city'];
            }
        }

        if (! filled($row['city_code'] ?? null)) {
            return $row;
        }

        return array_merge($row, Address::attributesFromCityCode($row['city_code']));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            // City and country are required only when there is no city code to
            // derive them from. Asking for a value the dataset is about to
            // overwrite is asking for typing that gets thrown away.
            'city' => ['required_without:city_code', 'nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:32'],
            'country' => ['required_without:city_code', 'nullable', 'string', 'max:255'],
            'region_code' => ['nullable', 'string', Rule::in(array_keys(PhLocations::regions()))],
            'province_code' => ['nullable', 'string', Rule::in(array_keys(PhLocations::provinces()))],
            // Not Rule::in, because the cell may hold a name - and because a row
            // whose city could not be placed deserves better than "invalid",
            // which reads as a complaint about a code the author never wrote.
            'city_code' => ['nullable', 'string', $this->cityCodeRule()],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function model(array $row): Address
    {
        $this->imported++;

        $address = new Address([
            'user_id' => $this->owner->id,
            'label' => trim((string) $row['label']),
            'line1' => trim((string) $row['line1']),
            'line2' => $this->optional($row, 'line2'),
            'city' => trim((string) $row['city']),
            'state' => $this->optional($row, 'state'),
            'postal_code' => trim((string) $row['postal_code']),
            'country' => trim((string) $row['country']),
            'region_code' => $this->optional($row, 'region_code'),
            'province_code' => $this->optional($row, 'province_code'),
            'city_code' => $this->optional($row, 'city_code'),
            'latitude' => $this->optional($row, 'latitude'),
            'longitude' => $this->optional($row, 'longitude'),
        ]);

        if (! $address->hasCoordinates()) {
            $this->withoutCoordinates[] = (string) $address->city;
        }

        return $address;
    }

    /** @return Closure(string, mixed, Closure(string): void): void */
    private function cityCodeRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $problem = self::resolveCity((string) $value)['problem'];

            if ($problem !== null) {
                $fail($problem);
            }
        };
    }

    /**
     * What a city cell resolved to: the PSGC code, or why it did not resolve.
     *
     * The cell takes the city's name or its code. The name is the one a person
     * actually has, and the code is the one they would have to go and look up -
     * so the name is what the template asks for and the code is the escape hatch
     * for the names that are shared.
     *
     * The code path has a wrinkle worth knowing: a PSGC city code is always ten
     * digits and 1040 of the 1642 begin with a zero, which a spreadsheet drops
     * the moment it treats the cell as a number. A short numeric cell is
     * therefore left-padded, which is safe precisely because the width is fixed -
     * a shortened code has exactly one reading.
     *
     * $strict decides what an unrecognised name means. The city_code column is
     * explicit - someone wrote a value there to be understood - so a name it
     * cannot place is an error worth reporting. The city column is not: an
     * address in a city the dataset has never heard of is a perfectly good
     * address, and the free-text path already handles it.
     *
     * @return array{code: ?string, problem: ?string}
     */
    private static function resolveCity(string $value, bool $strict = true): array
    {
        $value = trim($value);

        if ($value === '') {
            return ['code' => null, 'problem' => null];
        }

        if (isset(PhLocations::cities()[$value])) {
            return ['code' => $value, 'problem' => null];
        }

        if (ctype_digit($value)) {
            $padded = str_pad($value, 10, '0', STR_PAD_LEFT);

            if (isset(PhLocations::cities()[$padded])) {
                return ['code' => $padded, 'problem' => null];
            }
        }

        // "San Isidro, Nueva Ecija" - the province after the comma is what makes
        // the 114 shared names answerable inside a single cell.
        [$name, $where] = array_pad(explode(',', $value, 2), 2, null);
        $name = trim($name);
        $where = $where === null ? null : trim($where);

        $matches = PhLocations::citiesNamed($name, $where);

        if (count($matches) === 1) {
            return ['code' => (string) array_key_first($matches), 'problem' => null];
        }

        if ($matches === []) {
            return [
                'code' => null,
                'problem' => $strict ? sprintf(
                    'No city called "%s" is in the PSGC dataset. Check the spelling, or write the city code instead.',
                    $name,
                ) : null,
            ];
        }

        // Still several after qualifying: the province named did not narrow it,
        // so naming another one will not help either.
        if ($where !== null) {
            return [
                'code' => null,
                'problem' => sprintf(
                    'There is no single "%s" in %s. Write the city code instead.',
                    $name,
                    $where,
                ),
            ];
        }

        return [
            'code' => null,
            'problem' => sprintf(
                '"%s" is the name of %d cities, in %s. Write the province after a comma - "%s, %s" - or use the city code.',
                $name,
                count($matches),
                self::describePlaces($matches),
                $name,
                (string) PhLocations::stateFor(reset($matches)),
            ),
        ];
    }

    /**
     * The places the candidates sit in, named rather than counted - "Nueva
     * Ecija, Laguna, Bohol, 3 more". A description of the ambiguity is what lets
     * a reader resolve it; a count on its own just says no.
     *
     * @param  array<string, array{code: string, name: string, region: ?string, province: ?string, class: string}>  $matches
     */
    private static function describePlaces(array $matches): string
    {
        $places = [];

        foreach ($matches as $city) {
            $place = PhLocations::stateFor($city);

            if ($place !== null && ! in_array($place, $places, true)) {
                $places[] = $place;
            }
        }

        $shown = array_slice($places, 0, 3);
        $rest = count($places) - count($shown);

        if ($rest > 0) {
            $shown[] = "{$rest} more";
        }

        return $shown === [] ? 'several provinces' : implode(', ', $shown);
    }

    /**
     * An optional cell, so a column left empty stores null rather than an empty
     * string - which is what the form stores for the same omission.
     *
     * @param  array<string, mixed>  $row
     */
    private function optional(array $row, string $column): ?string
    {
        $value = trim((string) ($row[$column] ?? ''));

        return $value === '' ? null : $value;
    }
}
