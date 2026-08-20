<?php

namespace App\Http\Requests;

use App\Concerns\UserValidationRules;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UserUpdateRequest extends FormRequest
{
    use UserValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $rules = $this->userRules($user instanceof User ? $user->id : null);

        if ($user instanceof User && $this->user()->is($user)) {
            $rules['blocked'][] = function (string $attribute, mixed $value, Closure $fail): void {
                if ($value) {
                    $fail(__('You cannot block your own account.'));
                }
            };
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->userMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->userAttributes();
    }
}
