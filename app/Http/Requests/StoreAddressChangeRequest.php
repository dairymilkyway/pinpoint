<?php

namespace App\Http\Requests;

use App\Models\AddressRequest;
use Illuminate\Validation\Rule;

/**
 * A Customer's proposal, validated with the same rules a direct write gets.
 *
 * It extends the store request deliberately: the payload an approver will apply
 * goes through exactly the validation and the same dataset derivation as a write
 * by an Admin, so an approved request cannot put a row in the table that a
 * direct write could not have produced. Only the authorization and the fields
 * unique to a proposal differ.
 */
class StoreAddressChangeRequest extends StoreAddressRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', AddressRequest::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['type' => (string) $this->input('type')]);

        // A deletion proposes no values, and deriving them for one would put a
        // payload on the row that nothing ever reads.
        if ($this->input('type') === AddressRequest::TYPE_DELETE) {
            return;
        }

        parent::prepareForValidation();
    }

    public function rules(): array
    {
        $type = $this->input('type');
        $namesAnAddress = in_array($type, [AddressRequest::TYPE_UPDATE, AddressRequest::TYPE_DELETE], true);

        $rules = [
            'type' => ['required', Rule::in([
                AddressRequest::TYPE_CREATE,
                AddressRequest::TYPE_UPDATE,
                AddressRequest::TYPE_DELETE,
            ])],
            'note' => ['nullable', 'string', 'max:1000'],
            'address_id' => [
                $namesAnAddress ? 'required' : 'nullable',
                'integer',
                Rule::exists('addresses', 'id'),
                // One open request of a kind per address. Two identical rows in
                // the queue would be two approvals of the same change, and the
                // second one would fail on an address that no longer exists.
                function (string $attribute, mixed $value, callable $fail) use ($type) {
                    if ($value === null) {
                        return;
                    }

                    $alreadyOpen = AddressRequest::query()
                        ->pending()
                        ->where('address_id', $value)
                        ->where('type', $type)
                        ->exists();

                    if ($alreadyOpen) {
                        $fail('There is already a pending request of this kind for that address.');
                    }
                },
            ],
        ];

        return $type === AddressRequest::TYPE_DELETE
            ? $rules
            : array_merge($rules, parent::rules());
    }
}
