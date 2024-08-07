<?php

namespace App\Rules;

use App\Models\ClientEmployee;
use App\User;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\DataAwareRule;

class UserCodeAvailableRule implements Rule, DataAwareRule
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
        $code = $this->data['code'];
        $userQuery = User::where('client_id', $client_id)->where('code', $code);
        $clientEmployeeQuery = ClientEmployee::where('client_id', $client_id)->where('code', $value);

        if ($this->data['overwrite']) {
            $originalCode = $userQuery->value('code');
            return !$originalCode || $originalCode == $code;
        }

        return !$userQuery->exists() && !$clientEmployeeQuery->exists();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('importing.already_taken_msg', ['msg' => __('validation.attributes.code')]);
    }
}
