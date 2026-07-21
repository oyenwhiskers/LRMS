<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReviewRegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $decision = (string) $this->input('decision');

        return in_array($decision, ['approve', 'reject'], true)
            && Gate::allows("registrations.{$decision}");
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'position_id' => [
                'nullable',
                'required_if:decision,approve',
                Rule::exists('positions', 'id')->where('is_active', true),
            ],
            'rejection_reason' => ['nullable', 'required_if:decision,reject', 'string', 'max:1000'],
        ];
    }
}
