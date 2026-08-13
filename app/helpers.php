<?php

use App\Models\User;

if (! function_exists('isCli')) {
    function isCli(): bool
    {
        return php_sapi_name() === 'cli' || defined('STDIN');
    }
}

if (! function_exists('currentUser')) {
    function currentUser(): User
    {
        return isCli() ? User::find(1) : auth()->user();
    }
}
