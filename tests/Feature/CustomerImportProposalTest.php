<?php

namespace Tests\Feature;

use App\Imports\AddressImport;
use App\Models\Address;
use App\Models\AddressRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\AddressRequestDecided;
use App\Notifications\AddressRequestRaised;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * A Customer cannot write to the directory, so an import they submit is a
 * proposal: rows are validated, the valid ones become ONE pending request, and
 * nothing lands until a reader approves the whole file. These tests pin that,
 * and in particular that widening the import gate did not let the write through.
 */
class CustomerImportProposalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_the_customer_page_carries_the_three_actions_and_no_direct_create(): void
    {
        $customer = $this->userWithRole('Customer');
        Address::factory()->for($customer)->create();

        $html = $this->actingAs($customer)
            ->get(route('addresses.index'))->assertOk()->getContent();

        // Yajra returns the toolbar inside a JSON payload, and Laravel escapes
        // slashes there. Normalise before matching so the assertion turns on the
        // URL itself, not on which encoder wrote it.
        $html = str_replace('\/', '/', $html);

        // The labels are text nodes, so they survive the JSON payload the same
        // way the readers' toolbar labels do.
        $this->assertStringContainsString('New address', $html);
        $this->assertStringContainsString('Import Excel', $html);
        $this->assertStringContainsString('Export to Excel', $html);

        // New address proposes rather than creates.
        $this->assertStringContainsString(
            route('requests.create', ['type' => 'create']),
            $html,
        );

        // And the direct create path is still not offered.
        $this->assertStringNotContainsString(route('addresses.create'), $html);
    }

    public function test_a_customer_import_files_one_request_and_writes_no_address(): void
    {
        $customer = $this->userWithRole('Customer');

        $this->actingAs($customer)
            ->post(route('addresses.import.store'), [
                'file' => $this->workbook([
                    $this->row(['label' => 'Depot']),
                    $this->row(['label' => 'Warehouse']),
                ]),
            ])
            ->assertRedirect();

        // The criterion that catches the bypass hazard: the gate was widened, so
        // the only thing standing between a Customer and a direct write is the
        // proposal branch. If it is gone, this is 2, not 0.
        $this->assertSame(0, Address::query()->count());

        $change = AddressRequest::query()->sole();

        $this->assertSame(AddressRequest::TYPE_IMPORT, $change->type);
        $this->assertSame(AddressRequest::STATUS_PENDING, $change->status);
        $this->assertSame($customer->id, $change->user_id);
        $this->assertNull($change->address_id);
        $this->assertNull($change->before);

        $this->assertCount(2, $change->payload);
        $this->assertSame(
            ['Depot', 'Warehouse'],
            array_column($change->payload, 'label'),
        );

        // The model's own flags: an import is an addition, so it is never the
        // orphaned-edit case the review modal refuses to approve.
        $this->assertTrue($change->isAddition());
        $this->assertFalse($change->isOrphaned());
    }

    public function test_only_the_valid_rows_reach_the_proposal(): void
    {
        $customer = $this->userWithRole('Customer');

        $this->actingAs($customer)
            ->post(route('addresses.import.store'), [
                'file' => $this->workbook([
                    $this->row(['label' => 'Keeps']),
                    $this->row(['label' => '']),
                    $this->row(['label' => 'Also keeps']),
                ]),
            ])
            ->assertRedirect()
            ->assertSessionHas('import_failures', function (array $failures): bool {
                return count($failures) === 1 && str_contains($failures[0], 'Row 3');
            });

        // The failed row is reported the way it always was, and is not smuggled
        // into the proposal to be created blind on approval.
        $payload = AddressRequest::query()->sole()->payload;

        $this->assertSame(['Keeps', 'Also keeps'], array_column($payload, 'label'));
    }

    public function test_submitting_the_proposal_notifies_the_approvers_once_and_records_it(): void
    {
        Notification::fake();

        $customer = $this->userWithRole('Customer');
        $reader = $this->userWithRole('Superadmin');

        $this->actingAs($customer)
            ->post(route('addresses.import.store'), [
                'file' => $this->workbook([$this->row()]),
            ])
            ->assertRedirect();

        // Filing through the import controller must not skip what raising any
        // other request does.
        Notification::assertSentTo($reader, AddressRequestRaised::class);
        Notification::assertNotSentTo($customer, AddressRequestRaised::class);

        $this->assertSame(1, AuditLog::query()->where('event', AuditLog::REQUEST_RAISED)->count());
    }

    public function test_approving_creates_one_address_per_row_owned_by_the_requester(): void
    {
        $customer = $this->userWithRole('Customer');
        $reader = $this->userWithRole('Superadmin');

        $this->actingAs($customer)->post(route('addresses.import.store'), [
            'file' => $this->workbook([
                $this->row(['label' => 'Depot', 'city' => 'Manila']),
                $this->row(['label' => 'Warehouse', 'city' => 'Manila']),
            ]),
        ]);

        $change = AddressRequest::query()->sole();

        $this->actingAs($reader)
            ->post(route('requests.approve', $change))
            ->assertRedirect();

        $this->assertSame(2, Address::query()->count());
        $this->assertSame(
            [$customer->id, $customer->id],
            Address::orderBy('id')->pluck('user_id')->all(),
        );
        $this->assertSame(['Depot', 'Warehouse'], Address::orderBy('id')->pluck('label')->all());

        $this->assertDatabaseHas('address_requests', [
            'id' => $change->id,
            'status' => AddressRequest::STATUS_APPROVED,
            'decided_by' => $reader->id,
        ]);
    }

    public function test_approving_records_one_approval_and_one_create_per_address(): void
    {
        $customer = $this->userWithRole('Customer');
        $reader = $this->userWithRole('Superadmin');

        $this->actingAs($customer)->post(route('addresses.import.store'), [
            'file' => $this->workbook([
                $this->row(['label' => 'Depot']),
                $this->row(['label' => 'Warehouse']),
                $this->row(['label' => 'Annex']),
            ]),
        ]);

        $change = AddressRequest::query()->sole();

        $this->actingAs($reader)->post(route('requests.approve', $change))->assertRedirect();

        // One decision, not one per row: that was the whole reason the file is a
        // single request rather than three.
        $this->assertSame(1, AuditLog::query()->where('event', AuditLog::REQUEST_APPROVED)->count());
        $this->assertSame(3, AuditLog::query()->where('event', AuditLog::ADDRESS_CREATED)->count());

        // The approving reader is the actor on every entry, because the writes go
        // through the same model path a reader's own create uses.
        $this->assertSame(
            0,
            AuditLog::query()
                ->whereIn('event', [AuditLog::REQUEST_APPROVED, AuditLog::ADDRESS_CREATED])
                ->where('actor_id', '!=', $reader->id)
                ->count(),
        );
    }

    public function test_approving_notifies_the_customer_once_not_once_per_row(): void
    {
        Notification::fake();

        $customer = $this->userWithRole('Customer');
        $reader = $this->userWithRole('Superadmin');

        $this->actingAs($customer)->post(route('addresses.import.store'), [
            'file' => $this->workbook([
                $this->row(['label' => 'Depot']),
                $this->row(['label' => 'Warehouse']),
                $this->row(['label' => 'Annex']),
            ]),
        ]);

        $change = AddressRequest::query()->sole();

        Notification::fake();

        $this->actingAs($reader)->post(route('requests.approve', $change))->assertRedirect();

        Notification::assertSentToTimes($customer, AddressRequestDecided::class, 1);
    }

    public function test_rejecting_creates_nothing_and_keeps_the_reason(): void
    {
        $customer = $this->userWithRole('Customer');
        $reader = $this->userWithRole('Superadmin');

        $this->actingAs($customer)->post(route('addresses.import.store'), [
            'file' => $this->workbook([$this->row(['label' => 'Depot'])]),
        ]);

        $change = AddressRequest::query()->sole();

        $this->actingAs($reader)->post(route('requests.reject', $change), [
            'decision_note' => 'Wrong city for this account.',
        ])->assertRedirect();

        $this->assertSame(0, Address::query()->count());
        $this->assertDatabaseHas('address_requests', [
            'id' => $change->id,
            'status' => AddressRequest::STATUS_REJECTED,
            'decision_note' => 'Wrong city for this account.',
        ]);
    }

    public function test_a_pending_proposal_is_not_an_address(): void
    {
        $customer = $this->userWithRole('Customer');

        $this->actingAs($customer)->post(route('addresses.import.store'), [
            'file' => $this->workbook([
                $this->row(['label' => 'Depot']),
                $this->row(['label' => 'Warehouse']),
            ]),
        ]);

        // It is an AddressRequest, not an Address, so every read path - the
        // table, its counts, the map, the dashboard - is untouched by
        // construction. The Customer still sees it in their queue, though.
        $this->assertSame(0, Address::query()->count());

        $html = $this->actingAs($customer)
            ->get(route('requests.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Depot', $html);
    }

    public function test_a_reader_still_imports_directly_without_a_proposal(): void
    {
        $reader = $this->userWithRole('Superadmin');
        $owner = User::factory()->create();

        $this->actingAs($reader)
            ->post(route('addresses.import.store'), [
                'user_id' => $owner->id,
                'file' => $this->workbook([$this->row(['label' => 'Depot'])]),
            ])
            ->assertRedirect();

        // A reader holds addresses.create, so the proposal branch is not taken.
        $this->assertSame(1, Address::query()->count());
        $this->assertSame($owner->id, Address::sole()->user_id);
        $this->assertSame(0, AddressRequest::query()->count());
    }

    public function test_the_mode_follows_the_actor_not_a_request_parameter(): void
    {
        $customer = $this->userWithRole('Customer');
        $someoneElse = User::factory()->create();

        // Every lever a crafted request could pull: a parameter pretending it is
        // already approved, and another account's id. Neither moves the mode.
        $this->actingAs($customer)
            ->post(route('addresses.import.store'), [
                'user_id' => $someoneElse->id,
                'propose' => '0',
                'status' => AddressRequest::STATUS_APPROVED,
                'file' => $this->workbook([$this->row(['label' => 'Depot'])]),
            ])
            ->assertRedirect();

        $this->assertSame(0, Address::query()->count());

        $change = AddressRequest::query()->sole();
        $this->assertSame($customer->id, $change->user_id);
        $this->assertSame(AddressRequest::STATUS_PENDING, $change->status);
    }

    public function test_a_customer_cannot_approve_their_own_proposal(): void
    {
        $customer = $this->userWithRole('Customer');

        $this->actingAs($customer)->post(route('addresses.import.store'), [
            'file' => $this->workbook([$this->row(['label' => 'Depot'])]),
        ]);

        $change = AddressRequest::query()->sole();

        $this->actingAs($customer)
            ->post(route('requests.approve', $change))
            ->assertForbidden();

        $this->assertSame(0, Address::query()->count());
        $this->assertTrue($change->fresh()->isPending());
    }

    public function test_the_import_page_opens_in_proposal_mode_for_a_customer(): void
    {
        $customer = $this->userWithRole('Customer');

        $html = $this->actingAs($customer)
            ->get(route('addresses.import.create'))
            ->assertOk()
            ->getContent();

        // The page is about asking rather than writing, and there is no account
        // to choose because the rows can only be theirs.
        $this->assertStringContainsStringIgnoringCase('approv', $html);
        $this->assertStringNotContainsString('Whose addresses are these?', $html);
    }

    public function test_an_import_that_cannot_be_fully_applied_creates_nothing(): void
    {
        $customer = $this->userWithRole('Customer');
        $reader = $this->userWithRole('Superadmin');

        // A payload the import would never produce - it validates every row - but
        // a payload is data, and the database will not accept a null postal code.
        // The transaction's whole job is to keep the good rows from landing while
        // the bad one fails, which is the state neither party chose.
        $change = AddressRequest::create([
            'user_id' => $customer->id,
            'address_id' => null,
            'type' => AddressRequest::TYPE_IMPORT,
            'payload' => [
                ['label' => 'Good', 'line1' => '1 Road', 'city' => 'Manila', 'state' => 'Metro Manila', 'postal_code' => '1000', 'country' => 'Philippines'],
                ['label' => 'Bad', 'line1' => '2 Road', 'city' => 'Manila', 'state' => 'Metro Manila', 'postal_code' => null, 'country' => 'Philippines'],
            ],
            'before' => null,
        ]);

        $response = $this->actingAs($reader)->post(route('requests.approve', $change));

        // 500, because apply() does not catch a failed create - the same shape
        // the single-address path has always had. Pinned rather than hidden: the
        // payload is validated at upload so this is unreachable in practice, and
        // the point of the assertion is the state, not the status.
        $response->assertStatus(500);

        $this->assertSame(0, Address::query()->count(), 'the good row must not survive the bad one');
        $this->assertTrue($change->fresh()->isPending(), 'a decision that could not be applied is not a decision');
    }

    public function test_the_queue_shows_one_entry_with_its_count_and_the_modal_lists_the_rows(): void
    {
        $customer = $this->userWithRole('Customer');
        $reader = $this->userWithRole('Superadmin');

        $this->actingAs($customer)->post(route('addresses.import.store'), [
            'file' => $this->workbook([
                $this->row(['label' => 'Depot', 'line1' => '1 Zzfirst Road']),
                $this->row(['label' => 'Annex', 'line1' => '2 Zzsecond Road']),
            ]),
        ]);

        $html = $this->actingAs($reader)->get(route('requests.index'))->assertOk()->getContent();

        // ONE entry for the whole file, not one per address in it.
        $this->assertSame(1, substr_count($html, 'data-bs-target="#review-'));

        // Worded with the count, so the reader knows the size of what they are
        // deciding without opening it.
        $this->assertStringContainsString('2 addresses', $html);

        // And the modal carries the proposed rows read-only, so the decision is
        // made with the values on screen rather than from memory of a row.
        $this->assertStringContainsString('1 Zzfirst Road', $html);
        $this->assertStringContainsString('2 Zzsecond Road', $html);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create()->assignRole($role);
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
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function workbook(array $rows): UploadedFile
    {
        $headings = AddressImport::COLUMNS;

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
