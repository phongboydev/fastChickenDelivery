<?php

namespace App\GraphQL\Mutations;

use App\Models\ClientWorkflowSetting;
use App\Models\WorktimeRegisterCategory;
use App\Support\Constant;
use Illuminate\Support\Facades\Auth;
use App\Models\ClientEmployee;

class ClientEmployeeLeaveRequestMutator
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        // TODO implement the resolver
    }

    public function getListData($root, array $args)
    {
        $arrLeaveCategory = [];
        $leaveCategories = Constant::LEAVE_CATEGORIES;
        if (!empty($leaveCategories)) {
            $user = Auth::user();
            if(isset($args['client_employee_id'])){
                $clientEmployee = ClientEmployee::where('id',  $args['client_employee_id'])->first();
                $clientSetting = ClientWorkflowSetting::where('client_id', $clientEmployee->client_id)->first();
            } else {
                $clientEmployee = ClientEmployee::where('user_id',  $user->id)->first();
                $clientSetting = ClientWorkflowSetting::where('client_id', $user->client_id)->first();
            }
            foreach ($leaveCategories as $key => $item) {
                if($key == 'authorized_leave'){
                    $opt = [];
                    foreach ($item as $value) {
                        $arrItem = explode('.', $value);
                        if(end($arrItem) == 'woman_leave' && empty($clientSetting->authorized_leave_woman_leave) ){
                            continue;
                        }
                        if(end($arrItem) == 'baby_care' && ($clientEmployee && $clientEmployee->sex != 'female') ){
                            continue;
                        }
                        if(end($arrItem) == 'other_leave' && !$clientSetting->enable_paid_leave_other ){
                            continue;
                        }
                        array_push($opt, (object) [
                            "label" => __($value),
                            "value" => $key.".".end($arrItem)
                        ]);
                    }
                    $arrLeaveCategory[0] = [
                        "label" => __('model.worktime_register.leave_request.type.authorized_leave'),
                        "options" => $opt
                    ];
                }
                if($key == 'unauthorized_leave'){
                    $opt = [];
                    foreach ($item as $value) {
                        $arrItem = explode('.', $value);
                        if(end($arrItem) == 'other_leave' && !$clientSetting->enable_unpaid_leave_other ){
                            continue;
                        }
                        array_push($opt, (object) [
                            "label" => __($value),
                            "value" => $key.".".end($arrItem)
                        ]);
                    }
                    $arrLeaveCategory[1] = [
                        "label" => __('model.worktime_register.leave_request.type.unauthorized_leave'),
                        "options" => $opt
                    ];
                }
            }
        }
        $wtCategory = WorktimeRegisterCategory::where('client_id', auth()->user()->client_id)
            ->where('type', 'leave_request')
            ->orderBy('category_name', 'ASC')
            ->get();
        if ($wtCategory->isNotEmpty()) {
            foreach ($wtCategory as $category) {
                if ($category->sub_type == 'authorized_leave') {
                    array_push($arrLeaveCategory[0]['options'], (object) [
                        "label" => $category->category_name,
                        "value" => 'authorized_leave.' . $category->id
                    ]);
                }
                if ($category->sub_type == 'unauthorized_leave') {
                    array_push($arrLeaveCategory[1]['options'], (object) [
                        "label" => $category->category_name,
                        "value" => 'unauthorized_leave.' . $category->id
                    ]);
                }
            }
        }
        return json_encode( $arrLeaveCategory, 200);
    }

    public function getTypeRegister($root, array $args)
    {
        $arrTypeRegister = [];
        $typeRegister = Constant::TYPE_REGISTER;
        if (!empty($typeRegister)) {
            $user = Auth::user();
            $clientSetting = ClientWorkflowSetting::where('client_id', $user->client_id)->first();
            foreach ($typeRegister as $key => $item) {
                if( $key== 'by_the_hour' && !empty($clientSetting->enable_turn_off_leave_hours_mode) ){
                    continue;
                }
                $arrTypeRegister[] = [
                    "label" => __($item['label']),
                    "value" => $item['value']
                ];
            }
        }

        return json_encode( $arrTypeRegister);
    }

}
