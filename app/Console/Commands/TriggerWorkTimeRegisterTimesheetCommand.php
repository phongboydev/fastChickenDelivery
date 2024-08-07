<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\WorktimeRegister;
use Illuminate\Console\Command;

class TriggerWorkTimeRegisterTimesheetCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workTimeRegisterTimesheet:trigger {fromDate} {toDate?} {--clientCode= : Code of client}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or update work time register timesheet';

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
        $clientCode = $this->option("clientCode");
        $from = $this->argument("fromDate");
        $to = $this->argument("toDate");
        if (empty($from)) return 0;
        $wsStart = $from . ' 00:00:00';
        $wsEnd = !empty($to) ? $to . ' 23:59:59' : "2030-12-31 23:59:59";
        $clients = collect();
        if ($clientCode) {
            $clients = Client::where('code', $clientCode)->get('id');
            if ($clients->isEmpty()) return 0;
        }
        if ($clients->isNotEmpty()) {
            $clientIds = $clients->pluck('id');
            $this->upsertWorkTimeRegisterTimesheet($wsStart, $wsEnd, $clientIds);
        } else {
            $this->upsertWorkTimeRegisterTimesheet($wsStart, $wsEnd);
        }

        return 1;
    }

    private function upsertWorkTimeRegisterTimesheet($wsStart, $wsEnd, $clientIds = []) {
        $wtr = WorktimeRegister::query()
            ->whereStatus('approved')
            ->where(function ($subQuery) use ($wsStart, $wsEnd) {
                $subQuery->whereBetween('start_time', [
                    $wsStart,
                    $wsEnd,
                ])
                    ->orWhereBetween('end_time', [
                        $wsStart,
                        $wsEnd,
                    ])
                    ->orWhere(function ($query) use ($wsStart) {
                        $query->where('start_time', '<=', $wsStart)
                            ->where('end_time', '>=', $wsStart);
                    });
            })
            ->whereIn('type', ['overtime_request']);
        if ($clientIds) {
            $wtr->whereHas('clientEmployee', function ($ce) use ($clientIds) {
                $ce->whereIn('client_id', $clientIds);
            });
        }
        $wtr->chunkById(100, function ($items) {
                foreach ($items as $item) {
                    /** @var WorktimeRegister $item */
                    $this->line("Process ... " . $item->id);
                    try {
                        $item->createOrUpdateOTWorkTimeRegisterTimesheet();
                    } catch(\Throwable $th) {
                        $this->line("ERROR ... " . $th->getMessage());
                    }
                }
            });
    }
}
