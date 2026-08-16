<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class PlayerMergeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'keep_id' => ['required', 'integer', 'exists:players,id'],
            'player_ids' => ['required', 'array', 'min:2'],
            'player_ids.*' => ['integer', 'distinct', 'exists:players,id'],
        ];
    }

    /**
     * The player to keep must be one of the players being merged.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $keepId = $this->input('keep_id');
            $playerIds = $this->input('player_ids');

            if (is_array($playerIds) && ! in_array($keepId, $playerIds)) {
                $validator->errors()->add('keep_id', __('The player to keep must be one of the selected duplicates.'));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'keep_id' => __('Player to keep'),
            'player_ids' => __('Players'),
        ];
    }
}
