<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\DataImportHistory;
use App\Models\DataImport;
use App\Models\Client;
use App\Imports\HistoryFileImport;
use Illuminate\Support\Str;

class ImportHistory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $data;
    private $folderName;
    private $type;
    private $lang;
    private $clientId;
    private $authId;
    private $headersList;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data, $type, $clientId, $authId, $lang, $headersList)
    {
        $this->data = $data;
        $this->type = $type;
        $this->lang = $lang;
        $this->clientId = $clientId;
        $this->authId = $authId;
        $this->headersList = $headersList;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $client = Client::select('code')->where('id', $this->clientId)->first();
        $fileName = Str::finish(sprintf('%s_%s_%s', $client->code, $this->type, now()->format('Y-m-d-H-i-s-u')), "_{$this->lang}.xlsx");
        $folderName = Str::replace('_', '', Str::title($this->type));
        $pathFile = $folderName . 'Export/' . $fileName;
        Excel::store((new HistoryFileImport($this->data, $folderName, $this->type, $this->clientId, $this->lang, $this->headersList)), $pathFile, 'minio');

        $importDataHistory = DataImportHistory::create([
            'type' => 'IMPORT_' . Str::of($this->type)->upper(),
            'client_id' => $this->clientId,
            'user_id' => $this->authId,
        ]);

        $importDataHistory->addMediaFromDisk($pathFile, 'minio')
            ->toMediaCollection('DataImportHistory', 'minio');
    }
}
