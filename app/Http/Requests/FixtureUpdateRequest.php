<?php

namespace App\Http\Requests;

use App\Models\Fixture;
use App\Rules\Score;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FixtureUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Fixture $fixture */
        $fixture = $this->route('fixture');

        return [
            'score' => ['nullable', 'string', new Score($fixture)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'score' => __('Result'),
        ];
    }
}
