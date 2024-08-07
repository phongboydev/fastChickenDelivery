<?php

namespace App\GraphQL\Mutations;

use App\Exceptions\CustomException;
use App\Exceptions\HumanErrorException;
use App\Exports\CalculationSheetSalaryExportCsv;
use App\Imports\CalculationSheetSalaryImport;
use App\Imports\PayrollAccountantImport;
use App\Models\CalculationSheet as CalculationSheet;
use App\Models\CalculationSheetClientEmployee;
use App\Models\CalculationSheetExportTemplate;
use App\Models\CalculationSheetTemplate;
use App\Models\Client;
use App\Models\ClientEmployeeSalary;
use App\Models\DebitNote;
use App\Models\PayrollAccountantExportTemplate;
use App\Support\Constant;
use App\Support\MediaHelper;
use Carbon\Carbon;
use Defuse\Crypto\File;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use PhpOffice\PhpWord\TemplateProcessor;
use App\Exceptions\PdfApiFailedException;
use Illuminate\Support\Facades\Http;
use App\Models\CalculationSheetVariable;

class CalculationSheetMutator
{

    use DispatchesJobs;

    public function staticCalculationSheetByStatus(
        $rootValue,
        array $args,
        GraphQLContext $context,
        ResolveInfo $resolveInfo
    ) {
        $user = Auth::user();

        $filterMonth = isset($args['filter_month']) ? $args['filter_month'] : false;
        $filterYear = isset($args['filter_year']) ? $args['filter_year'] : false;
        $filterClient = isset($args['filter_client']) ? $args['filter_client'] : '';

        $query = CalculationSheet::selectRaw('
            COUNT(*) AS total,
            COUNT(IF(status=\'' . Constant::NEW_STATUS . '\',1, NULL)) AS ' . Constant::NEW_STATUS . ',
            COUNT(IF(status=\'' . Constant::PROCESSED_STATUS . '\',1, NULL)) AS ' . Constant::PROCESSED_STATUS . ',
            COUNT(IF(status=\'' . Constant::CALC_SHEET_STATUS_DIRECTOR_REVIEW . '\',1, NULL)) AS ' . Constant::CALC_SHEET_STATUS_DIRECTOR_REVIEW . ',
            COUNT(IF(status=\'' . Constant::CALC_SHEET_STATUS_DIRECTOR_APPROCED . '\',1, NULL)) AS ' . Constant::CALC_SHEET_STATUS_DIRECTOR_APPROCED . ',
            COUNT(IF(status=\'' . Constant::CALC_SHEET_STATUS_CLIENT_REVIEW . '\',1, NULL)) AS ' . Constant::CALC_SHEET_STATUS_CLIENT_REVIEW . ',
            COUNT(IF(status=\'' . Constant::CALC_SHEET_STATUS_CLIENT_APPROVED . '\',1, NULL)) AS ' . Constant::CALC_SHEET_STATUS_CLIENT_APPROVED . ',
            COUNT(IF(status=\'' . Constant::CALC_SHEET_STATUS_PAID . '\',1, NULL)) AS ' . Constant::CALC_SHEET_STATUS_PAID . ',
            COUNT(IF(status=\'' . Constant::CALC_SHEET_STATUS_CLIENT_REJECTED . '\',1, NULL)) AS ' . Constant::CALC_SHEET_STATUS_CLIENT_REJECTED . '
            ')
            ->where('deleted_at', null)
            ->authUserAccessible();

        if ($filterYear) {
            $query->whereYear('created_at', $filterYear);
        }

        if ($filterMonth) {
            $query->whereMonth('created_at', $filterMonth);
        }

        if (!empty($filterClient)) {
            $query->where('client_id', $filterClient);
        }

        return $query->get();
    }

    public function clientsNotExitCalculationSheet(array $args)
    {
        $user = Auth::user();

        $filterMonth = isset($args['filter_month']) ? $args['filter_month'] : '';
        $filterYear = isset($args['filter_year']) ? $args['filter_year'] : '';
        $orderby = isset($args['orderby']) ? $args['orderby'] : 'id';
        $order = isset($args['order']) ? $args['order'] : 'ASC';
        $perpage = isset($args['perpage']) ? $args['perpage'] : 10;
        $page = isset($args['page']) ? $args['page'] : '1';
        $clientLists = Client::select('*')
            ->with('calculationSheet')
            ->whereDoesntHave(
                'calculationSheet',
                function (Builder $query) use ($filterMonth, $filterYear) {
                    if ($filterMonth && $filterYear) {
                        $query->whereMonth('calculation_sheets.created_at', '=', $filterMonth)
                            ->whereYear('calculation_sheets.created_at', '=', $filterYear)
                            ->where('deleted_at', null);
                    }
                }
            )
            ->where('deleted_at', null)
            ->authUserAccessible()
            ->orderBy($orderby, $order)
            ->paginate($perpage, ['*'], 'page', $page);

        return [
            'data' => $clientLists,
            'pagination' => [
                'total' => $clientLists->total(),
                'count' => $clientLists->count(),
                'per_page' => $clientLists->perPage(),
                'current_page' => $clientLists->currentPage(),
                'total_pages' => $clientLists->lastPage(),
            ],
        ];
    }

    public function staticCalculationSheetByClient(
        $rootValue,
        array $args,
        GraphQLContext $context,
        ResolveInfo $resolveInfo
    ) {
        $user = Auth::user();

        $clientLists = [];
        $clients = [];

        if ($user->isInternalUser()) {
            $role = $user->getRole();
            switch ($role) {
                case Constant::ROLE_INTERNAL_LEADER:
                case Constant::ROLE_INTERNAL_STAFF:
                    $orderby = isset($args['orderby']) ? $args['orderby'] : 'id';
                    $order = isset($args['order']) ? $args['order'] : 'ASC';
                    $perpage = isset($args['perpage']) ? $args['perpage'] : 10;
                    $page = isset($args['page']) ? $args['page'] : '1';

                    $clientLists = Client::select('*')
                        ->authUserAccessible()
                        ->where('deleted_at', null)
                        ->orderBy($orderby, $order)
                        ->paginate($perpage, ['*'], 'page', $page);

                    if ($clientLists && !empty($clientLists->toArray()['data'])) {
                        foreach ($clientLists->toArray()['data'] as $client) {
                            $args['filter_client'] = $client['id'];
                            $client['static'] = $this->staticCalculationSheetByStatus(
                                $rootValue,
                                $args,
                                $context,
                                $resolveInfo
                            );
                            array_push($clients, $client);
                        }
                    }
                    break;

                case Constant::ROLE_INTERNAL_DIRECTOR:
                    $filterMonth = isset($args['filter_month']) ? $args['filter_month'] : Carbon::now()->month;
                    $filterYear = isset($args['filter_year']) ? $args['filter_year'] : Carbon::now()->year;
                    // Count clients
                    $countClients = DB::table('clients')->where('deleted_at', null)->count();
                    $query = '
                        SELECT
                            COUNT(IF(CS.client_id IS NULL,NULL,1)) AS count
                        FROM
                        (
                            SELECT created_at
                            FROM
                            (
                                SELECT MAKEDATE(' . $filterYear . ',1) +
                                        INTERVAL (' . $filterMonth . '-1) MONTH +
                                        INTERVAL daynum DAY created_at
                                FROM
                                (
                                    SELECT t*10+u daynum FROM
                                        (SELECT 0 t UNION SELECT 1 UNION SELECT 2 UNION SELECT 3) A,
                                    (SELECT 0 u UNION SELECT 1 UNION SELECT 2 UNION SELECT 3
                                    UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7
                                    UNION SELECT 8 UNION SELECT 9) B ORDER BY daynum
                                ) CL
                            ) CL WHERE MONTH(created_at) = ' . $filterMonth . '
                        ) CLX LEFT OUTER JOIN (
                                                SELECT DATE_FORMAT(calculation_sheets.created_at, "%Y-%m-%d") AS created_at, calculation_sheets.client_id
                                                FROM calculation_sheets
                                                WHERE calculation_sheets.deleted_at IS NULL AND calculation_sheets.client_id IS NOT NULL AND
                                                  calculation_sheets.client_id IN
                                                        (SELECT id
                                                         FROM clients
                                                         WHERE id IS NOT NULL AND deleted_at IS NULL
                                                         )
                                                GROUP BY calculation_sheets.created_at, calculation_sheets.client_id
                        ) CS ON CLX.created_at = DATE_FORMAT(CS.created_at, "%Y-%m-%d")
                        GROUP BY CLX.created_at
                        ORDER BY CLX.created_at
                    ';
                    $calculationSheet = DB::select($query);
                    $countCalculationSheet = [];
                    if ($calculationSheet) {
                        $totalByDate = 0;
                        foreach ($calculationSheet as $value) {
                            $totalByDate += $value->count;
                            $countCalculationSheet[] = $countClients - $totalByDate;
                        }
                    }

                    $client['id'] = '';
                    $client['code'] = '';
                    $client['company_name'] = '';
                    $client['address'] = '';
                    $client['created_at'] = '';
                    $client['updated_at'] = '';
                    $client['static'] = json_encode($countCalculationSheet);
                    array_push($clients, $client);
                    break;
            }
        }

        return [
            'data' => $clients,
            'pagination' => [
                'total' => $clientLists ? $clientLists->total() : 1,
                'count' => $clientLists ? $clientLists->count() : 1,
                'per_page' => $clientLists ? $clientLists->perPage() : 1,
                'current_page' => $clientLists ? $clientLists->currentPage() : 1,
                'total_pages' => $clientLists ? $clientLists->lastPage() : 1,
            ],
        ];
    }

    /**
     * @throws HumanErrorException
     */
    public function salaryExport($root, array $args)
    {
        $calculationSheetId = $args['id'];
        $variables = $args['variables'];
        $forceExport = isset($args['forceExport']) ? $args['forceExport'] : false;
        $employeeGroupIds = isset($args['employeeGroupIds']) ? $args['employeeGroupIds'] : [];

        $calculationSheet = CalculationSheet::select('*')
            ->with('templateExport')
            ->with('client')
            ->where('id', $calculationSheetId)
            ->authUserAccessible(["advanced_permissions" => ["advanced-manage-payroll-list-export"]])
            ->first();

        if (!$calculationSheet) {
            throw new HumanErrorException(__("error.not_found", ["name" => __("model.clients.payroll")]));
        }

        // check if calculation sheet is paid then only return link file download
        if($calculationSheet->status == Constant::CALC_SHEET_STATUS_PAID || $calculationSheet->status == Constant::CALC_SHEET_STATUS_CLIENT_APPROVED){

            $url = '';

            if ($calculationSheet->mediaTemp) {
                $url = $calculationSheet->mediaTemp[0]->url;
            }
            // check link file exist
            if($url) {
                $extension = $url ? pathinfo($url)['extension'] : 'xls';
                $fileName = $calculationSheet->name . '.' . $extension;    
                $response = [
                    'error' => false,
                    'name' => $fileName,
                    'file' => $url,
                ];
                return json_encode($response);    
            } else {
                return $this->createFileExportSalary($calculationSheet, $forceExport, $variables, $employeeGroupIds);  
            }
            
        } else {            
            return $this->createFileExportSalary($calculationSheet, $forceExport, $variables, $employeeGroupIds);            
        }        
    }
    
    // Create file export salary
    private function createFileExportSalary($calculationSheet, $forceExport = false, array $variables = [], array $employeeGroupIds = []){

        if (!$calculationSheet->templateExport) {
            throw new HumanErrorException(__("error.not_found", ["name" => __("model.calculation_sheet_template")]));
        }

        $calculationSheetExportTemplate = CalculationSheetExportTemplate::where(
            'id',
            $calculationSheet->templateExport['id']
        )
            ->first();

        $templateExport = $calculationSheetExportTemplate->relativePath;

        $extension = $templateExport ? pathinfo($templateExport)['extension'] : 'xls';

        // $fileName = $calculationSheet->id.'/'.$calculationSheet->client->code.'_SALARY_REPORT_'.$calculationSheet->month.'_'.$calculationSheet->year.'.'.$extension;
        $fileName = $calculationSheet->name . '.' . $extension;

        $pathFile = 'CalculationSheetExport/' . $fileName;

        $errors = false;

        try {
            if ($templateExport) {
                if (!Storage::missing($templateExport)) {
                    $excelPath = $calculationSheet->excelPath;

                    if (!$excelPath || $forceExport || !$calculationSheet->mediaTemp) {

                        Storage::disk('local')->put(
                            $templateExport,
                            Storage::get($templateExport)
                        );

                        Excel::import(new CalculationSheetSalaryImport(
                            $calculationSheet->id,
                            $variables,
                            $templateExport,
                            $pathFile,
                            $employeeGroupIds
                        ), storage_path('app/' . $templateExport));

                        Storage::disk('local')->delete($templateExport);

                        // delete db media excel old
                        if ($calculationSheet->mediaTemp) {
                            foreach ($calculationSheet->mediaTemp as $item) {
                                $item->delete();
                            }
                        }

                        $calculationSheet->addMediaFromDisk($pathFile, 'minio')
                            ->toMediaCollection('CalculationSheet', 'minio');

                        $calculationSheet->refresh();
                    } elseif ($excelPath) { // Set file name as Calculation sheet name

                        $url = '';

                        if ($calculationSheet->mediaTemp) {
                            $url = $calculationSheet->mediaTemp[0]->url;
                        }

                        $response = [
                            'error' => false,
                            'name' => $fileName,
                            'file' => $url,
                        ];
                        return json_encode($response);
                    }
                } else {
                    throw new CustomException(
                        'File template bị mất',
                        'ValidationException'
                    );
                }
            } else {
                throw new CustomException(
                    'Chưa chọn template',
                    'ValidationException'
                );
            }
        } catch (CustomException $e) {
            $errors = [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        } catch (ValidationException $e) {
            throw new HumanErrorException(__("error.not_found", ["name" => __("model.calculation_sheet_template")]));
        }

        if ($errors) {
            return json_encode($errors);
        } else {
            $response = [
                'error' => false,
                'name' => $fileName,
                'file' => $calculationSheet->excelPath,
            ];

            return json_encode($response);
        }
    }
    public function salaryExportCSV($root, array $args)
    {
        $calculationSheetId = $args['id'];
        $variables = !empty($args['variables']) ? $args['variables'] : [];

        $calculationSheet = CalculationSheet::select('*')
            ->with('templateExport')
            ->with('client')
            ->where('id', $calculationSheetId)
            ->authUserAccessible(["advanced_permissions" => ["advanced-manage-payroll-list-export"]])
            ->first();

        if (!$calculationSheet) {
            throw new HumanErrorException(__("error.not_found", ["name" => __("model.clients.payroll")]));
        }

        $fileName = $calculationSheet->id . '/' . $calculationSheet->client->code . '_SALARY_' . $calculationSheet->month . '_' . $calculationSheet->year . '.csv';

        $pathFile = 'CalculationSheetExport/' . $fileName;

        $errors = false;

        try {
            Excel::store((new CalculationSheetSalaryExportCsv(
                $calculationSheetId,
                $variables
            )), $pathFile, 'minio', \Maatwebsite\Excel\Excel::CSV);

            $calculationSheet = CalculationSheet::where('id', $calculationSheetId)->first();

            $calculationSheet
                ->addMediaFromDisk($pathFile, 'minio')
                ->toMediaCollection('CalculationSheet', 'minio');
        } catch (CustomException $e) {
            $errors = [
                'error' => true,
                'message' => $e->getMessage(),
            ];

            logger('@error export payroll CSV', $errors);
        }

        $this->salaryExport($root, $args);

        if ($errors) {
            return json_encode($errors);
        } else {
            $response = [
                'error' => false,
                'name' => $fileName,
                'file' => env('MINIO_URL') . '/' . env('MINIO_BUCKET') . '/' . $pathFile,
            ];

            return json_encode($response);
        }
    }

    public function salaryFromCSV($root, array $args)
    {
        $calculationSheetId = $args['id'];

        $calculationSheet = CalculationSheet::select('*')->where('id', $calculationSheetId)
            ->authUserAccessible(["advanced_permissions" => ["advanced-manage-payroll-list-export"]])->first();

        if (!$calculationSheet) {
            throw new HumanErrorException(__("error.not_found", ["name" => __("model.clients.payroll")]));
        }

        // $fileName = $calculationSheet->id . '/' . $calculationSheet->client->code . '_SALARY_' . $calculationSheet->month . '_' . $calculationSheet->year . '.csv';

        // $pathFile = 'CalculationSheetExport/' . $fileName;

        $pathFile = $calculationSheet->csvPath;

        if (!Storage::missing($pathFile)) {
            return $pathFile;
        }

        return 'fail';
    }

    public function debitNoteExport($root, array $args)
    {
        $user = Auth::user();

        $debitNote = DebitNote::where('id', $args['id'])->authUserAccessible()->first();
        if (!$debitNote) {
            throw new HumanErrorException(__("error.permission"));
        }

        $calculated_value = CalculationSheetClientEmployee::selectRaw('SUM(calculated_value) AS total')
            ->where('calculation_sheet_id', $args['calculation_sheet_id'])
            ->first();

        $client = Client::where('id', $user->client_id)->first();

        $address = $client->address ? $client->address : '......................';

        $templateProcessor = new TemplateProcessor(base_path('reports/template_debit_note.docx'));

        $total = !empty($calculated_value) ? $calculated_value['total'] : 0;

        $f = new \NumberFormatter("en", \NumberFormatter::SPELLOUT);
        $total_format = $f->format($total);
        $createdAt = Carbon::parse($debitNote->created_at);

        $templateProcessor->setValue('total', number_format($total, 2));
        $templateProcessor->setValue('total_format', $total_format);
        $templateProcessor->setValue('address', $address);
        $templateProcessor->setValue('presenter_name', $client->presenter_name);
        $templateProcessor->setValue('batch_no', $debitNote->batch_no);
        $templateProcessor->setValue('created_at', $createdAt->format('Y/m/d'));
        $templateProcessor->setValue('not_later_than_days', 10);

        $storagePath = Storage::disk('public')->getDriver()->getAdapter()->getPathPrefix();

        $fileName = $client->code . "_template_debit_note.docx";
        $path = $storagePath . '/' . $fileName;

        $templateProcessor->saveAs($path);

        $response = [
            'name' => $client->code . '_debit_note.docx',
            'file' => "data:application/docx;base64," . base64_encode(file_get_contents($path)),
        ];

        unlink($path);

        return json_encode($response);
    }

    public function accountantReportExport($root, array $args)
    {
        $calculationSheetId = $args['id'];

        $calculationSheet = CalculationSheet::select('*')
            ->where('id', $calculationSheetId)->with('client')
            ->authUserAccessible(["advanced_permissions" => ["advanced-manage-payroll-list-export"]])
            ->first();
        if (!$calculationSheet) {
            throw new HumanErrorException(__("error.not_found", ["name" => __("model.clients.payroll")]));
        }

        $template = $calculationSheet->payroll_accountant_export_template_id;

        if ($template) {
            $fileName = $calculationSheet->client->code . '_ACC_REPORT' . '.xlsx';

            $pathFile = 'PayrollAccountantExport/' . $calculationSheet->id . '/' . $fileName;

            if (Storage::missing($pathFile)) {
                $variables = $calculationSheet->payroll_accountant_export_values;

                $payrollAccountantExportTemplate = PayrollAccountantExportTemplate::where('id', $template)
                    ->first();

                if (!empty($payrollAccountantExportTemplate)) {
                    $templateExport = $payrollAccountantExportTemplate->getFirstMedia(PayrollAccountantExportTemplate::MEDIA_COLLECTION);

                    $tempPath = "accountantReportExport" . time() . ".xlsx";
                    $localDisk = Storage::disk("local");

                    if ($templateExport) {
                        $localDisk->put(
                            $tempPath,
                            Storage::disk('minio')->get($templateExport->getPath())
                        );

                        Excel::import(
                            new PayrollAccountantImport(
                                $calculationSheetId,
                                $variables,
                                [],
                                $tempPath,
                                $pathFile
                            ),
                            $localDisk->path($tempPath)
                        );

                        Storage::disk('local')->delete($tempPath);
                    }
                }
            }

            $response = [
                'status' => 1,
                'name' => $calculationSheetId . '.xlsx',
                'file' => MediaHelper::getPublicTemporaryUrl($pathFile),
            ];
        } else {
            $response = [
                'status' => 0,
            ];
        }

        return $response;
    }

    public function clientSalaryStatisticDepartments($root, array $args): ?string
    {
        $response = [
            'filter_month' => [],
            'filter_year' => [],
        ];

        if (!empty($args['filter_by_month'])) {
            $results = ClientEmployeeSalary::selectRaw(
                "
                department,
                SUM(case when DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT('{$args['filter_by_month']}', '%Y-%m') then salary else 0 end) AS total"
            )->groupBy('department')
                ->where('client_id', $args['id'])->orderBy('department', 'ASC')->get();

            if (!empty($results)) {
                $collection = collect($results);

                $total = $collection->sum('total');

                foreach ($collection as $c) {
                    $c->percent = $total ? round(($c->total * 100) / $total, 1) : 0;
                }

                $response['filter_month'] = $collection->toArray();
            }
        }

        if (!empty($args['filter_by_year'])) {
            $year = Carbon::parse($args['filter_by_year'])->format('Y');

            $year_results = ClientEmployeeSalary::selectRaw(
                "department"
            )->groupBy('department')
                ->whereYear('created_at', $year)
                ->where('client_id', $args['id'])->orderBy('department', 'ASC')->get();

            if (!empty($year_results)) {
                $collection = collect($year_results);

                $departments = [];

                foreach ($collection as $c) {
                    $departments[$c->department] = ClientEmployeeSalary::selectRaw(
                        "
                        MONTH(created_at) month,
                        SUM(salary) AS total"
                    )->groupBy('month')
                        ->where('department', $c->department)
                        ->whereYear('created_at', $year)
                        ->where('client_id', $args['id'])->get();
                }

                $filter_year = [];

                if ($departments) {
                    foreach ($departments as $d => $vd) {
                        $filter_year[$d] = [];

                        foreach ($vd as $v) {
                            foreach (range(1, 12) as $i => $month) {
                                if (($v['month']) == $month) {
                                    $filter_year[$d][$i] = $v['total'];
                                } else {
                                    if (!isset($filter_year[$d][$i])) {
                                        $filter_year[$d][$i] = 0;
                                    }
                                }
                            }
                        }
                    }
                }

                $response['filter_year'] = $filter_year;
                $response['departments'] = $departments;
            }
        }

        return json_encode($response);
    }

    public function getDisplayColumns($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $id = $args['calculation_sheet_id'];

        $calculationSheet = CalculationSheet::select('display_columns')->where('id', $id)->first();

        return !empty($calculationSheet) ? $calculationSheet->display_columns : '';
    }

    public function groupCalculationSheetByMonthYear(
        $root,
        array $args,
        GraphQLContext $context,
        ResolveInfo $resolveInfo
    ) {
        $builder = CalculationSheet::query();

        $builder = $resolveInfo
            ->argumentSet
            ->enhanceBuilder(
                $builder,
                ["authUserAccessible"]
            );

        logger($builder->toSql());

        return $builder->select('month', 'year', DB::raw('count(id) as calculation_sheets_count'))
            ->groupBy(
                'month',
                'year'
            )
            ->orderBy('year', 'DESC')
            ->orderBy('month', 'DESC')
            ->get();
    }

    public function getAccountantColumns($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $id = $args['calculation_sheet_id'];

        $calculationSheet = CalculationSheet::select('payslip_accountant_columns_setting')->where('id', $id)->first();

        return !empty($calculationSheet) ? $calculationSheet->payslip_accountant_columns_setting : '';
    }

    public function getApproves($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
    }

    /**
     * @throws HumanErrorException
     */
    public function nodeCreate($root, array $args): ?string
    {
        $client_id = $args['client_id'];
        $user = Auth::user();

        if (!$user->isInternalUser()) {
            if ($user->client_id != $client_id) {
                throw new HumanErrorException(__("error.permission"));
            }

            $normalPermissions = ["manage-payroll"];
            $advancedPermissions = ["manage-payroll", "advanced-manage-payroll-info", "advanced-manage-payroll-list-update"];

            if (!$user->checkHavePermission($normalPermissions, $advancedPermissions, $user->getSettingAdvancedPermissionFlow(), null, true)) {
                throw new HumanErrorException(__("error.permission"));
            }
        } else {
            if (
                $user->getRole() != Constant::ROLE_INTERNAL_DIRECTOR
                && !$user->iGlocalEmployee->isAssignedFor($client_id)
            ) {
                throw new HumanErrorException(__("error.permission"));
            }
        }

        $client = Client::select('*')->where('id', $client_id);

        if (empty($client)) {
            throw new HumanErrorException(__("error.not_found", ["name" => __("model.clients.company")]));
        }

        $cst = CalculationSheetTemplate::query()->where("id", $args['payroll_template'])
            ->first();

        if (!$cst) {
            throw new HumanErrorException(__("error.not_found", ["name" => __("model.calculation_sheet_template")]));
        }

        /** @var CalculationSheetTemplate $cst */
        $cs = $cst->createCalculationSheet(
            $args['name'],
            $args['date_from'],
            $args['date_to'],
            $args['other_from'] ?? "",
            $args['other_to'] ?? "",
            $args['month'],
            $args['year'],
            $args['list_employee_notify_ids'] ?? "",
            $user->is_internal,
            $args['prefered_reviewer_id'] ?? "",
            $args['payslip_date'] ?? NULL,
            $args['payslip_complaint_deadline'] ?? NULL,
            $args['is_send_mail_payslip'] ?? true
        );
        $cs->creator_id = $user->id;
        $cs->type = !empty($args['type']) ? $args['type'] : "";
        $cs->save();

        return 'ok';
    }

    public function internalCanApproveCalculationSheet($root, array $args): ?string
    {
        $client_id = $args['id'];

        $flow_name = 'CLIENT_REQUEST_PAYROLL';

        $flow = DB::table('approve_flow_users')
            ->join('approve_flows', function ($join) use ($flow_name) {
                $join->on('approve_flow_users.approve_flow_id', '=', 'approve_flows.id')
                    ->where('approve_flows.flow_name', $flow_name);
            })->where('approve_flows.client_id', $client_id)->select('approve_flows.flow_name')->get();

        return (count($flow) > 0 ? 'ok' : 'fail');
    }

    public function exportPDFShuiOrUnionFee($root, array $args)
    {

        $calculationSheet = CalculationSheet::authUserAccessible(["advanced_permissions" => ["advanced-manage-payroll-list-export"]])->find($args['input']['id']);

        if (!$calculationSheet) {
            throw new HumanErrorException(__("error.permission"));
        }

        try {
            if ($calculationSheet->PdfPath && !$args['input']['forceExport']) {
                $res_file_name = $calculationSheet->getFirstMedia("pdf")->file_name;
                $res_file_path = MediaHelper::getPublicTemporaryUrl($calculationSheet->getFirstMedia("pdf")->getPath());
            } else {
                // Check file excel
                if (
                    $calculationSheet->excelPath && $args['input']['insurance_for_vietnamese'] && isset($args['input']['variable_name_1'])
                    || $calculationSheet->excelPath && $args['input']['insurance_for_foreigner'] && isset($args['input']['variable_name_2'])
                    || $calculationSheet->excelPath && $args['input']['trade_union_fee'] && isset($args['input']['variable_name_3'])
                    || $calculationSheet->excelPath && $args['input']['labor_union_fee'] && isset($args['input']['variable_name_4'])
                ) {

                    $data = [];

                    $data['month'] = $calculationSheet->month;
                    $data['year'] = $calculationSheet->year;

                    $dt = Carbon::createFromTimeStamp(strtotime("last day of this month", Carbon::create($data['year'], $data['month'])->timestamp));

                    if ($dt->isDayOfWeek(Carbon::SUNDAY)) {
                        $data['due_date'] = $dt->subDays(2)->format('d M Y');
                    } elseif ($dt->isDayOfWeek(Carbon::SATURDAY)) {
                        $data['due_date'] = $dt->subDay()->format('d M Y');
                    } else {
                        $data['due_date']  = $dt->format('d M Y');
                    }

                    $data['insurance_for_vietnamese'] = $args['input']['insurance_for_vietnamese'];
                    $data['insurance_for_foreigner'] = $args['input']['insurance_for_foreigner'];
                    $data['trade_union_fee'] = $args['input']['trade_union_fee'];
                    $data['labor_union_fee'] = $args['input']['labor_union_fee'];

                    if ($args['input']['insurance_for_vietnamese'] && isset($args['input']['variable_name_1'])) {

                        $insurance_for_vietnamese = CalculationSheetVariable::where([
                            'calculation_sheet_id' => $args['input']['id'],
                            'calculation_sheet_variables.variable_name' => $args['input']['variable_name_1']
                        ])->whereHas('clientEmployee', function (Builder $query) {
                            $query->where('client_employees.nationality', 'Việt Nam');
                        })->sum('variable_value');

                        $data['payable_insurance_for_vietnamese'] =  $insurance_for_vietnamese + $args['input']['adjustment_1'];
                    }

                    if ($args['input']['insurance_for_foreigner'] && isset($args['input']['variable_name_2'])) {

                        $insurance_for_foreigner = CalculationSheetVariable::where([
                            'calculation_sheet_id' => $args['input']['id'],
                            'calculation_sheet_variables.variable_name' => $args['input']['variable_name_2']
                        ])->whereHas('clientEmployee', function (Builder $query) {
                            $query->where('client_employees.nationality', '!=', 'Việt Nam');
                        })->sum('variable_value');

                        $data['payable_insurance_for_foreigner'] =  $insurance_for_foreigner + $args['input']['adjustment_2'];
                    }

                    if ($args['input']['trade_union_fee'] && isset($args['input']['variable_name_3'])) {

                        $payable_trade_union_fee = CalculationSheetVariable::where([
                            'calculation_sheet_id' => $args['input']['id'],
                            'calculation_sheet_variables.variable_name' => $args['input']['variable_name_3']
                        ])->sum('variable_value');

                        $data['payable_trade_union_fee'] =  $payable_trade_union_fee + $args['input']['adjustment_3'];
                    }

                    if ($args['input']['labor_union_fee'] && isset($args['input']['variable_name_4'])) {

                        $payable_labor_union_fee = CalculationSheetVariable::where([
                            'calculation_sheet_id' => $args['input']['id'],
                            'calculation_sheet_variables.variable_name' => $args['input']['variable_name_4']
                        ])->sum('variable_value');

                        $data['payable_labor_union_fee'] =  $payable_labor_union_fee + $args['input']['adjustment_4'];
                    }

                    if ($calculationSheet->PdfPath && $args['input']['forceExport']) {
                        $media = $calculationSheet->getFirstMedia("pdf");
                        $media->delete();
                    }

                    // Create PDF
                    $client = Client::find($args['input']['client_id']);

                    $view = view('pdfs.ShuiOrUnionFee', ['client' => $client, 'data' => $data]);

                    Storage::disk('local')->put('index.html', $view);
                    $htmlPath = Storage::disk('local')->path('index.html');

                    $response = Http::attach("files", $view, "index.html")
                        ->sink($htmlPath)
                        ->post(
                            config('vpo.gotenberg.url') .  '/forms/chromium/convert/html',
                            []
                        );

                    if (!$response->successful()) {
                        throw new PdfApiFailedException($response->body());
                    }

                    Storage::disk('local')->delete('index.html');

                    $name = 'tmp_pdf_generate_output_shui' . '_' . uniqid() . '.pdf';


                    Storage::disk('local')->put($name, $response);

                    $calculationSheet->addMediaFromDisk($name, 'local')
                        ->storingConversionsOnDisk('minio')
                        ->toMediaCollection('pdf', 'minio');

                    $re_calculationSheet = CalculationSheet::find($args['input']['id']);

                    $res_file_path = MediaHelper::getPublicTemporaryUrl($re_calculationSheet->getFirstMedia("pdf")->getPath());
                    $res_file_name = $name;
                } elseif (!$args['input']['insurance_for_vietnamese'] && !$args['input']['insurance_for_foreigner'] && !$args['input']['trade_union_fee'] && !$args['input']['labor_union_fee']) {
                    throw new \Exception(__("model.notifications.creating_failed"));
                } else {
                    throw new \Exception(__("no_data"));
                }
            }

            return json_encode(['error' => false, 'file' => $res_file_path, 'name' => $res_file_name]);
        } catch (\Exception $e) {
            return json_encode(['error' => true, 'message' => $e->getMessage()]);
        }
    }
}
