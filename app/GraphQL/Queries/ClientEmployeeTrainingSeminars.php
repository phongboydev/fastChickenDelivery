<?php

namespace App\GraphQL\Queries;

use App\Models\TrainingSeminar;
use App\Models\ClientEmployeeTrainingSeminar;
use App\Exceptions\CustomException;
use App\Support\Constant;

class ClientEmployeeTrainingSeminars
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        if(isset($args['training_seminar_id'])) {
            $training_seminar = TrainingSeminar::where(['id' => $args['training_seminar_id'], 'client_id' => auth()->user()->client_id])->exists();
        }
        if(isset($args['client_employee_id'])) {
            $training_seminar = ClientEmployeeTrainingSeminar::where(['client_employee_id' => $args['client_employee_id'], 'client_id' => auth()->user()->client_id ])->exists();
        }

        if (auth()->user()->getRole() == Constant::ROLE_CLIENT_MANAGER && $training_seminar || auth()->user()->hasDirectPermission('manage-training') && $training_seminar) {
            if(isset($args['training_seminar_id'])) {
                return $this->getTrainingSeminarsByID($args);
            }
            if(isset($args['client_employee_id'])) {
                $result = $this->getTrainingSeminarsByEmployeeID($args);
                return $result;
            }         
            
        } else {
            throw new CustomException(
                'You do not have permission to use this feature.',
                'ValidationException'
            );
        }
    }
    /**
     * Get list training seminar of employee
     */
    protected function getTrainingSeminarsByEmployeeID($args){
        $perpage = isset($args['perPage']) ? $args['perPage'] : 10;
        $page = isset($args['page']) ? $args['page'] : '1';
        $orderby = isset($args['orderBy']) ? $args['orderBy'][0]['field'] : 'id';
        $order = isset($args['orderBy']) ? $args['orderBy'][0]['order'] : 'ASC';
        $client_employee_id = isset($args['client_employee_id']) ? $args['client_employee_id'] : '';
        $items = ClientEmployeeTrainingSeminar::where('client_employee_id' , $client_employee_id );        
        $items = $items->orderBy('client_employee_training_seminars.' . $orderby, $order)
            ->paginate($perpage, ['*'], 'page', $page);
            logger(['item', $items]);
        return [
                'data'       => $items,
                'pagination' => [
                    'total'        => $items->total(),
                    'count'        => $items->count(),
                    'per_page'     => $items->perPage(),
                    'current_page' => $items->currentPage(),
                    'total_pages'  => $items->lastPage()
                ],
            ];
    }
    /**
     * Get list tranining seminar by ID
     */
    protected function getTrainingSeminarsByID($args){
        $perpage = isset($args['perPage']) ? $args['perPage'] : 10;
        $page = isset($args['page']) ? $args['page'] : '1';
        $orderby = isset($args['orderBy']) ? $args['orderBy'][0]['field'] : 'id';
        $order = isset($args['orderBy']) ? $args['orderBy'][0]['order'] : 'ASC';
        $state = isset($args['state']) ? $args['state'] : '';
        $items = TrainingSeminar::find($args['training_seminar_id'])
            ->clientEmployee();
        if($state){
            $items = $items->join('training_seminar_attendance', function($join) use ($state) {
                $join->on('client_employee_training_seminars.client_employee_id', '=', 'training_seminar_attendance.client_employee_id');
                $join->on('client_employee_training_seminars.training_seminar_id','=','training_seminar_attendance.training_seminar_id')
                ->where('training_seminar_attendance.state', '=', $state);
            });
        }
            
        $items = $items->with(
                [
                    "trainingSeminarAttendance" => function ($q) use ($args) {
                        $q->where('training_seminar_attendance.training_seminar_id', $args['training_seminar_id']);
                    }
                ]
                );
        if($state){
            $items = $items->groupBy('training_seminar_attendance.client_employee_id');
        }
            
        $items = $items->orderBy('client_employee_training_seminars.' . $orderby, $order)
            ->paginate($perpage, ['*'], 'page', $page);

        return [
            'data'       => $items,
            'pagination' => [
                'total'        => $items->total(),
                'count'        => $items->count(),
                'per_page'     => $items->perPage(),
                'current_page' => $items->currentPage(),
                'total_pages'  => $items->lastPage()
            ],
        ];
    }
}
