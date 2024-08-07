<?php

namespace App\Rules;

use App\Models\ClientEmployee;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\DataAwareRule;
use App\Models\ClientEmployeeSalaryHistory;
use Carbon\Carbon;

class SalaryHistoryRule implements Rule, DataAwareRule
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
        $clientEmployee = ClientEmployee::where('client_id', $this->data['client_id'])
            ->where('code', $this->data['code'])
            ->first();

        if (!empty($clientEmployee)) {
            return !ClientEmployeeSalaryHistory::where('client_employee_id', $clientEmployee->id)->whereDate('start_date', $value)->exists();
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
        return __('date_is_exit');
    }
}
