<?php

namespace App\Jobs;

use App\Models\Checking;
use App\Models\ClientEmployee;
use App\Models\ClientWorkflowSetting;
use App\Models\TimesheetTmp;
use App\Support\Constant;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SpecialTimesheetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $client_id;
    protected string $import_key;
    protected string $employee_id;
    protected $day_begin_mark;
    // protected $clientEmployees;
    protected $clientEmployee;
    protected $workflowSetting;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        string $client_id,
        string $import_key,
        string $employee_id
    ) {
        $this->client_id = $client_id;
        $this->import_key = $import_key;
        $this->employee_id = $employee_id;
        $this->clientEmployee = ClientEmployee::where('client_id', $this->client_id)
        ->where('id', $this->employee_id)
        ->where('status', 'đang làm việc')
        ->first();
        $this->workflowSetting = ClientWorkflowSetting::where('client_id', $this->client_id)->first();
        $this->day_begin_mark = $this->workflowSetting->timesheet_day_begin;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        TimesheetTmp::where('import_key', $this->import_key)
            ->where('client_employee_id', $this->employee_id)
            ->chunkById(1000, function ($items) {
                $checkingList = [];
                foreach ($items as $item) {
                    // $clientEmployee = $this->clientEmployees->get($item->client_employee_id);
                    $clientEmployee = $this->clientEmployee;
                    $start_next_day = 0;
                    $start_time = 0;
                    $start_log_date = 0;
                    if ($item->check_in != '0000-00-00 00:00:00') {
                        $start_time = Carbon::createFromFormat('Y-m-d H:i:s', $item->check_in, Constant::TIMESHEET_TIMEZONE);
                        if ($start_time->format('H:i') >= $this->day_begin_mark) {
                            $start_log_date = $start_time->toDateString();
                        } else {
                            $start_log_date = $start_time->subDay()->toDateString();
                            $start_next_day = 1;
                        }
                    }

                    $next_day = 0;
                    $end_time = 0;
                    $end_log_date = 0;
                    if ($item->check_out != '0000-00-00 00:00:00') {
                        $end_time = Carbon::createFromFormat('Y-m-d H:i:s', $item->check_out, Constant::TIMESHEET_TIMEZONE);
                        if ($end_time->format('H:i') >= $this->day_begin_mark) {
                            $end_log_date = $end_time->toDateString();
                        } else {
                            $end_log_date = $end_time->subDay()->toDateString();
                            $next_day = 1;
                        }
                    }

                    // when start_log_date is the same with end_log_date
                    // we store and re-calculate 1 time for both
                    if (!empty($start_log_date) && !empty($end_log_date) && $start_log_date == $end_log_date) {
                        $timesheet = $clientEmployee->touchTimesheet($start_log_date);
                        $checkingList[] = [
                            'client_id' => $this->client_id,
                            'client_employee_id' => $this->employee_id,
                            'checking_time' => $start_time,
                            'source' => 'SpecialImport'
                        ];
                        $checkingList[] = [
                            'client_id' => $this->client_id,
                            'client_employee_id' => $this->employee_id,
                            'checking_time' => $end_time,
                            'source' => 'SpecialImport'
                        ];

                        if ($timesheet->isUsingMultiShift($this->workflowSetting)) {
                            $timesheet->checkTimeWithMultiShift($start_time, 'Import');
                            $timesheet->checkTimeWithMultiShift($end_time, 'Import');
                            $timesheet->check_in = $start_time->format("H:i");
                            $timesheet->start_next_day = $start_next_day;
                            $timesheet->check_out = $end_time->format("H:i");
                            $timesheet->next_day = $next_day;
                            $timesheet->calculateMultiTimesheet($this->workflowSetting);
                            $timesheet->saveQuietly();
                        } else {
                            $timesheet->check_in = $start_time->format("H:i");
                            $timesheet->start_next_day = $start_next_day;
                            $timesheet->check_out = $end_time->format("H:i");
                            $timesheet->next_day = $next_day;
                            $timesheet->flexible = 1;
                            $timesheet->oldRecalculate($clientEmployee);
                            $timesheet->saveQuietly();
                        }
                    } else {
                        //only store and re-calculate start_date
                        if (!empty($start_log_date)) {
                            $timesheet = $clientEmployee->touchTimesheet($start_log_date);
                            $checkingList[] = [
                                'client_id' => $this->client_id,
                                'client_employee_id' => $this->employee_id,
                                'checking_time' => $start_time,
                                'source' => 'SpecialImport'
                            ];
                            if ($timesheet->isUsingMultiShift($this->workflowSetting)) {
                                $timesheet->checkTimeWithMultiShift($start_time, 'Import');
                                $timesheet->check_in = $start_time->format("H:i");
                                $timesheet->start_next_day = $start_next_day;
                                $timesheet->calculateMultiTimesheet($this->workflowSetting);
                                $timesheet->saveQuietly();
                            } else {
                                $timesheet->check_in = $start_time->format("H:i");
                                $timesheet->start_next_day = $start_next_day;
                                $timesheet->oldRecalculate($clientEmployee);
                                $timesheet->saveQuietly();
                            }
                        }

                        //only store and re-calculate end_date
                        if (!empty($end_log_date)) {
                            $timesheet = $clientEmployee->touchTimesheet($end_log_date);
                            $checkingList[] = [
                                'client_id' => $this->client_id,
                                'client_employee_id' => $this->employee_id,
                                'checking_time' => $end_time,
                                'source' => 'SpecialImport'
                            ];

                            if ($timesheet->isUsingMultiShift($this->workflowSetting)) {
                                $timesheet->checkTimeWithMultiShift($end_time, 'Import');
                                $timesheet->check_out = $end_time->format("H:i");
                                $timesheet->next_day = $next_day;
                                $timesheet->calculateMultiTimesheet($this->workflowSetting);
                                $timesheet->saveQuietly();
                            } else {
                                $timesheet->check_out = $end_time->format("H:i");
                                $timesheet->next_day = $next_day;
                                $timesheet->oldRecalculate($clientEmployee);
                                $timesheet->saveQuietly();
                            }
                        }
                    }
                    $item->delete();
                }

                Checking::upsert($checkingList, ['client_employee_id', 'checking_time']);
            }, 'id');
    }
}
