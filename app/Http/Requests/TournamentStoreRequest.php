<?php

namespace App\Http\Requests;

use App\Concerns\TournamentValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TournamentStoreRequest extends FormRequest
{
    use TournamentValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->tournamentRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->tournamentMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->tournamentAttributes();
    }
}
