<?php

namespace App\Rules;

use App\User;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\DataAwareRule;

class EmailAvailableRule implements Rule, DataAwareRule
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
        $userQuery = User::where('client_id', $client_id)->where('code', $this->data['code']);

        if ($this->data['overwrite']) {
            $originalEmail = $userQuery->value('email');
            return !$originalEmail || $originalEmail == $value;
        }

        return !$userQuery->exists();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('importing.already_taken_msg', ['msg' => __('validation.attributes.email')]);
    }
}
