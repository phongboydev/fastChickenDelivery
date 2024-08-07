<?php

namespace App\Console\Commands;

use App\Models\Approve;
use App\Models\ApproveFlow;
use App\Models\Client;
use App\Models\Timesheet;
use Illuminate\Console\Command;

class HotFixApproveUnfinishedCommand extends Command
{

    protected $signature = 'tidy:unfinished-approves {--d|dry-run} {clientCode}';

    protected $description = 'Clean unfinished approves';

    public function handle()
    {
        $clientCode = $this->argument('clientCode');
        $type       = 'CLIENT_REQUEST_TIMESHEET_EDIT_WORK_HOUR'; // $this->argument('type');
        $dryRun = $this->option('dry-run');

        $client = Client::where('code', $clientCode)->firstOrFail();

        $maxStep = ApproveFlow::query()->where('client_id', $client->id)
                                       ->where('flow_name', $type)
                                       ->max('step');

        $query = Approve::query();
        $query->where('client_id', $client->id)
              ->where('type', $type)
              ->where('step', '<', $maxStep)
              ->where('is_final_step', 1);

        $query->chunk(100, function ($approves) use ($dryRun) {
            foreach ($approves as $approve) {
                /** @var Approve $approve */
                if (empty($approve->approved_at)) {
                    return;
                }
                $this->line('Approve: ' . $approve->id);
                if ($approve->target && $approve->target instanceof Timesheet) {
                    $ts = $approve->target;
                    /**
                     * {"current_check_in":"07:13","current_check_out":"15:35","request_check_in":"07:13","request_check_out":"15:35","reason":"Giao hàng xe máy ","id":"3f60b25f-2f79-4a93-a0cd-2a5a82d90024","log_date":"2022-05-06","client_employee_id":"393bee43-b2fc-4741-b6fc-6c70d8317f49"}
                     */
                    $content = json_decode($approve->content, true);
                    if (isset($content['request_check_in'])) {
                        $checkIn = $content['request_check_in'] ?? '';
                        $this->line('request in: ' . $checkIn);
                        if ($checkIn && $checkIn != $ts->check_in) {
                            $this->line('Update check-in value: ' . $ts->check_in . "->" . $checkIn);
                            $ts->check_in = $checkIn;
                        } else {
                            $this->line('Skip check-in value: ' . $checkIn);
                        }
                    }
                    if (isset($content['request_check_out'])) {
                        $checkOut = $content['request_check_out'] ?? '';
                        $this->line('request out: ' . $checkOut);
                        if ($checkOut && $checkOut != $ts->check_out) {
                            $this->line('Update check-out value: ' . $ts->check_out . "->" . $checkOut);
                            $ts->check_out = $checkOut;
                        } else {
                            $this->line('Skip check-out value: ' . $checkOut);
                        }
                    }
                    if (!$dryRun) {
                        $approve->target->save();
                    }
                }
            }
        });
    }
}
