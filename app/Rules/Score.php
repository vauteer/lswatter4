<?php

namespace App\Rules;

use App\Models\Fixture;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class Score implements ValidationRule
{
    public function __construct(private readonly Fixture $fixture) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        if (! preg_match('/'.$this->fixture->tournament->getScoreRegex().'/', $value)) {
            $fail(__('The :attribute has an invalid format or the wrong number of games.'));

            return;
        }

        $result = $this->fixture->calculate($value, false);

        if ($result !== true) {
            $fail($result);
        }
    }
}
