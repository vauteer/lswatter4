<?php

namespace App\Http\Requests;

use App\Concerns\TournamentValidationRules;
use App\Models\Tournament;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class TournamentUpdateRequest extends FormRequest
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

    /**
     * Once the tournament has started, its round/game format can no
     * longer change without invalidating results already entered.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $tournament = $this->route('tournament');

            if (! $tournament instanceof Tournament || ! $tournament->started()) {
                return;
            }

            foreach (['rounds', 'games', 'winpoints'] as $field) {
                if ((int) $this->input($field) !== (int) $tournament->{$field}) {
                    $validator->errors()->add($field, __('This can no longer be changed once the tournament has started.'));
                }
            }
        });
    }
}
