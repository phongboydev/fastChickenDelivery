<?php

namespace App\GraphQL\Mutations;

use App\Models\ClientSettingConditionCompare;
use App\Support\Constant;

class ClientSettingConditionCompareMutator
{
    public function getClientSettingConditionCompare($root, array $args)
    {
        if (!isset($args['client_id'])) {
            return [];
        }

        $query = ClientSettingConditionCompare::where('client_id', $args['client_id']);
        if(!empty($args['is_with_trashed'])) {
            $query = $query->withTrashed();
        }

        return $query->get();
    }

    public function getConditionCompareCustom($root, array $args)
    {
        if (empty($args['client_id'])) {
            return [];
        }

        $defaultVariable = Constant::LIST_S_VARIABLE_WITH_NOT_CONDITION;
        foreach ($defaultVariable as &$value) {
            unset($value['variable_value']);
        }
        $data = ClientSettingConditionCompare::where('client_id', $args['client_id'])->get();
        foreach ($data as $item) {
            if(!array_key_exists($item->name_variable, $defaultVariable)) {
                $defaultVariable[$item->name_variable] = [
                    "readable_name" => Constant::LIST_S_VARIABLE_WITH_CONDITION[$item->key_condition]['readable_name'],
                    "variable_name" => $item->name_variable
                ];
            }
        }

        return array_values($defaultVariable);
    }

    public function restore($root, array $args)
    {
        $model = ClientSettingConditionCompare::withTrashed()->findOrFail($args['id']);
        $model->restore();

        return $model;
    }
}
