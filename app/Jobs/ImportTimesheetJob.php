<?php

namespace App\Jobs;

use App\Imports\TimesheetsImport;
use Exception;
use Illuminate\Bus\Queueable;
// use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use App\Events\DataImportCreatedEvent;
use App\Models\TimeSheetEmployeeImport;

class ImportTimesheetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    protected $user;
    protected $clientId;
    protected $inputFileImport;
    protected $importId;

    public $tries = 1;
    public $timeout = 600;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($clientId, $inputFileImport, $user, $importId)
    {
        $this->user = $user;
        $this->clientId = $clientId;
        $this->inputFileImport = $inputFileImport;
        $this->importId = $importId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $importData = [
                'status' => '',
                'note' => ''
            ];

            $timeSheetEmployeeImport = TimeSheetEmployeeImport::where('id', $this->importId)->first();

            $mediaItems = $timeSheetEmployeeImport->getMedia('TimeSheetEmployeeImport');

            if ($mediaItems) {

                $path = $mediaItems[0]->getPath();

                Storage::disk('local')->put( $path, Storage::get($path));

                Excel::import(new TimesheetsImport($this->clientId, $this->user), storage_path('app/' . $path));
                DataImportCreatedEvent::dispatch([
                    'type' => 'IMPORT_TIMESHEET',
                    'client_id' => $this->clientId,
                    'user_id' => $this->user->id,
                    'file' => $path
                ]);
                $importData['status'] = 'success';

                Storage::disk('local')->delete($path);
            }
        } catch (\Throwable $th) {
            logger(self::class . " Error ", [$th]);
            $importData['status'] = 'failed';
            $importData['note'] = $th;
        } finally {
            logger("Updated");
            $import = TimeSheetEmployeeImport::query()->where('id', $this->importId)->firstOrFail();
            $import->status = $importData['status'];

            $note = '';
            if(!empty($importData['note']) && $importData['note']->getMessage()) {
                $message = json_decode($importData['note']->getMessage(), true);
                // Get content message
                $note = (!empty($message['msg'])) ? $message['msg'] : $importData['note'];
            } else {
                $note = $importData['note'];
            }

            $import->note = $note;
            $import->save();
            logger("Done", $importData);
            $this->delete();
        }
    }

    public function failed(Exception $exception)
    {
        $importData = [
            'status' => 'failed',
            'note' => $exception->getMessage()
        ];
        TimeSheetEmployeeImport::where('id', $this->importId)->update($importData);
    }
}
