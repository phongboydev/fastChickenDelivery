<?php

namespace App\Console\Commands;

use App\Models\CalculationSheet;
use App\Models\CalculationSheetClientEmployee;
use App\Models\CalculationSheetVariable;
use App\Models\Client;
use App\Models\ClientEmployee;
use App\Models\ClientEmployeeCustomVariable;
use Icewind\SMB\BasicAuth;
use Icewind\SMB\ServerFactory;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use League\Csv\Writer;

class ExportDataCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'powerbi:export {clientCodes?*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export data from database';

    /**
     * Create a new command instance.
     *
     * @return void
     */

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $folderExport = storage_path('app/ExportData');
        $folderZip = $folderExport; // TODO delete old variable
        $files = [
            'clients.csv',
            'client_employees.csv',
            'client_employee_custom_variables.csv',
            'calculation_sheets.csv',
            'calculation_sheet_variables.csv',
            'calculation_sheet_client_employees.csv',
        ];
        $tables = [
            'clients',
            'client_employees',
            'client_employee_custom_variables',
            'calculation_sheets',
            'calculation_sheet_variables',
            'calculation_sheet_client_employees',
        ];

        if (is_dir($folderExport) === false) {
            mkdir($folderExport, 0755, true);
        }

        // clean up old file if any
        $localDisk = Storage::disk('local');
        foreach ($files as $index => $file) {
            $filePath = $folderZip.DIRECTORY_SEPARATOR.$file;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $table = $tables[$index];
            $writer = Writer::createFromPath($filePath, 'a+');
            $headers = \Schema::getColumnListing($table);
            $writer->insertOne($headers);
        }

        $clientCodes = $this->argument('clientCodes');

        $pathFile = 'ExportData/clients.csv';
        $this->line("Export ".$pathFile);

        $clientQuery = Client::query();
        $clientIds = [];
        if (!empty($clientCodes)) {
            $clientQuery = $clientQuery->whereIn('code', $clientCodes);
            $clientIds = $clientQuery->get()->pluck("id");
        }
        $this->exportCsv($clientQuery, $pathFile);

        $pathFile = 'ExportData/client_employees.csv';
        $this->line("Export ".$pathFile);
        $ceQuery = ClientEmployee::query();
        if (!empty($clientIds)) {
            $ceQuery = $ceQuery->whereIn('client_id', $clientIds);
        }
        $this->exportCsv($ceQuery, $pathFile);

        $pathFile = 'ExportData/client_employee_custom_variables.csv';
        $this->line("Export ".$pathFile);
        $cecvQuery = ClientEmployeeCustomVariable::query();
        if (!empty($clientIds)) {
            $cecvQuery = $cecvQuery->whereHas('clientEmployee', function ($ce) use ($clientIds) {
                $ce->whereIn('client_id', $clientIds);
            });
        }
        $this->exportCsv($cecvQuery, $pathFile, 2000);

        $pathFile = 'ExportData/calculation_sheets.csv';
        $this->line("Export ".$pathFile);
        $csQuery = CalculationSheet::query()
                                   ->select([
                                       "id",
                                       "client_id",
                                       "fomulas",
                                       "month",
                                       "year",
                                       "date_from",
                                       "date_to",
                                       "created_at",
                                       "deleted_at",
                                       "updated_at",
                                       "is_internal",
                                       "creator_id",
                                       "status",
                                       "payment_period",
                                       "payslip_date",
                                   ])
                                   ->whereIn('status', ['paid', 'client_approved'])
                                   ->where('year', Carbon::now()->year); // only this year data
        if (!empty($clientIds)) {
            $csQuery = $csQuery->whereIn('client_id', $clientIds);
        }
        $this->exportCsv($csQuery, $pathFile);

        // for progress showing
        $calculationSheetsCount = $csQuery->count();

        $pathFile = 'ExportData/calculation_sheet_variables.csv';
        $this->line("Export ".$pathFile);
        $variablesBar = $this->output->createProgressBar($calculationSheetsCount);
        $csQuery->chunk(100, function ($calculationSheets) use ($variablesBar, $pathFile) {
            foreach ($calculationSheets as $calculationSheet) {
                $csvQuery = CalculationSheetVariable::query()
                                                    ->whereNotNull('variable_name')
                                                    ->where('calculation_sheet_id', $calculationSheet->id);
                $this->exportCsv($csvQuery, $pathFile, 4000);
            }
            $variablesBar->advance();
        });
        $variablesBar->finish();
        $this->line("");
        $this->line("Finish export CalculationSheetVariable");

        $pathFile = 'ExportData/calculation_sheet_client_employees.csv';
        $this->line("Export ".$pathFile);
        $employeesBar = $this->output->createProgressBar($calculationSheetsCount);
        $csQuery->chunk(100, function ($calculationSheets) use ($employeesBar, $pathFile) {
            foreach ($calculationSheets as $calculationSheet) {
                $csceQuery = CalculationSheetClientEmployee::query()
                                                           ->where("calculation_sheet_id", $calculationSheet->id);
                $this->exportCsv($csceQuery, $pathFile);
            }
            $employeesBar->advance();
        });
        $employeesBar->finish();
        $this->line("");
        $this->line("Finish export CalculationSheetClientEmployee");

        if (config('vpo.power_bi.enabled')) {
            $this->line('Export to Power BI');
            // connect to smb server, put each file to the export path
            $serverFactory = new ServerFactory();
            $auth = new BasicAuth(config('vpo.power_bi.smb.user'), config('vpo.power_bi.smb.work_group'),
                config('vpo.power_bi.smb.password'));
            $server = $serverFactory->createServer(config('vpo.power_bi.smb.host'), $auth);

            $share = $server->getShare(config('vpo.power_bi.smb.share'));

            // try get dir, if not exist, create it
            try {
                $share->dir(config('vpo.power_bi.smb.export_path'));
            } catch (\Exception $e) {
                $share->mkdir(config('vpo.power_bi.smb.export_path'));
            }

            // try get dir for result, if not exist, create it
            try {
                $share->dir(config('vpo.power_bi.smb.result_path'));
            } catch (\Exception $e) {
                $share->mkdir(config('vpo.power_bi.smb.result_path'));
            }

            foreach ($files as $file) {
                $this->line('Uploading '.$file);
                $filePath = $folderZip.DIRECTORY_SEPARATOR.$file;
                $share->put($filePath, config('vpo.power_bi.smb.export_path').'/'.$file);
            }
        } else {
            $this->warn('Power BI is disabled. Skip upload to Power BI');
        }
        // $zip = new ZipArchive;
        // $fileName = 'export_data.zip';
        // if (is_dir($folderZip) === false){
        //     mkdir($folderZip, 0755,true);
        // }
        // \File::delete($folderZip . $fileName);
        // $this->info("Path------>" . $folderZip);
        // if (true === ($zip->open($folderZip . $fileName, ZipArchive::CREATE ))) {
        //     foreach ($files as $file) {
        //         $this->info("Zipping file...." . $folderZip . $file);
        //         $zip->addFile(($pathFile . $file), $file);
        //     }
        // }
        // $zip->close();
        return Command::SUCCESS;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query  Custom query
     * @param $pathFile
     *
     * @throws \League\Csv\CannotInsertRecord
     */
    private function exportCsv(Builder $query, $pathFile, $chunkSize = 200)
    {
        $path = storage_path("app/$pathFile");
        $writer = Writer::createFromPath($path, 'a+');
        $tableName = $query->getModel()->getTable();
        $headers = \Schema::getColumnListing($tableName);
        // $writer->insertOne($headers);
        $emptyRecord = array_map(
            function ($v) {
                return "";
            },
            array_flip($headers)
        );

        $query->chunk($chunkSize, function ($data) use ($writer, $emptyRecord) {
            foreach ($data as $value) {
                $record = $emptyRecord;
                $row = $value->toArray();
                // Format array to string
                foreach ($row as $key => $r) {
                    if (is_array($r)) { // Fix bug convert both object and value to array
                        $row[$key] = json_encode($r);
                    }
                }
                $record = array_merge($record, $row);
                $writer->insertOne($record);
            }
        });
        $this->info("Export to CSV table: $pathFile");
    }
}
