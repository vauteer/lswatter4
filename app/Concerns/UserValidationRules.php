<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait UserValidationRules
{
    /**
     * Get the validation rules used to validate users.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function userRules(?int $userId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                $userId === null
                    ? Rule::unique(User::class)
                    : Rule::unique(User::class)->ignore($userId),
            ],
            'admin' => ['required', 'boolean'],
            'blocked' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function userMessages(): array
    {
        return [
            'required' => __(':attribute is required.'),
            'string' => __(':attribute must be a string.'),
            'email' => __(':attribute must be a valid email address.'),
            'email.unique' => __(':attribute is already in use.'),
            'boolean' => __(':attribute must be true or false.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function userAttributes(): array
    {
        return [
            'name' => __('Name'),
            'email' => __('Email address'),
            'admin' => __('Admin'),
            'blocked' => __('Blocked'),
        ];
    }
}
