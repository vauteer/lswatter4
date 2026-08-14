<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait TournamentValidationRules
{
    /**
     * Get the validation rules used to validate tournaments.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function tournamentRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'start' => ['required', 'date'],
            'rounds' => ['required', 'integer', 'min:2', 'max:9'],
            'games' => ['required', 'integer', 'min:2', 'max:9'],
            'winpoints' => ['required', 'integer', 'min:11', 'max:21'],
            'private' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function tournamentMessages(): array
    {
        return [
            'required' => __(':attribute is required.'),
            'string' => __(':attribute must be a string.'),
            'date' => __(':attribute must be a valid date.'),
            'integer' => __(':attribute must be a number.'),
            'boolean' => __(':attribute must be true or false.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function tournamentAttributes(): array
    {
        return [
            'name' => __('Name'),
            'start' => __('Start'),
            'rounds' => __('Rounds'),
            'games' => __('Games'),
            'winpoints' => __('Winning points'),
            'private' => __('Private'),
        ];
    }
}
