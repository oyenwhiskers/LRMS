<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'staff_number' => [
                'required',
                'string',
                'max:50',
                Rule::exists('staff', 'staff_number')->where('is_active', true),
            ],
            'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:255', 'unique:users,email'],
            'requested_position_id' => [
                'required',
                'integer',
                Rule::exists('positions', 'id')->where('is_active', true),
            ],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->mixedCase()->numbers()->symbols(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'staff_number.exists' => 'The staff number cannot be used for registration.',
            'requested_position_id.exists' => 'The selected position is unavailable.',
        ];
    }
}
