<?php

namespace App\Observers;

use App\Exceptions\HumanErrorException;
use App\Models\ClientSettingConditionCompare;
use App\Support\Constant;

class ClientSettingConditionComparerObserver
{
    /**
     * Handle the ClientSettingConditionCompare "created" event.
     *
     * @param ClientSettingConditionCompare $clientSettingConditionCompare
     * @return void
     * @throws HumanErrorException
     */
    public function creating(ClientSettingConditionCompare $clientSettingConditionCompare)
    {
        // Validate data
        $this->validate($clientSettingConditionCompare);
        // Check exit
        $clientSettingConditionCompareExit = ClientSettingConditionCompare::where([
            'client_id' => $clientSettingConditionCompare->client_id,
            'key_condition' => $clientSettingConditionCompare->key_condition,
        ])->latest('created_at')->first();
        $subLevel = 1;
        if($clientSettingConditionCompareExit) {
            $subLevel = $clientSettingConditionCompareExit->sub_level + 1;
        }
        if ($subLevel != 1) {
            // Check limit  of S variable type
            $countRecord = ClientSettingConditionCompare::where([
                'client_id' => $clientSettingConditionCompare->client_id,
                'key_condition' => $clientSettingConditionCompare->key_condition,
            ])->get()->count();
            // 3 is the limit of variable when add more sub variable
            if ($countRecord >= 3) {
                throw new HumanErrorException(__("not_create_record_more_when_variable_create_enough"));
            }
            $clientSettingConditionCompare->name_variable = $clientSettingConditionCompare->name_variable . '_' . $subLevel;
        }
        $clientSettingConditionCompare->sub_level = $subLevel;
    }

    /**
     * Handle the ClientSettingConditionCompare "created" event.
     *
     * @param ClientSettingConditionCompare $clientSettingConditionCompare
     * @return void
     */
    public function created(ClientSettingConditionCompare $clientSettingConditionCompare)
    {
    }

    /**
     * Handle the ClientSettingConditionCompare "updated" event.
     *
     * @param ClientSettingConditionCompare $clientSettingConditionCompare
     * @return void
     */
    public function updated(ClientSettingConditionCompare $clientSettingConditionCompare)
    {
        //
    }

    /**
     * Handle the ClientSettingConditionCompare "deleted" event.
     *
     * @param ClientSettingConditionCompare $clientSettingConditionCompare
     * @return void
     */
    public function deleted(ClientSettingConditionCompare $clientSettingConditionCompare)
    {
        //
    }

    /**
     * Handle the ClientSettingConditionCompare "restored" event.
     *
     * @param ClientSettingConditionCompare $clientSettingConditionCompare
     * @return void
     */
    public function restored(ClientSettingConditionCompare $clientSettingConditionCompare)
    {
        //
    }

    /**
     * Handle the ClientSettingConditionCompare "force deleted" event.
     *
     * @param ClientSettingConditionCompare $clientSettingConditionCompare
     * @return void
     */
    public function forceDeleted(ClientSettingConditionCompare $clientSettingConditionCompare)
    {
        //
    }

    public function validate($data)
    {
        if (!in_array($data['key_condition'], Constant::KEY_CONDITION_COMPARE)) {
            throw new HumanErrorException(__("key_condition_is_not_list_condition"));
        }
        $listSVariable = array_column(array_values(Constant::LIST_S_VARIABLE_WITH_CONDITION), "name_variable");
        if(!in_array($data['name_variable'], $listSVariable)) {
            throw new HumanErrorException(__("name_variable_is_not_in list"));
        }
        if (!in_array($data['comparison_operator'], Constant::COMPARISON_OPERATOR)) {
            throw new HumanErrorException(__("operator_is_not_in_the_list"));
        }
        if (!is_numeric($data['value'])) {
            throw new HumanErrorException(__("value_is_not_type"));
        } else {
            // Number hour of day
            if ($data['value'] < 0 || $data['value'] > 23.99) {
                throw new HumanErrorException(__("value_is_not_in_range_allow"));
            }
        }
    }
}
