<?php

namespace App\GraphQL\Mutations;

use App\Events\DataImportCreatedEvent;
use App\Exceptions\CustomException;
use App\Exceptions\DownloadFileErrorException;
use App\Exceptions\HumanErrorException;
use App\Exports\CalculationSheetTemplateAssignmentExport;
use App\Imports\CalculationSheetTemplateAssignmentImport;
use App\Models\CalculationSheetTemplate;
use App\Models\CalculationSheetTemplateAssignment;
use App\Models\ClientWorkflowSetting;
use Illuminate\Http\File;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException as ValidationException;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CalculationSheetTemplateAssignmentMutator
{
    public function calculationSheetTemplateAssignmentCustom($root, array $args)
    {
        $clientId = $args['client_id'] ?? null;
        $templateId = $args['template_id'] ?? null;
        if (empty($clientId) || empty($templateId)) return [];
        $query = CalculationSheetTemplateAssignment::join('client_employees', function ($join) use ($templateId, $clientId, $args) {
            $join->on('client_employees.id', '=', 'calculation_sheet_template_assignments.client_employee_id')
                ->where([
                    'calculation_sheet_template_assignments.template_id' => $templateId,
                    'calculation_sheet_template_assignments.client_id' => $clientId
                ]);
            if (isset($args['employee_filter'])) {
                $employeeFilter = $args['employee_filter'];
                $join->where(function ($query) use ($employeeFilter) {
                    $query->where('full_name', 'LIKE', "%$employeeFilter%")
                        ->orWhere('code', 'LIKE', "%$employeeFilter%");
                });
        }
        });

        return $query
            ->select('sort_by','code', 'full_name', 'calculation_sheet_template_assignments.id')
            ->orderBy('calculation_sheet_template_assignments.sort_by', 'ASC')
            ->orderBy('client_employees.code', 'ASC')
            ->get();
    }

    /**
     * @throws HumanErrorException
     */
    public function updateMultiple($root, array $args)
    {
        $data = $args['input'] ?? [];
        if (empty($data)) return;
        // Check setting
        $user = Auth::user();
        $calExit = CalculationSheetTemplateAssignment::whereIn('id', array_column($data, 'id'))->first();
        if (!$calExit) {
            throw new HumanErrorException('not_exit');
        }
        if (!$user->isInternalUser()) {
            $clientWorkflowSetting = ClientWorkflowSetting::select('*')->where('client_id', $user->client_id)->first();
            if (!$clientWorkflowSetting->enable_create_payroll || $calExit->client_id != $user->client_id) {
                return false;
            }
        }
        // Update multiple sort_by column
        CalculationSheetTemplateAssignment::upsert($data, ['id'], ['sort_by']);

        return CalculationSheetTemplateAssignment::where('template_id', $calExit->template_id)->get();
    }

    public function export($root, array $args)
    {
        $templateId = $args['template_id'] ?? '';
        if (!$templateId) return false;
        $calculationSheetTemplate = CalculationSheetTemplate::find($templateId);
        if (!$calculationSheetTemplate) return false;
        $data = CalculationSheetTemplateAssignment::join('client_employees', 'calculation_sheet_template_assignments.client_employee_id', '=', 'client_employees.id')
            ->where([
                'calculation_sheet_template_assignments.template_id' => $templateId,
                'calculation_sheet_template_assignments.client_id' => $calculationSheetTemplate->client_id
            ])
            ->orderBy('calculation_sheet_template_assignments.sort_by', 'ASC')
            ->orderBy('client_employees.code', 'ASC')
            ->with('clientEmployee')->get();
        $params = [
            'data' => $data,
            'template_name' => $calculationSheetTemplate->name
        ];
        // Export excel
        $fileName = Str::upper("CALCULATION_SHEET_TEMPLATE") . "_" . time() . '.xlsx';
        $pathFile = 'CalculationSheetTemplateAssignment/' . $fileName;
        Excel::store((new CalculationSheetTemplateAssignmentExport($params)), $pathFile, 'minio');

        $response = [
            'name' => $fileName,
            'url' => Storage::temporaryUrl($pathFile, Carbon::now()->addMinutes(config('app.media_temporary_time', 5)))
        ];

        return json_encode($response);
    }

    /**
     * @throws CustomException
     * @throws DownloadFileErrorException
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public function import($root, array $args)
    {
        $auth = Auth::user();
        $templateId = $args['template_id'] ?? '';
        if (empty($templateId)) return false;
        $rules = array(
            'file' => 'required',
        );

        $inputFileType = 'Xlsx';
        $inputFileName = 'calculation_sheet_template_assignment' . time() . '.xlsx';
        $inputFileImport = 'CalculationSheetTemplateAssignmentMutator/' . $inputFileName;

        Storage::disk('local')->putFileAs(
            'CalculationSheetTemplateAssignmentMutator',
            new File($args['file']),
            $inputFileName
        );

        $reader = IOFactory::createReader($inputFileType);
        $reader->setLoadAllSheets();
        $spreadsheet = $reader->load(storage_path('app/' . $inputFileImport));

        $sheetNames = $spreadsheet->getSheetNames();
        $errors = [];
        $calculationSheetTemplate = CalculationSheetTemplate::find($templateId);
        $clientID = $calculationSheetTemplate->client_id;
        $calculationSheetTemplateAssignment = new CalculationSheetTemplateAssignmentImport($templateId, $clientID);
        $sheet1Errors = $calculationSheetTemplateAssignment->validate($spreadsheet->getSheet(0));
        if ($sheet1Errors) $errors[$sheetNames[0]] = $sheet1Errors;

        if ($errors) {
            throw new DownloadFileErrorException($errors, $inputFileImport);
        }

        try {
            Validator::make($args, $rules);

            Excel::import(new CalculationSheetTemplateAssignmentImport($templateId, $clientID), $args['file']);

            DataImportCreatedEvent::dispatch([
                'type' => 'IMPORT_CLIENT_EMPLOYEE',
                'client_id' => $clientID,
                'user_id' => $auth->id,
                'file' => $inputFileImport
            ]);

            Storage::disk('local')->delete($inputFileImport);

            return json_encode(['status' => 200, 'message' => 'Import is successful.'], 200);
        } catch (ValidationException $e) {

            $message = '';

            if ($e->errors()) {
                $errors = iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveArrayIterator($e->errors())), false);

                $message = implode(' <br/> ', $errors);
            }

            Storage::disk('local')->delete($inputFileImport);

            throw new CustomException(
                $message,
                'ValidationException'
            );
        }
    }
}
