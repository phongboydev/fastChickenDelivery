<?php

namespace App\Jobs;

use App\Events\DataImportCreatedEvent;

use App\User;
use App\Models\DataImport;
use Illuminate\Support\Facades\Storage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use \Maatwebsite\Excel\Validators\ValidationException as ValidationException;
use App\Imports\ClientEmployeeImportMultiSheet;
use App\Imports\Sheets\ClientEmployeeBasicSheetNewImport;
use App\Imports\Sheets\ClientEmployeeBasicSheetUpdateImport;
use App\Imports\Sheets\ClientEmployeeSalarySheetImport;
use App\Models\ClientEmployee;
use App\Notifications\IglocalImportClientEmployeeNotification;
use App\Support\Constant;
use App\Support\ImportHelper;
use Carbon\Carbon;

class ImportClientEmployee implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $approve;
    protected $approveContent;

    /**
     * Create a new job instance.
     *
     * @param SurveyJob           $job
     * @param SurveyJobSubmission $submission
     * @param array               $subjects
     * @param array               $htmls
     * @param string|null         $emailOverride
     */
    public function __construct($approve)
    {
        $this->approve = $approve;
        $this->approveContent = json_decode($approve->content, true);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (optional($this->approveContent)['data']) {
            $clientId = $this->approveContent['client_id'];
            foreach ($this->approveContent['data'] as $row) {
                $row = array_merge($row, ['client_id' => $clientId]);

                switch ($this->approveContent['type']) {
                    case ImportHelper::CLIENT_EMPLOYEE:
                        ImportHelper::updateClientEmployee($row, $clientId, true);
                        break;
                    case ImportHelper::CONTRACT_INFORMATION:
                        ImportHelper::updateContract($row, $clientId);
                        break;
                    case ImportHelper::DEPENDANT_INFORMATION:
                        ImportHelper::updateDependent($row, $clientId);
                        break;
                    case ImportHelper::PAID_LEAVE:
                        ImportHelper::updatePaidLeave($row, $clientId);
                        break;
                    case ImportHelper::SALARY_INFORMATION:
                        ImportHelper::updateSalary($row, $clientId);
                        break;
                }
            }
        } else {
            $dataImport = DataImport::where('id', $this->approve->target_id)->first();
            if (empty($dataImport)) return;
            $inputFileType = 'Xlsx';
            $inputFileName = 'client_employee_import_' . time() . '.xlsx';
            $inputFileImport = 'ClientEmployeeImport/' . $inputFileName;
            $media = $dataImport->getMedia('DataImport');
            $url = $media[0]->getTemporaryUrl(Carbon::now()->addMinutes(5));
            Storage::disk('local')->put($inputFileImport, file_get_contents($url));
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
            $reader->setLoadAllSheets();
            $spreadsheet = $reader->load(storage_path('app/' . $inputFileImport));
            $sheetNames = $spreadsheet->getSheetNames();
            $totalSheet = $spreadsheet->getSheetCount();

            $errors = [];

            $clientEmployeeBasicSheetImport = $this->approveContent['is_new'] ? new ClientEmployeeBasicSheetNewImport($this->approveContent['client_id']) : new ClientEmployeeBasicSheetUpdateImport($this->approveContent['client_id'], $this->approve->creator_id);

            $clientEmployeeSalarySheetImport = new ClientEmployeeSalarySheetImport($this->approveContent['client_id']);

            $sheet1Errors = $clientEmployeeBasicSheetImport->validate($spreadsheet->getSheet(0));
            $sheet2Errors = $clientEmployeeSalarySheetImport->validate($spreadsheet->getSheet(1));

            if ($sheet1Errors) $errors[$sheetNames[0]] = $sheet1Errors;
            if ($sheet2Errors) $errors[$sheetNames[1]] = $sheet2Errors;

            $creator = User::where('id', $this->approve->original_creator_id)->first();

            if (empty($errors)) {

                try {

                    Excel::import(new ClientEmployeeImportMultiSheet($this->approveContent['client_id'], $totalSheet, false, $sheetNames, $this->approve->creator_id), storage_path('app/' . $inputFileImport));

                    Storage::disk('local')->delete($inputFileImport);

                    if (!empty($creator))
                        $creator->notify(new IglocalImportClientEmployeeNotification($this->approve, 'success'));
                } catch (ValidationException $e) {

                    if (!empty($creator))
                        $creator->notify(new IglocalImportClientEmployeeNotification($this->approve, 'fail'));

                    Storage::disk('local')->delete($inputFileImport);
                }
            } else {

                if (!empty($creator))
                    $creator->notify(new IglocalImportClientEmployeeNotification($this->approve, 'fail'));
            }
        }
    }
}
