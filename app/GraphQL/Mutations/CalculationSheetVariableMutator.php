<?php

namespace App\GraphQL\Mutations;

use App\Models\CalculationSheet;
use App\Models\CalculationSheetClientEmployee;
use App\Events\CalculationSheetReadyEvent;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Support\TemporaryMediaTrait;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use App\Models\CalculationSheetVariable as CalculationSheetVariable;
use Illuminate\Support\Facades\Auth;
use App\User;
use App\Support\Constant;
use App\Exceptions\CustomException;

class CalculationSheetVariableMutator
{

    public function update($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::user();

        $id = (isset($args['id'])) ? $args['id'] : '';
        $calculationSheetId = (isset($args['filter_calculation_sheet_id'])) ? $args['filter_calculation_sheet_id'] : '';
        $clientEmployeeId = (isset($args['filter_client_employee_id'])) ? $args['filter_client_employee_id'] : '';
        $variableName = (isset($args['filter_variable_name'])) ? $args['filter_variable_name'] : '';

        // Neu set id thi ko quan tam cac filter con lai
        if (isset($args['id'])) {
            $calculationSheetId = null;
            $clientEmployeeId = null;
        }

        $calculationSheetVariables = CalculationSheetVariable::select('*')
                                                             ->where(function ($query) use ($id) {
                                                                 if ($id) {
                                                                     $query->where('id', '=', $id);
                                                                 }
                                                             })
                                                             ->where(function ($query) use ($calculationSheetId) {
                                                                 if ($calculationSheetId) {
                                                                     $query->where('calculation_sheet_id', '=', $calculationSheetId);
                                                                 }
                                                             })
                                                             ->where(function ($query) use ($clientEmployeeId) {
                                                                 if ($clientEmployeeId) {
                                                                     $query->where('client_employee_id', '=', $clientEmployeeId);
                                                                 }
                                                             })
                                                             ->where(function ($query) use ($variableName) {
                                                                 if ($variableName) {
                                                                     $query->where('variable_name', '=', $variableName);
                                                                 }
                                                             })
                                                             ->get()->toArray();

        $modelUpdate = [];
        if ($calculationSheetVariables) {
            DB::transaction(function () use ($args, $calculationSheetVariables, &$modelUpdate, $user) {
                foreach ($calculationSheetVariables as $calculationSheetVariable) {
                    // Get model instance
                    $model = CalculationSheetVariable::findOrFail($calculationSheetVariable['id']);
                    $model->readable_name = $args['readable_name'];
                    $model->variable_name = $args['variable_name'];
                    $model->variable_value = (isset($args['variable_value'])) ? $args['variable_value'] : 0;
                    if (isset($args['id'])) {
                        $model->calculation_sheet_id = $args['calculation_sheet_id'];
                        $model->client_employee_id = $args['client_employee_id'];
                    }

                    if ($user->can('update', $model)) {
                        // Save model
                        $model->saveOrFail();
                        array_push($modelUpdate, $model);
                    } else {
                        throw new CustomException(
                            'You are not authorized to access updateCalculationSheetVariable.',
                            'AuthorizedException'
                        );
                    }
                }
            });
        }

        return $modelUpdate;
    }

    public function batchUpdate($rootValue, array $args)
    {
        $user = Auth::user();
        if (!$user->isInternalUser()) {
            throw new HumanErrorException(__("error.permission"));
        } else {
            $role = $user->getRole();
            $clientEmployee = ClientEmployee::select('client_id')->where('id', $args['client_employee_id'])->first();
            if ($role != Constant::ROLE_INTERNAL_DIRECTOR
                && $user->hasDirectPermission('manage_clients')
                && $user->iGlocalEmployee->isAssignedFor($clientEmployee->client_id)
            ) {
                throw new HumanErrorException(__("error.permission"));
            }
        }

        $calculationSheetId = $args['calculation_sheet_id'];
        $calculatedValue = $args['calculated_value'] ?? 0;
        $clientEmployeeId = $args['client_employee_id'];
        $variables = $args['variables'];

        $variablesCollection = collect($variables);
        CalculationSheetVariable::query()->where('calculation_sheet_id', $calculationSheetId)
                                         ->where('client_employee_id', $clientEmployeeId)
                                         ->whereIn('variable_name', $variablesCollection->pluck('variable_name'))
                                         ->delete();
        $variablesData = $variablesCollection->map(function ($variable) use ($calculationSheetId, $clientEmployeeId) {
            return [
                'id' => Str::uuid(), // TODO should not do this here
                'calculation_sheet_id' => $calculationSheetId,
                'client_employee_id' => $clientEmployeeId,
                'variable_name' => $variable['variable_name'],
                'readable_name' => $variable['readable_name'] ?? "",
                'variable_value' => $variable['variable_value'] ?? 0,
            ];
        });
        CalculationSheetVariable::insert($variablesData->toArray());
        CalculationSheetClientEmployee::query()
                                      ->where('calculation_sheet_id', $calculationSheetId)
                                      ->where('client_employee_id', $clientEmployeeId)
                                      ->update([
                                          'calculated_value' => $calculatedValue,
                                      ]);

        return "ok";
    }

