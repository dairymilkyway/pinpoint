<?php

namespace App\Http\Requests;

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
