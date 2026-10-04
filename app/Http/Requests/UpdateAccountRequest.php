<?php

namespace App\Http\Requests;

use App\Rules\PhilippineMobileNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
{
    /**
     * The Customer-only guard lives in AccountController, so anything that
     * reaches here is already known to be the account's own owner.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // ignore() lets the owner re-save their own address; a deactivated
            // account still holds its unique email, so the rule sees it as taken.
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->user()->id),
            ],
            'phone' => ['required', 'string', new PhilippineMobileNumber],
        ];
    }
}
