<?php

namespace App\Rules;

use App\Support\Constant;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\DataAwareRule;

class LeaveRule implements Rule, DataAwareRule
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
        if ($this->data['type'] === Constant::AUTHORIZED_LEAVE) {
            if (empty($this->data['self_marriage_leave']) && empty($this->data['child_marriage_leave']) && empty($this->data['family_lost']) && empty($this->data['woman_leave']) && empty($this->data['baby_care']) && empty($this->data['changed_leave']) && empty($this->data['covid_leave']) && empty($this->data['other_leave'])) {
                return false;
            }
        } else {
            if (empty($this->data['unpaid_leave']) && empty($this->data['pregnant_leave']) && empty($this->data['self_sick_leave']) && empty($this->data['child_sick']) && empty($this->data['wife_pregnant_leave']) && empty($this->data['prenatal_checkup_leave']) && empty($this->data['sick_leave']) && empty($this->data['other_leave'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('warning.please_enter_at_least_one_type_of_leave_time');
    }
}
