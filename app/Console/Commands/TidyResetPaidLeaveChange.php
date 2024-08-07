<?php

namespace App\Console\Commands;

use App\Models\WorktimeRegister;
use App\Models\Client;
use Illuminate\Console\Command;
use App\Support\WorktimeRegisterHelper;

class TidyResetPaidLeaveChange extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tidy:reset_paid_leave_change {client_id?} {--from_date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rest paid leave change so_gio_tam_tinh = 0 and da_tru = 0';

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
     * @return mixed
     */
    public function handle()
    {
        $clientId = $this->argument("client_id");
        $fromDate = $this->option('from_date');

        $query = WorkTimeRegister::where('sub_type', 'authorized_leave')
            ->where('category', 'year_leave')
            ->where('status', '!=', 'canceled_approved')
            ->where('status', '!=', 'pending')
            ->whereHas('periods', function ($periods) {
                return $periods->where('so_gio_tam_tinh', 0)->where('da_tru', 0);
            })
            ->with('client')->with('periods')->with('clientEmployee');

        if ($clientId) {
            $query->whereHas('client', function ($subQuery) use ($clientId) {
                return $subQuery->where((new Client)->getTable() . ".id", $clientId);
            });
        }

        if ($fromDate) {
            $query->whereDate('start_time', '>=',  $fromDate);
        } else {
            $query->whereDate('start_time', '>',  date('Y-m-d H:i:s'));
        }

        $query->chunkById(100, function ($worktimeRegisters) {
            foreach ($worktimeRegisters as $worktimeRegister) {
                try {
                    $this->line("Processed ... " . $worktimeRegister->id);
                    WorktimeRegisterHelper::processLeaveChange($worktimeRegister);
                } catch (\Throwable $e) {
                    $this->line("Processed Error ... " . $worktimeRegister->id . " : " . $e->getMessage());
                }
            }
        }, 'id');
    }
}
