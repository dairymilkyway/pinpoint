<?php

namespace Tests\Feature;

use App\Models\User;
use App\Rules\PhilippineMobileNumber;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * The phone number is business logic rather than a text column: one rule decides
 * what is acceptable, and the same class writes the stored form, so the two
 * cannot drift apart into a database holding four spellings of one number.
 */
class PhoneNumberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_every_accepted_form_stores_as_one_canonical_number(): void
    {
        // The forms a person actually writes, including the spaced one. All five
        // are the same number and must land as the same value, or the column
        // becomes a record of how each person typed it.
        foreach ([
            '09171234567',
            '+639171234567',
            '639171234567',
            '9171234567',
            '0917 123 4567',
            '+63 917 123 4567',
            '0917-123-4567',
            '(0917) 123 4567',
        ] as $written) {
            $this->assertSame(
                '+639171234567',
                PhilippineMobileNumber::normalise($written),
                "{$written} did not normalise to the canonical form",
            );
        }
    }

    public function test_a_landline_is_refused(): void
    {
        // The distinguishing property is the 9 after the country or trunk lead.
        // A Metro Manila landline is a real Philippine number and still wrong
        // here, which is the case a loose digits-check would wave through.
        foreach (['0212345678', '+63212345678', '028123456'] as $landline) {
            $this->assertFalse(
                $this->passes($landline),
                "{$landline} was accepted as a mobile number",
            );
        }
    }

    public function test_numbers_of_the_wrong_length_are_refused(): void
    {
        // Too short by one digit, and too long by one.
        foreach (['0917123456', '091712345678'] as $wrongLength) {
            $this->assertFalse(
                $this->passes($wrongLength),
                "{$wrongLength} was accepted",
            );
        }
    }

    public function test_input_that_is_not_a_number_is_refused_without_erroring(): void
    {
        // normalise() is called on write, so it must survive junk rather than
        // throwing - a register POST is unauthenticated input.
        foreach (['', 'not a number', 'abcdefghijk', '+63', 'phone'] as $junk) {
            $this->assertFalse($this->passes($junk), "{$junk} was accepted");
        }

        // And normalise() must not throw on the same junk. It is only ever
        // reached after validation has passed, so the return value here is not
        // specified - only that a bad POST cannot take the request down.
        $this->assertIsString(PhilippineMobileNumber::normalise('not a number'));
    }

    public function test_registration_stores_the_canonical_form_not_what_was_typed(): void
    {
        $this->post(route('register'), [
            'name' => 'Nena Cruz',
            'email' => 'nena@example.test',
            'phone' => '0917 123 4567',
            'password' => 'register-secret-1234',
            'password_confirmation' => 'register-secret-1234',
        ])->assertRedirect(route('home'));

        // The discriminator for the normalise() call in create(): drop it and the
        // spaced form is stored verbatim, and this fails.
        $this->assertSame(
            '+639171234567',
            User::where('email', 'nena@example.test')->firstOrFail()->phone,
        );
    }

    public function test_registration_refuses_an_account_without_a_mobile_number(): void
    {
        foreach ([null, '', '0212345678'] as $phone) {
            $this->post(route('register'), [
                'name' => 'Nena Cruz',
                'email' => 'nena@example.test',
                'phone' => $phone,
                'password' => 'register-secret-1234',
                'password_confirmation' => 'register-secret-1234',
            ])->assertSessionHasErrors('phone');

            $this->assertDatabaseMissing('users', ['email' => 'nena@example.test']);
        }
    }

    /** Run one value through the rule the way the validators do. */
    private function passes(?string $value): bool
    {
        return Validator::make(
            ['phone' => $value],
            ['phone' => [new PhilippineMobileNumber]],
        )->passes();
    }
}
