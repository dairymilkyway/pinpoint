<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The single definition of a Philippine mobile number, shared by the validator
 * and the writer so the two cannot drift into disagreement about what a valid
 * number is or how it is stored. Mirrors app/Rbac.php's one-source-of-truth role
 * for permission names.
 */
class PhilippineMobileNumber implements ValidationRule
{
    /**
     * What a mobile number looks like once spaces, dashes, dots and parentheses
     * are gone: an optional +, then country code 63 or trunk lead 0 (either may
     * be absent), then a 9 and nine more digits. A landline fails here because
     * the digit after the lead is 2 to 8, never 9.
     */
    private const PATTERN = '/^(?:\+?63|0)?9\d{9}$/';

    /**
     * Run even when the value is empty. Without this Laravel skips the rule for
     * an empty string, and an unauthenticated register POST could bypass it.
     */
    public bool $implicit = true;

    /**
     * Only meaningful after validate() has passed, which is the only way this is
     * reached in practice. Junk still returns a string rather than throwing,
     * because a register POST is unauthenticated input.
     */
    public static function normalise(string $value): string
    {
        return '+63'.substr(preg_replace('/\D/', '', $value) ?? '', -10);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATTERN, self::strip($value)) !== 1) {
            $fail('Enter a mobile number like 0917 123 4567.');
        }
    }

    private static function strip(string $value): string
    {
        return preg_replace('/[\s\-\.\(\)]/', '', $value) ?? '';
    }
}
