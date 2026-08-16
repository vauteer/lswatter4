<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TournamentRegisterRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'player1_id' => ['nullable', 'integer', 'exists:players,id', 'required_without:new_player1_name'],
            'new_player1_name' => ['nullable', 'string', 'max:255', 'required_without:player1_id'],
            'player2_id' => ['nullable', 'integer', 'exists:players,id', 'different:player1_id'],
            'new_player2_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required_without' => __(':attribute is required.'),
            'string' => __(':attribute must be a string.'),
            'different' => __('Player 2 must be a different player than Player 1.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'player1_id' => __('Player 1'),
            'new_player1_name' => __('Player 1'),
            'player2_id' => __('Player 2'),
            'new_player2_name' => __('Player 2'),
        ];
    }
}
