<?php

namespace App\Http\Requests;

use App\Models\Address;

class UpdateAddressRequest extends StoreAddressRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('address'));
    }

    public function rules(): array
    {
        return parent::rules();
    }
}
