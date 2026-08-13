<?php

namespace App\Concerns;

use App\Models\Player;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait PlayerValidationRules
{
    /**
     * Get the validation rules used to validate players.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function playerRules(?int $playerId = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                $playerId === null
                    ? Rule::unique(Player::class)
                    : Rule::unique(Player::class)->ignore($playerId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function playerMessages(): array
    {
        return [
            'required' => __(':attribute is required.'),
            'string' => __(':attribute must be a string.'),
            'name.unique' => __(':attribute is already in use.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function playerAttributes(): array
    {
        return [
            'name' => __('Name'),
        ];
    }
}
