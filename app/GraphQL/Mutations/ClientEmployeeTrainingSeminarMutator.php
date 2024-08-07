<?php

namespace App\GraphQL\Mutations;

use Illuminate\Support\Facades\DB;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use App\Exceptions\CustomException;
use App\Models\ClientEmployeeTrainingSeminar;
use App\Models\ClientEmployee;
use App\Models\TrainingSeminar;
use App\Support\Constant;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
class ClientEmployeeTrainingSeminarMutator
{

    public function create($root, array $args)
    {
        DB::beginTransaction();

        try {

            $training_seminar = TrainingSeminar::where(['id' => $args['training_seminar_id'], 'client_id' => auth()->user()->client_id])->exists();

            if (auth()->user()->getRole() == Constant::ROLE_CLIENT_MANAGER && $training_seminar || auth()->user()->hasDirectPermission('manage-training') && $training_seminar) {

                $arrEmployees = Arr::pluck($args['client_employees'], 'client_employee_id');

                $arrEmployeesSeminar = ClientEmployeeTrainingSeminar::where( 'client_id', auth()->user()->client_id ) 
                                        ->where('training_seminar_id', $args['training_seminar_id'])
                                        ->whereIn('client_employee_id', $arrEmployees)
                                        ->groupBy('client_employee_id')
                                        ->pluck('client_employee_id')
                                        ->toArray();

                // Get list employee not exist in training seminar
                $arrEmployeesNotExist = array_diff($arrEmployees, $arrEmployeesSeminar);
                // ClientEmployeeTrainingSeminar
                foreach( $arrEmployeesNotExist as $client_employee_id){
                    ClientEmployeeTrainingSeminar::create(
                        array(
                            'client_id' => auth()->user()->client_id,
                            'client_employee_id' => $client_employee_id,
                            'training_seminar_id' => $args['training_seminar_id'],
                        )
                    );
                }
                
                DB::commit();

                return true; // all good
            } else {
                throw new CustomException(
                    'You do not have permission to use this feature.',
                    'ValidationException'
                );
            }
        } catch (\Throwable $e) {

            DB::rollback();

            echo $e->getMessage();
            // something went wrong
            return false;
        }
    }

    
    public function getClientEmployeesSeminars($rootValue, array $args){       

        $user = Auth::user();        
        $perpage = isset($args['perPage']) ? $args['perPage'] : 10;
        $page = isset($args['page']) ? $args['page'] : '1';
        $code = isset($args['code']) ? $args['code'] : '';
        $client_department_id = isset($args['client_department_id']) ? $args['client_department_id'] : '';
        
        if(!empty($user->client_id)){
            $employees = ClientEmployee::select('*')->where('client_id', $user->client_id);
            if( $code ) {
                $employees = $employees->where('code', $code);
            }

            if( $client_department_id ){
                $employees = $employees->where('client_department_id', $client_department_id);
            }

            $employees = $employees->paginate($perpage, ['*'], 'page', $page);

            return [
                    'data'       => $employees,
                    'pagination' => [
                        'total'        => $employees->total(),
                        'count'        => $employees->count(),
                        'per_page'     => $employees->perPage(),
                        'current_page' => $employees->currentPage(),
                        'total_pages'  => $employees->lastPage()
                    ]
            ];
        }
        return [
            'data'       => [],
            'pagination' => [
                'total'        => 0,
                'count'        => 0,
                'per_page'     => 0,
                'current_page' => 0,
                'total_pages'  => 0
            ]
    ];
        
    }
}
