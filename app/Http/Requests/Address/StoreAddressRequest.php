<?php
// app/Http/Requests/Address/StoreAddressRequest.php

namespace App\Http\Requests\Address;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label'          => ['sometimes', 'string', 'max:50'],
            'first_name'     => ['required', 'string', 'max:80'],
            'last_name'      => ['required', 'string', 'max:80'],
            'phone'          => ['nullable', 'string', 'max:20'],
            'address_line1'  => ['required', 'string', 'max:255'],
            'address_line2'  => ['nullable', 'string', 'max:255'],
            'city'           => ['required', 'string', 'max:100'],
            'province'       => ['nullable', 'string', 'max:100'],
            'postal_code'    => ['nullable', 'string', 'max:20'],
            'country'        => ['sometimes', 'string', 'max:80'],
            'is_default'     => ['sometimes', 'boolean'],
        ];
    }
}
