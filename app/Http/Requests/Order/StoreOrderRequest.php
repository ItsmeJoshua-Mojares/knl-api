<?php
// app/Http/Requests/Order/StoreOrderRequest.php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only logged-in users reach here — enforced by auth:api middleware
        // on the route, so we just confirm a user is present.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'first_name'        => ['required', 'string', 'max:80'],
            'last_name'         => ['required', 'string', 'max:80'],
            'phone'             => ['required', 'string', 'regex:/^09\d{9}$/'],
            'address_line1'     => ['required', 'string', 'max:255'],
            'address_line2'     => ['nullable', 'string', 'max:255'],
            'city'              => ['required', 'string', 'max:100'],
            'province'          => ['required', 'string', 'max:100'],
            'postal_code'       => ['required', 'string', 'max:20'],
            'payment_method'    => ['required', 'in:gcash,maya,bank_transfer,cod'],
            'reference_number'  => ['required_if:payment_method,gcash,maya', 'nullable', 'string', 'max:100'],
            'coupon_code'       => ['nullable', 'string', 'max:50'],
            'customer_notes'    => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid Philippine mobile number (09XX XXX XXXX).',
            'reference_number.required_if' => 'Please enter your payment reference number.',
        ];
    }
}