    public function delete($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::user();

        $id = (isset($args['id'])) ? $args['id'] : '';
        $calculationSheetId = (isset($args['filter_calculation_sheet_id'])) ? $args['filter_calculation_sheet_id'] : '';
        $clientEmployeeId = (isset($args['filter_client_employee_id'])) ? $args['filter_client_employee_id'] : '';

        // Neu set id thi ko quan tam cac filter con lai
        if (isset($args['id'])) {
            $calculationSheetId = null;
            $clientEmployeeId = null;
        }

        $calculationSheetVariables = CalculationSheetVariable::select('*')
                                                             ->where(function ($query) use ($id) {
                                                                 if ($id) {
                                                                     $query->where('id', '=', $id);
                                                                 }
                                                             })
                                                             ->where(function ($query) use ($calculationSheetId) {
                                                                 if ($calculationSheetId) {
                                                                     $query->where('calculation_sheet_id', '=', $calculationSheetId);
                                                                 }
                                                             })
                                                             ->where(function ($query) use ($clientEmployeeId) {
                                                                 if ($clientEmployeeId) {
                                                                     $query->where('client_employee_id', '=', $clientEmployeeId);
                                                                 }
                                                             })
                                                             ->get()->toArray();

        $modelDelete = [];
        if ($calculationSheetVariables) {
            DB::transaction(function () use ($args, $calculationSheetVariables, &$modelDelete, $user) {
                foreach ($calculationSheetVariables as $calculationSheetVariable) {
                    // Get model instance
                    $model = CalculationSheetVariable::findOrFail($calculationSheetVariable['id']);

                    if ($user->can('delete', $model)) {
                        // Save model
                        $model->delete();
                        array_push($modelDelete, $model);
                    } else {
                        throw new CustomException(
                            'You are not authorized to access deleteCalculationSheetVariable.',
                            'AuthorizedException'
                        );
                    }
                }
            });
        }

        return $modelDelete;
    }

    public function getTotalPagesVariables($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $calculation_sheet_id   =   isset($args['filter_calculation_sheet_id']) ? $args['filter_calculation_sheet_id'] : false;
        $client_employee_id     =   isset($args['filter_client_employee_id']) ? $args['filter_client_employee_id'] : false;
        $client_employee_ids     =   isset($args['filter_client_employee_ids']) ? $args['filter_client_employee_ids'] : false;

        $query = CalculationSheetVariable::authUserAccessible()->query();

        if ($calculation_sheet_id) {
            $query->where('calculation_sheet_id', $calculation_sheet_id);
        }

        if ($client_employee_id) {
            $query->where('client_employee_id', $client_employee_id);
        }

        if ($client_employee_ids) {
            $query->whereIn('client_employee_id', $client_employee_ids);
        }

        $variables = $query->orderBy('variable_name', 'ASC')->paginate(1000, ['*'], 'page', 1);

        return $variables->lastPage();
    }

    public function getAllVariables($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $page                   =   isset($args['page']) ? $args['page'] : 1;
        $calculation_sheet_id   =   isset($args['filter_calculation_sheet_id']) ? $args['filter_calculation_sheet_id'] : false;
        $client_employee_id     =   isset($args['filter_client_employee_id']) ? $args['filter_client_employee_id'] : false;
        $client_employee_ids     =   isset($args['filter_client_employee_ids']) ? $args['filter_client_employee_ids'] : false;

        $query = CalculationSheetVariable::query();

        if ($calculation_sheet_id) {
            $query->where('calculation_sheet_id', $calculation_sheet_id);
        }

        if ($client_employee_id) {
            $query->where('client_employee_id', $client_employee_id);
        }

        if ($client_employee_ids) {
            $query->whereIn('client_employee_id', $client_employee_ids);
        }

        $variables = $query->orderBy('variable_name', 'ASC')->paginate(1000, ['*'], 'page', $page);

        return $variables->count() > 0 ? json_encode($variables->items()) : '';
    }

    public function updateMultiple($rootValue, array $args){
        $agrsValue  = isset($args['input']) ? $args['input'] : false;
        $user = Auth::user();
        if ($agrsValue) {
            DB::transaction(function () use ($args, $agrsValue, &$modelUpdate, $user) {
                foreach ($agrsValue as $calculationSheetVariable) {
                    // Get model instance
                    $model = CalculationSheetVariable::findOrFail($calculationSheetVariable['id']);
                    $model->variable_value = $calculationSheetVariable['variable_value'];
                    if ($user->can('update', $model)) {
                        // Save model
                        logger($calculationSheetVariable['variable_value']);
                        $model->saveOrFail();
                    } else {
                        throw new CustomException(
                            'You are not authorized to access updateMultipleCalculationSheetVariable.',
                            'AuthorizedException'
                        );
                    }
                }
            });
            if(!empty($args['id'])){
                $calculationSheet = CalculationSheet::where('id', $args['id'])->first();
                if (!empty($calculationSheet)) {
                    $calculationSheet->handleUpdate = true;
                    event(new CalculationSheetReadyEvent($calculationSheet));
                }
            }
        }
        return "ok";
    }
}
