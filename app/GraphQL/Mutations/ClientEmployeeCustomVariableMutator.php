<?php

namespace App\GraphQL\Mutations;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Support\TemporaryMediaTrait;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use App\Models\ClientEmployeeCustomVariable as ClientEmployeeCustomVariable;
use Illuminate\Support\Facades\Auth;
use App\User;
use App\Support\Constant;
use App\Exceptions\CustomException;
use App\Exports\VariableImportTemplate\VariableImportTemplateExport;
use App\Imports\ClientEmployeeCustomVariablesImport;
use App\Models\Client;
use App\Models\ClientEmployee;
use App\Models\ClientCustomVariable;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use App\Exports\ClientEmployeeCustomVariablesExport;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\File;
use App\Events\DataImportCreatedEvent;

class ClientEmployeeCustomVariableMutator

{
    public function update($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::user();

        $id = (isset($args['id'])) ? $args['id'] : '';
        $clientEmployeeId = (isset($args['filter_client_employee_id'])) ? $args['filter_client_employee_id'] : '';
        // Neu set id thi ko quan tam cac filter con lai
        if (isset($args['id'])) {
            $clientId = null;
        }

        $clientEmployeeCustomVariables = ClientEmployeeCustomVariable::select('*')
            ->where(function ($query) use ($id) {
                if ($id) {
                    $query->where('id', '=', $id);
                }
            })
            ->where(function ($query) use ($clientEmployeeId) {
                if ($clientEmployeeId) {
                    $query->where('client_employee_id', '=', $clientEmployeeId);
                }
            })
            ->get()->toArray();

        $modelUpdate = array();
        if ($clientEmployeeCustomVariables) {
            DB::transaction(function () use ($args, $clientEmployeeCustomVariables, &$modelUpdate, $user) {
                foreach ($clientEmployeeCustomVariables as $clientEmployeeCustomVariable) {
                    // Get model instance
                    $model = ClientEmployeeCustomVariable::findOrFail($clientEmployeeCustomVariable['id']);
                    $model->readable_name = $args['readable_name'];
                    $model->variable_name = $args['variable_name'];
                    $model->variable_value = (isset($args['variable_value'])) ? $args['variable_value'] : 0;
                    if (isset($args['id'])) {
                        $model->client_employee_id = $args['client_employee_id'];
                    }

                    if ($user->can('update', $model)) {
                        // Save model
                        $model->saveOrFail();
                        array_push($modelUpdate, $model);
                    } else {
                        throw new CustomException(
                            'You are not authorized to access updateClientEmployeeCustomVariable.',
                            'AuthorizedException'
                        );
                    }
                }
            });
        }

        return $modelUpdate;
    }

    public function delete($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::user();

        $id = (isset($args['id'])) ? $args['id'] : '';
        $clientEmployeeId = (isset($args['filter_client_employee_id'])) ? $args['filter_client_employee_id'] : '';
        // Neu set id thi ko quan tam cac filter con lai
        if (isset($args['id'])) {
            $clientId = null;
        }

        $clientEmployeeCustomVariables = ClientEmployeeCustomVariable::select('*')
            ->where(function ($query) use ($id) {
                if ($id) {
                    $query->where('id', '=', $id);
                }
            })
            ->where(function ($query) use ($clientEmployeeId) {
                if ($clientEmployeeId) {
                    $query->where('client_employee_id', '=', $clientEmployeeId);
                }
            })
            ->get()->toArray();

        $modelDelete = array();
        if ($clientEmployeeCustomVariables) {
            DB::transaction(function () use ($args, $clientEmployeeCustomVariables, &$modelDelete, $user) {
                foreach ($clientEmployeeCustomVariables as $clientEmployeeCustomVariable) {
                    // Get model instance
                    $model = ClientEmployeeCustomVariable::findOrFail($clientEmployeeCustomVariable['id']);

                    if ($user->can('delete', $model)) {
                        // Save model
                        $model->delete();
                        array_push($modelDelete, $model);
                    } else {
                        throw new CustomException(
                            'You are not authorized to access deleteClientEmployeeCustomVariable.',
                            'AuthorizedException'
                        );
                    }
                }
            });
        }

        return $modelDelete;
    }

    public function exportImportTemplate($rootValue, array $args)
    {
        $fileName = $args['filename'];
        $variables = $args['variables'];
        $clientId  = $args['client_id'];
        if (empty($variables)) {
            $variables = [];
        }
        if ($clientId) {
            $client = Client::query()->where('id', $clientId)->authUserAccessible()->firstOrFail();
        }

        $myFile = Excel::raw(
            (new VariableImportTemplateExport($variables, $client)
            ),
            \Maatwebsite\Excel\Excel::XLSX
        );

        $response = [
            'name' =>  $fileName,
            'file' => "data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64," . base64_encode($myFile)
        ];

        return json_encode($response);
    }

    public function import($rootValue, array $args)
    {
        // TODO: lỗi import không đi qua Policy
        $client = Client::query()->whereId($args['client_id'])->authUserAccessible()->firstOrFail();
        $errors = [];
        try {
            $inputFileName = 'dinh_nghia_bien_import_' . time() . '.xlsx';
            $inputFileImport = 'DinhNghiaBienImport/' . $inputFileName;

            Storage::disk('local')->putFileAs(
                'DinhNghiaBienImport',
                new File($args['file']),
                $inputFileName
            );

            Excel::import(new ClientEmployeeCustomVariablesImport($client), $args['file']);

            DataImportCreatedEvent::dispatch([
                'type' => 'IMPORT_DINH_NGHIA_BIEN',
                'client_id' => $args['client_id'],
                'user_id' => Auth::user()->id,
                'file' => $inputFileImport
            ]);

            Storage::disk('local')->delete($inputFileImport);

        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }
        return [
            "errors" => $errors
        ];
    }

    public function export($root, array $args) {
        $clientId = $args['client_id'];
        // $clientId = "30f273db-3ae2-48e5-ad6c-834893db4312";
        $client = Client::authUserAccessible()->find($clientId);

        if (!$client) {
            return json_encode(['error' => 'Client was not found']);
        }

        try {
            $employees = ClientEmployee::leftJoin('client_employee_custom_variables', 'client_employees.id', 'client_employee_custom_variables.client_employee_id')
                                                    ->where('client_employees.client_id', $clientId)
                                                    ->where('client_employees.status','!=', 'nghỉ việc')
                                                    ->with('customVariables')
                                                    ->groupBy('client_employees.id')
                                                    ->orderBy('client_employees.code')
                                                    ->select('client_employees.*')
                                                    ->authUserAccessible()
                                                    ->get();

            $variables = ClientCustomVariable::select('variable_name', 'readable_name')
                                                ->where('client_id', $clientId)
                                                ->where('scope', 'employee')
                                                ->orderBy('sort_order', 'asc')
                                                ->groupBy('variable_name')->pluck('readable_name', 'variable_name')->toArray();

            $extension = '.xlsx';
            $fileName = "Client_Employee_Custom_Variables" . $extension;
            $pathFile = 'ClientEmployeeCustomVariablesExport/' . $fileName;
            Excel::store((new ClientEmployeeCustomVariablesExport($client, $employees, $variables)), $pathFile, 'minio');

            $response = [
                'name' => $fileName,
                'url' => Storage::temporaryUrl($pathFile, Carbon::now()->addMinutes(config('app.media_temporary_time', 5)))
            ];

            return json_encode($response);
        } catch (\Exception $e) {
            logger( 'ClientEmployeeCustomVariableMutator export error ' . $e->getMessage() );
            return json_encode(['error' => $e->getMessage()]);
        }

    }
}
