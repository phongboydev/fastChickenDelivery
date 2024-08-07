<?php

namespace App\Rules;

use App\User;
use Illuminate\Support\Str;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\DataAwareRule;

class UsernameExistsAvailableRule implements Rule, DataAwareRule
{
    protected $data = [];

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Set the data under validation.
     *
     * @param  array  $data
     * @return $this
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $client_id = $this->data['client_id'];
        $username = Str::lower("{$client_id}_{$value}");
        $userQuery = User::where('client_id', $client_id);

        if ($this->data['allow_login'] && !$this->data['overwrite']) {
            return !$userQuery->where('username', $username)->exists();
        }

        $originalUsername = Str::lower($userQuery->where("code", $this->data['code'])->value("username"));

        return $originalUsername === $username || !User::where('client_id', $client_id)->where('username', '!=', $originalUsername)->where('username', $username)->exists();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('importing.already_taken_msg', ['msg' => __('validation.attributes.username')]);
    }
}
