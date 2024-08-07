<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\PowerBiReport;
use Carbon\Carbon;
use Icewind\SMB\BasicAuth;
use Icewind\SMB\Exception\InvalidTypeException;
use Icewind\SMB\Exception\NotFoundException;
use Icewind\SMB\ServerFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ProcessPowerBiReportCommand extends Command
{

    protected $signature = 'powerbi:process';

    protected $description = 'Process power bi output';

    /**
     * @throws \Icewind\SMB\Exception\InvalidTypeException
     * @throws \Icewind\SMB\Exception\NotFoundException
     * @throws \Icewind\SMB\Exception\AlreadyExistsException
     * @throws \Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig
     * @throws \Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist
     * @throws \Icewind\SMB\Exception\DependencyException
     */
    public function handle()
    {
        if (!config('vpo.power_bi.enabled')) {
            $this->warn('Power BI is disabled. Skip upload to Power BI');
            return Command::SUCCESS;
        }

        $this->line('Process Power BI output');
        // connect to smb server, put each file to the export path
        $serverFactory = new ServerFactory();
        $auth = new BasicAuth(config('vpo.power_bi.smb.user'), config('vpo.power_bi.smb.work_group'),
            config('vpo.power_bi.smb.password'));
        $server = $serverFactory->createServer(config('vpo.power_bi.smb.host'), $auth);

        $share = $server->getShare(config('vpo.power_bi.smb.share'));

        // try get dir for result, if not exist, create it
        try {
            $share->dir(config('vpo.power_bi.smb.result_path'));
        } catch (\Exception $e) {
            $share->mkdir(config('vpo.power_bi.smb.result_path'));
        }

        $files = $share->dir(config('vpo.power_bi.smb.result_path'));

        $now = Carbon::now();
        $year  = $now->year;
        $month = $now->month;

        if (!Storage::disk('local')->exists('PowerBiOutput')) {
            Storage::disk('local')->makeDirectory('PowerBiOutput');
        }

        $clientExisted = [];
        foreach ($files as $file) {
            $localDiskPath = "PowerBiOutput/".$file->getName();

            try {
                if (Storage::disk('local')->exists($localDiskPath)) {
                    Storage::disk('local')->delete($localDiskPath);
                }
                $share->get($file->getPath(), Storage::disk('local')->path($localDiskPath));
                $this->info('Done ' . $localDiskPath);
            } catch (InvalidTypeException $e) {
                $this->warn("InvalidTypeException: " . $file->getName());
                continue;
            } catch (NotFoundException $e) {
                $this->warn("NotFoundException file gone: " . $file->getName());
                continue;
            }
        }

        $files = Storage::disk('local')->allFiles("PowerBiOutput");
        foreach ($files as $fullPath) {
            $file = basename($fullPath);
            $parts = explode('_', $file, 2);
            $this->info("Process report ... " . $file);
            if (count($parts) < 2) {
                $this->warn("Invalid report name: " . $file);
                continue;
            }
            $clientId = $parts[0];
            if (!isset($clientExisted[$clientId])) {
                $clientExisted[$clientId] = Client::where('id', $clientId)->exists();
            }

            if (!$clientExisted[$clientId]) {
                $this->warn("Skip unknown Client: " . $file);
                continue;
            }

            // Save file to local disk
            $disk = Storage::disk('local');
            if (!$disk->exists("PowerBiOutput")) {
                $disk->createDir("PowerBiOutput");
            }
            $localDiskPath = "PowerBiOutput/".$file;

            $report = PowerBiReport::query()->where('client_id', $clientId)
                                ->where('year',$year)
                                ->where('month', $month)
                                ->where('report_name', $parts[1])
                                ->first();

            if (!$report) {
                $report = new PowerBiReport();
                $report->client_id = $clientId;
                $report->year = $year;
                $report->month = $month;

            }

            $report->report_name = $parts[1];
            $report->touch();
            $report->save();

            $media = $report->getFirstMedia();
            if ($media) {
                $media->delete();
            }

            $report->addMedia($disk->path($localDiskPath))
                   ->toMediaCollection('default', 'minio');

            //     $this->line('Uploading '.$file);
            //     $filePath = $folderZip.$file;
            //     $share->put($filePath, config('vpo.power_bi.smb.export_path').'/'.$file);
        }
    }
}
