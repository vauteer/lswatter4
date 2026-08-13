<?php

namespace App\Http\Requests;

use App\Concerns\PlayerValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PlayerUpdateRequest extends FormRequest
{
    use PlayerValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->playerRules($this->route('player')->id);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->playerMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->playerAttributes();
    }
}
