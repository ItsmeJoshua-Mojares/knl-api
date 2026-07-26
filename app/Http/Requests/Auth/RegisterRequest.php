<?php
// app/Http/Requests/Auth/RegisterRequest.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Form Request Validation
//
// Instead of validating inside the controller with:
//   $request->validate([...])
//
// We create a dedicated class. Benefits:
// - Validation rules live in one place
// - Controller stays clean (just calls $request->validated())
// - Can add complex authorization logic (authorize() method)
// - Easy to reuse across multiple controllers
//
// Laravel automatically runs the validation BEFORE the controller
// method is called. If validation fails, it returns a 422 JSON
// response automatically — you don't have to write that code.
//
// Validation rules (the strings after the field name):
//   required      — field must be present and not empty
//   string        — must be a string
//   email         — must be a valid email format
//   unique:users  — must not already exist in the users table
//   min:8         — minimum 8 characters
//   confirmed     — must have a matching _confirmation field
//                   (password + password_confirmation)
//   nullable      — optional, can be null
//   max:20        — maximum 20 characters
// ─────────────────────────────────────────────────────────────

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Who is allowed to make this request?
     * Return true = anyone. Return false = nobody.
     * Add $this->user() checks for protected routes.
     */
    public function authorize(): bool
    {
        return true; // Registration is open to everyone
    }

    /**
     * Validation rules.
     * All fields here are automatically available via $request->validated().
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name'  => ['required', 'string', 'max:80'],
            'email'      => ['required', 'string', 'email', 'max:191', 'unique:users,email'],
            'password'   => ['required', 'string', 'min:8', 'confirmed'],
            // password_confirmation must also be sent and must match password
            'phone'      => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * Custom error messages — more helpful than the defaults.
     */
    public function messages(): array
    {
        return [
            'email.unique'         => 'An account with this email already exists.',
            'password.confirmed'   => 'Password confirmation does not match.',
            'password.min'         => 'Password must be at least 8 characters.',
        ];
    }
}
