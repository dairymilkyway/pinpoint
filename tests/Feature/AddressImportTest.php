<?php

namespace Tests\Feature;

use App\Geo\PhLocations;
use App\Imports\AddressImport;
use App\Models\Address;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AddressImportTest extends TestCase
{
    use RefreshDatabase;

    /** A city the bundled dataset knows, code and all. */
    private const CITY_CODE = '1380100000';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_reader_imports_a_file_onto_the_account_they_chose(): void
    {
        $reader = $this->userWithRole('Superadmin');
        $owner = User::factory()->create();

        $this->actingAs($reader)
            ->post(route('addresses.import.store'), [
                'user_id' => $owner->id,
                'file' => $this->workbook([$this->row(['label' => 'Depot'])]),
            ])
            ->assertRedirect(route('addresses.index'))
            ->assertSessionHas('success', '1 address added to '.$owner->name.'.');

        $address = Address::sole();

        $this->assertSame($owner->id, $address->user_id);
        $this->assertSame('Depot', $address->label);
    }

    public function test_a_row_that_fails_is_named_and_the_rest_still_land(): void
    {
        $reader = $this->userWithRole('Superadmin');
        $owner = User::factory()->create();

        $this->actingAs($reader)
            ->post(route('addresses.import.store'), [
                'user_id' => $owner->id,
                'file' => $this->workbook([
                    $this->row(['label' => 'Keeps']),
                    $this->row(['label' => '']),
                    $this->row(['label' => 'Also keeps']),
                ]),
            ])
            ->assertRedirect()
            ->assertSessionHas('import_failures', function (array $failures): bool {
                // The line as the reader sees it in the spreadsheet: row 1 is
                // the headings, so the second address is on row 3.
                return count($failures) === 1 && str_contains($failures[0], 'Row 3');
            });

        $this->assertSame(
            ['Keeps', 'Also keeps'],
            Address::orderBy('id')->pluck('label')->all(),
        );
    }

    public function test_the_reader_is_shown_which_rows_did_not_land(): void
    {
        $reader = $this->userWithRole('Superadmin');
        $owner = User::factory()->create();

        // Followed through to the page, because the flash partial is the half
        // the reader actually reads - a Blade error in it would leave the
        // session assertion above passing and the reader with a stack trace.
        $html = $this->actingAs($reader)
            ->followingRedirects()
            ->post(route('addresses.import.store'), [
                'user_id' => $owner->id,
                'file' => $this->workbook([$this->row(['label' => ''])]),
            ])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('1 row could not be imported', $html);
        $this->assertStringContainsString('Row 2', $html);
        // Escaped, because that is what the flash partial renders. Faker names
        // carry apostrophes often enough that comparing against the raw name
        // failed about one run in six on the HTML entity.
        $this->assertStringContainsString(
            'Nothing was imported. No row in that file passed validation for '.e($owner->name).'.',
            $html,
        );
    }

    public function test_the_city_code_decides_what_it_knows(): void
    {
        $reader = $this->userWithRole('Superadmin');
        $owner = User::factory()->create();

        // The sheet claims a city and a state that do not belong to the code.
        $this->actingAs($reader)
            ->post(route('addresses.import.store'), [
                'user_id' => $owner->id,
                'file' => $this->workbook(
                    [$this->row([
                        'city' => 'Zzwrongville',
                        'state' => 'Zzwrongstate',
                        'city_code' => self::CITY_CODE,
                    ])],
                    $this->headingsWithCode(),
                ),
            ])
            ->assertRedirect();

        $city = PhLocations::find(self::CITY_CODE);

        // The same rule the create form holds, so a spreadsheet cannot pair a
        // code with a city the code does not name.
        $this->assertSame($city['name'], Address::sole()->city);
        $this->assertSame($city['region'], Address::sole()->region_code);
        $this->assertSame('Metro Manila', Address::sole()->state);

        // Coordinates as well, which is what makes dropping them from the
        // template safe rather than merely tidier: the dataset supplies what a
        // spreadsheet author would otherwise have had to look up elsewhere.
        $geo = PhLocations::coordinatesFor(self::CITY_CODE);

        $this->assertNotNull($geo, 'the fixture city must have coordinates, or this proves nothing');
        $this->assertEqualsWithDelta($geo['lat'], Address::sole()->latitude, 0.000001);
        $this->assertEqualsWithDelta($geo['lng'], Address::sole()->longitude, 0.000001);
    }

    public function test_a_city_name_stands_in_for_its_code(): void
    {
        $reader = $this->userWithRole('Superadmin');
        $owner = User::factory()->create();

        // No code anywhere: the name is the only thing the sheet says about
        // where this is, which is the whole point of the column going away.
        $this->actingAs($reader)
            ->post(route('addresses.import.store'), [
                'user_id' => $owner->id,
                'file' => $this->workbook([
                    $this->row(['city' => 'Cebu City', 'country' => '', 'postal_code' => '']),
                ]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $address = Address::sole();

        // The official name, not the one that was typed, because the dataset
        // decides what the address says - the same as it does on the form.
        $this->assertSame('City of Cebu', $address->city);
        $this->assertSame('Central Visayas', $address->state);
        $this->assertSame('0700000000', $address->region_code);
        $this->assertSame('6000', $address->postal_code);
        $this->assertNotNull($address->latitude);
    }

    public function test_a_name_several_places_share_has_to_be_qualified(): void
    {
        $reader = $this->userWithRole('Superadmin');
        $owner = User::factory()->create();

        $this->actingAs($reader)
            ->post(route('addresses.import.store'), [
                'user_id' => $owner->id,
                'file' => $this->workbook([$this->row(['city' => 'San Isidro'])]),
            ])
            ->assertRedirect()
            ->assertSessionHas('import_failures', function (array $failures): bool {
                // Nine places are called San Isidro. Picking one silently would
                // put the address in the wrong province with no sign of it, so
                // the row is refused and the reason names the provinces.
                return count($failures) === 1
                    && str_contains($failures[0], 'Row 2')
                    && preg_match('/is the name of \d+ cities, in /', $failures[0]) === 1
                    && str_contains($failures[0], 'Nueva Ecija');
            });

        $this->assertSame(0, Address::count());
    }

    public function test_a_shared_name_lands_once_the_province_is_named(): void
    {
        $reader = $this->userWithRole('Superadmin');
        $owner = User::factory()->create();

        $this->actingAs($reader)
            ->post(route('addresses.import.store'), [
                'user_id' => $owner->id,
                'file' => $this->workbook([
                    $this->row(['city' => 'San Isidro, Nueva Ecija']),
                ]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Nueva Ecija', Address::sole()->state);
        $this->assertSame('0304925000', Address::sole()->city_code);
    }

    public function test_a_city_outside_the_dataset_still_lands_as_free_text(): void
    {
        $reader = $this->userWithRole('Superadmin');
        $owner = User::factory()->create();

        // Not every address is in the Philippines, and an unknown name is not a
        // mistake - so this keeps the free-text path rather than failing.
        $this->actingAs($reader)
            ->post(route('addresses.import.store'), [
                'user_id' => $owner->id,
                'file' => $this->workbook([
                    $this->row([
                        'city' => 'Springfield',
                        'state' => 'Illinois',
                        'postal_code' => '62704',
                        'country' => 'United States',
                    ]),
                ]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $address = Address::sole();

        $this->assertSame('Springfield', $address->city);
        $this->assertSame('United States', $address->country);
        $this->assertNull($address->latitude);
        $this->assertNull($address->city_code);
    }

    public function test_a_code_that_lost_its_leading_zero_still_finds_its_city(): void
    {
        $reader = $this->userWithRole('Superadmin');
        $owner = User::factory()->create();

        // Adams is 0102801000. A spreadsheet reads that cell as a number and
        // hands back 102801000, the zero gone before anyone here sees it - so
        // the width is what makes the code recoverable, not the digits.
        $this->actingAs($reader)
            ->post(route('addresses.import.store'), [
                'user_id' => $owner->id,
                'file' => $this->workbook(
                    [$this->row(['city_code' => 102801000])],
                    $this->headingsWithCode(),
                ),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Adams', Address::sole()->city);
        $this->assertSame('0102801000', Address::sole()->city_code);
    }

    /**
     * General Santos is one of the 152 cities the join could not place, so it
     * has no row at all and the address it produces carries neither a position
     * nor a postal code. That is a real address all the same - the reader typed
     * a city the dataset knows - which is why this reports rather than rejects.
     */
    public function test_a_row_in_a_city_with_no_coordinates_says_so(): void
    {
        $reader = $this->userWithRole('Superadmin');
        $owner = User::factory()->create();

        $this->actingAs($reader)
            ->post(route('addresses.import.store'), [
                'user_id' => $owner->id,
                'file' => $this->workbook([$this->row(['city' => 'General Santos', 'postal_code' => '9500'])]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('import_unpinned', ['City of General Santos']);

        $this->assertSame('City of General Santos', Address::sole()->city);

        // The sheet's own postal code survives. The dataset is silent about a
        // city it cannot place, which is the right way round: it corrects the
        // sheet when it knows better, and does not blank what it does not know.
        $this->assertSame('9500', Address::sole()->postal_code);
        $this->assertNull(Address::sole()->latitude);
    }

    public function test_a_pinned_row_is_not_reported_as_unpinned(): void
    {
        $reader = $this->userWithRole('Superadmin');
        $owner = User::factory()->create();

        $this->actingAs($reader)
            ->post(route('addresses.import.store'), [
                'user_id' => $owner->id,
                'file' => $this->workbook([$this->row(['city' => 'Cebu City'])]),
            ])
            ->assertRedirect()
            ->assertSessionHas('import_unpinned', []);
    }

    public function test_the_reader_is_told_which_rows_will_not_appear_on_the_map(): void
    {
        $reader = $this->userWithRole('Superadmin');
        $owner = User::factory()->create();

        // Followed through to the page, since the flash partial is the half the
        // reader actually reads.
        $html = $this->actingAs($reader)
            ->followingRedirects()
            ->post(route('addresses.import.store'), [
                'user_id' => $owner->id,
                'file' => $this->workbook([$this->row(['city' => 'General Santos'])]),
            ])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Saved, but not on the map', $html);
        $this->assertStringContainsString('City of General Santos', $html);
    }

    public function test_a_numeric_postal_code_is_not_rejected_for_being_a_number(): void
    {
        $reader = $this->userWithRole('Superadmin');
        $owner = User::factory()->create();

        // Excel hands a typed number back as an int, and the column is a string.
        $this->actingAs($reader)
            ->post(route('addresses.import.store'), [
                'user_id' => $owner->id,
                'file' => $this->workbook([$this->row(['postal_code' => 1000])]),
            ])
            ->assertRedirect();

        // Empty rather than absent: the controller flashes the list on every
        // import, and the view treats an empty one as nothing to say.
        $this->assertSame([], session('import_failures', []), 'no row should have been rejected');
        $this->assertSame('1000', Address::sole()->postal_code);
    }

    public function test_a_scoped_account_imports_to_itself_whatever_the_form_says(): void
    {
        // The seeded Customer cannot create at all, so grant it the way the
        // matrix would. Otherwise this asserts the 403 rather than the owner.
        Role::findByName('Customer')->givePermissionTo('addresses.create');

        $customer = $this->userWithRole('Customer');
        $someoneElse = User::factory()->create();

        $this->actingAs($customer)
            ->post(route('addresses.import.store'), [
                'user_id' => $someoneElse->id,
                'file' => $this->workbook([$this->row()]),
            ])
            ->assertRedirect();

        $this->assertSame($customer->id, Address::sole()->user_id);
    }

    public function test_a_reader_cannot_import_onto_another_reader(): void
    {
        $reader = $this->userWithRole('Superadmin');
        $other = $this->userWithRole('Admin');

        // owningAccounts() is the rule, and the managing roles are not on it:
        // an address on a reader's account is a state the app does not expect.
        $this->actingAs($reader)
            ->post(route('addresses.import.store'), [
                'user_id' => $other->id,
                'file' => $this->workbook([$this->row()]),
            ])
            ->assertNotFound();

        $this->assertSame(0, Address::count());
    }

    public function test_the_import_refuses_a_role_holding_neither_create_nor_request(): void
    {
        // A Customer can reach the page now, but only to propose. A role with
        // neither permission is still refused at the door - the gate is not
        // simply open to everyone.
        $nobody = User::factory()->create();
        $owner = User::factory()->create();

        $this->actingAs($nobody)
            ->get(route('addresses.import.create'))
            ->assertForbidden();

        $this->actingAs($nobody)
            ->post(route('addresses.import.store'), [
                'user_id' => $owner->id,
                'file' => $this->workbook([$this->row()]),
            ])
            ->assertForbidden();
    }

    public function test_the_import_form_opens_locked_on_the_account_it_was_reached_from(): void
    {
        $reader = $this->userWithRole('Superadmin');
        $owner = User::factory()->create();

        $html = $this->actingAs($reader)
            ->get(route('addresses.import.create', ['user' => $owner->id]))
            ->assertOk()
            ->getContent();

        // Named in full and carried, but not offered as a choice. A dropdown
        // here would let the rows be reassigned without ever leaving the form,
        // which is the whole point of having arrived from that account's page.
        $this->assertStringContainsString(e($owner->name), $html);
        $this->assertStringContainsString('name="user_id" value="'.$owner->id.'"', $html);
        $this->assertStringNotContainsString('<select name="user_id"', $html);
    }

    public function test_an_unscoped_import_keeps_the_account_dropdown(): void
    {
        $reader = $this->userWithRole('Superadmin');
        $owner = User::factory()->create();

        $html = $this->actingAs($reader)
            ->get(route('addresses.import.create'))
            ->assertOk()
            ->getContent();

        // Reached from the users list rather than from an account, so there is
        // still everything to choose - and it stays chooseable.
        $this->assertStringContainsString('<select name="user_id"', $html);
        $this->assertStringNotContainsString('name="user_id" value=', $html);
        $this->assertStringContainsString(e($owner->name), $html);
    }

    public function test_an_account_that_cannot_hold_addresses_does_not_lock_the_form(): void
    {
        $reader = $this->userWithRole('Superadmin');
        $otherReader = $this->userWithRole('Admin');

        // A reader owns nothing, so an id naming one selects nothing - and
        // nothing is not something to lock the form onto.
        $html = $this->actingAs($reader)
            ->get(route('addresses.import.create', ['user' => $otherReader->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<select name="user_id"', $html);
        $this->assertStringNotContainsString('name="user_id" value=', $html);
    }

    public function test_a_scoped_account_gets_no_account_picker(): void
    {
        Role::findByName('Customer')->givePermissionTo('addresses.create');

        // Nothing to choose, so no control to choose it with.
        $this->actingAs($this->userWithRole('Customer'))
            ->get(route('addresses.import.create'))
            ->assertOk()
            ->assertDontSee('Whose addresses are these?');
    }

    public function test_the_toolbar_carries_the_actions_the_role_may_use(): void
    {
        $owner = User::factory()->create();
        Address::factory()->for($owner)->create();

        // The readers' table: both new actions and the export.
        $reader = $this->userWithRole('Superadmin');
        $html = $this->actingAs($reader)
            ->get(route('addresses.user', $owner))->assertOk()->getContent();

        $this->assertStringContainsString('New address', $html);
        $this->assertStringContainsString('Import Excel', $html);
        $this->assertStringContainsString('Export to Excel', $html);

        // A link in this toolbar needs both halves: the tag and href make it a
        // link, and the action is what navigates. DataTables cancels the click
        // on everything it renders, so an href on its own is inert - which is
        // why the pairing is asserted rather than the markup alone.
        $this->assertStringContainsString('"tag":"a"', $html);
        $this->assertStringContainsString('"action":function', $html);

        Role::findByName('Customer')->givePermissionTo('addresses.create');
        $customer = $this->userWithRole('Customer');

        // Holding create, the account sees the create-side actions and the
        // export alike.
        $own = $this->actingAs($customer)
            ->get(route('addresses.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Import Excel', $own);
        $this->assertStringContainsString('Export to Excel', $own);
    }

    public function test_the_template_downloads_with_the_imports_headings(): void
    {
        $reader = $this->userWithRole('Superadmin');

        $response = $this->actingAs($reader)
            ->get(route('addresses.import.template'))->assertOk();

        $this->assertStringContainsString('spreadsheetml', (string) $response->headers->get('content-type'));

        $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())
            ->getActiveSheet()->toArray();

        // The headings are the import's own list, which is what makes the
        // template and the reader agree on the column names.
        $this->assertSame(AddressImport::COLUMNS, $sheet[0]);

        // The codes and the coordinates are not asked for. The city decides all
        // five, and each of them is either a ten-digit number or a pair of
        // coordinates - things an author would have to go elsewhere to look up,
        // for values the import was going to overwrite anyway.
        foreach (['latitude', 'longitude', 'region_code', 'province_code', 'city_code'] as $derived) {
            $this->assertNotContains($derived, $sheet[0], "{$derived} should not be a template column");
        }

        $this->assertContains('city', $sheet[0], 'the city is how the rest of the location is named');
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    /**
     * The template's headings plus the code column it leaves off - what a reader
     * with a code to hand, or an older file, would be sending.
     *
     * @return array<int, string>
     */
    private function headingsWithCode(): array
    {
        return [...AddressImport::COLUMNS, 'city_code'];
    }

    /**
     * One row of the sheet, in the import's column order.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<int, mixed>
     */
    private function row(array $overrides = []): array
    {
        $row = array_replace([
            'label' => 'Home',
            'line1' => '12 Mabini Street',
            'line2' => '',
            'city' => 'Manila',
            'state' => 'Metro Manila',
            'postal_code' => '1000',
            'country' => 'Philippines',
            'city_code' => '',
        ], $overrides);

        return array_values($row);
    }

    /**
     * A real workbook, built through the same library the import reads with, so
     * the test exercises the reader rather than a fixture that happens to fit.
     *
     * The headings default to the template's own, and a test that exercises a
     * column the template does not advertise passes its own - which is the same
     * thing a reader with an old file or a code to hand would be doing.
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, string>|null  $headings
     */
    private function workbook(array $rows, ?array $headings = null): UploadedFile
    {
        $headings ??= AddressImport::COLUMNS;

        $export = new class($rows, $headings) implements FromArray, WithHeadings
        {
            /**
             * @param  array<int, array<int, mixed>>  $rows
             * @param  array<int, string>  $headings
             */
            public function __construct(
                private readonly array $rows,
                private readonly array $headings,
            ) {}

            public function array(): array
            {
                return $this->rows;
            }

            /** @return array<int, string> */
            public function headings(): array
            {
                return $this->headings;
            }
        };

        return UploadedFile::fake()->createWithContent(
            'addresses.xlsx',
            Excel::raw($export, ExcelWriter::XLSX),
        );
    }
}
