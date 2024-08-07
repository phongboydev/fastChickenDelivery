<?php

namespace App\Console;

use App\Console\Commands\CheckStuckCalculationSheetCommand;
use App\Console\Commands\CreateOvertimeCategoryEveryYearCommand;
use App\Console\Commands\LeaveManagementCarryForwardEntitlement;
use App\Console\Commands\GenerateContractPdfCommand;
use App\Console\Commands\GeneratePayslipPdfCommand;
use App\Console\Commands\GenerateProject;
use App\Console\Commands\HanetRecoverTimelogCommand;
use App\Console\Commands\HanetRenewExpiredToken;
use App\Console\Commands\HotFixApproveUnfinishedCommand;
use App\Console\Commands\IseedAllCommand;
use App\Console\Commands\ProcessPowerBiReportCommand;
use App\Console\Commands\RefreshWorkScheduleByClientCommand;
use App\Console\Commands\RemovingOldCheckingDataCommand;
use App\Console\Commands\RemovingErrorLogCommand;
use App\Console\Commands\TestEmailCommand;
use App\Console\Commands\TestPushDeviceNotificationCommand;
use App\Console\Commands\TidyCompanyNameCommand;
use App\Console\Commands\TidyDeletedClientVariablesCommand;
use App\Console\Commands\TidyTimesheetByWTR;
use App\Console\Commands\TidyResetClientEmployeeTarget;
use App\Console\Commands\TriggerTimesheetCommand;
use App\Console\Commands\TriggerUpdateColumnUnpaidLeaveHoursForOldData;
use App\Console\Commands\TriggerWorkTimeRegisterCommand;
use App\Console\Commands\ExportDataCommand;
use App\Console\Commands\CheckTimezoneSchedule;
use App\Console\Commands\DepartmentalSynchronization;
use App\Console\Commands\PositionSynchronization;
use App\Console\Commands\LeaveManagementDecrease;
use App\Console\Commands\RemindContractCommand;
use App\Console\Commands\LeaveManagementIncrease;
use App\Console\Commands\LeaveManagementSummarize;
use App\Console\Commands\LeaveManagementSync;
use App\Console\Commands\LeaveManagementGenerateCategoryBalance;
use App\Console\Commands\TidyRemoveDoubleApprove;
use App\Console\Commands\TidyDeleteDoubleClientEmployeeCustomVariablesCommand;
use App\Console\Commands\TidyRemoveDoubleClientCustomVariable;
use App\Console\Commands\FormulaSchedule;
use App\Console\Commands\ImplementSalaryUpdateCommand;
use App\Console\Commands\PermissionForceRefresh;
use App\Console\Commands\ProcessResetApproveNotWork;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class Kernel extends ConsoleKernel
{

    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\ContractReminder::class,
        Commands\TidyWorktimeRegisterPeriod::class,
        IseedAllCommand::class,
        TriggerTimesheetCommand::class,
        TriggerWorkTimeRegisterCommand::class,
        GenerateProject::class,
        TidyCompanyNameCommand::class,
        TidyDeletedClientVariablesCommand::class,
        HanetRecoverTimelogCommand::class,
        TestEmailCommand::class,
        TidyTimesheetByWTR::class,
        HanetRecoverTimelogCommand::class,
        RefreshWorkScheduleByClientCommand::class,
        GeneratePayslipPdfCommand::class,
        LeaveManagementIncrease::class,
        LeaveManagementDecrease::class,
        LeaveManagementSummarize::class,
        LeaveManagementSync::class,
        LeaveManagementGenerateCategoryBalance::class,
        CheckTimezoneSchedule::class,
        TidyResetClientEmployeeTarget::class,
        TidyRemoveDoubleApprove::class,
        ProcessPowerBiReportCommand::class,
        TidyDeleteDoubleClientEmployeeCustomVariablesCommand::class,
        RemindContractCommand::class,
        TidyRemoveDoubleApprove::class,
        TidyRemoveDoubleClientCustomVariable::class,
        HotFixApproveUnfinishedCommand::class,
        TidyResetClientEmployeeTarget::class,
        TidyDeleteDoubleClientEmployeeCustomVariablesCommand::class,
        RemindContractCommand::class,
        GenerateContractPdfCommand::class,
        FormulaSchedule::class,
        DepartmentalSynchronization::class,
        PositionSynchronization::class,
        ImplementSalaryUpdateCommand::class,
        TriggerUpdateColumnUnpaidLeaveHoursForOldData::class,
        HanetRenewExpiredToken::class,
        PermissionForceRefresh::class,
        LeaveManagementCarryForwardEntitlement::class,
        TestPushDeviceNotificationCommand::class,
        ProcessResetApproveNotWork::class,
        RemovingErrorLogCommand::class,
        CreateOvertimeCategoryEveryYearCommand::class,
        RemovingOldCheckingDataCommand::class,
        CheckStuckCalculationSheetCommand::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Monitor duplicated variable bug
        $schedule->call(function () {
            logger("[CRON] Monitor duplicated variable bug BEGIN");
            $results = DB::select("
            	SELECT count(*) as dupp,client_employee_id, variable_name
            	FROM client_employee_custom_variables
            	GROUP BY client_employee_id, variable_name
            	HAVING count(*) > 1
                LIMIT 11
            ");
            Mail::send([], [], function (Message $message) use ($results) {
                logger()->warning("[CRON] Monitor duplicated variable occured. NEED ATTENTION!");
                $sysAdmin = config("mail.sys_admin_email");
                if ($sysAdmin) {
                    $body = count($results) > 10 ?
                        "Found more than 10+ variables which is duplicated" :
                        "Duplicated variables founds: " . json_encode($results, JSON_PRETTY_PRINT);
                    $message->to($sysAdmin)
                        ->subject("[VPO][CRON] Monitor duplicated variable occured")
                        ->setBody($body, 'text/html');
                }
            });
            logger("[CRON] Monitor duplicated variable bug END");
        })->dailyAt('20:00');

        // Hanet webhook update check-in checkout timesheet
        $schedule->command('hanet:processwebhook')->everyThirtyMinutes();
        $schedule->command('Approve:reset')->hourly();

        /**
         * Hợp đồng lao động
         */
        // Nhắc nhở hết hạn
        // $schedule->command('command:contractreminder')->dailyAt('06:00');

        // Tạo đơn làm bù vào cuối tháng của lịch
        $schedule->command('command:createMakeupOrOTByMonth')->dailyAt('16:30');

        $schedule->command('formulaSchedule')->dailyAt('00:00');

        /**
         * Giờ phép
         */
        // Cập nhật balance mỗi đầu tháng
        $schedule->command('leave-management:summarize')->monthlyOn(1, '00:00');

        // Khi đăng ký "nghỉ phép năm" không trừ ngày phép liền, mà qua ngày nghỉ phép mới trừ
        $schedule->command('leave-management:decrease')->dailyAt('00:00');

        // Cập nhật giờ phép năm/tháng
        $schedule->command('leave-management:increase')->dailyAt('00:00');

        // Reset các loại giờ phép vào 1/1 hàng năm
        $schedule->command('leave-management:generate-category-balance')->yearlyOn(1, 1, '00:00');

        // Di chuyển giờ phép
        $schedule->command('leave-management:carry-forward-entitlement')->yearlyOn(12, 31, '23:59');

        /**
         * Hanet
         */
        // Phục hồi các record hanet đã mất trong buổi sáng 12:00
        $schedule->command('hanet:recover')->dailyAt('05:00');
        // Phục hồi các record hanet đã mất trong buổi chiều 18:00
        $schedule->command('hanet:recover')->dailyAt('11:00');
        // Phục hồi các record hanet đã mất trong ngày hôm qua 01:00
        $schedule->command('hanet:recover --yesterday')->dailyAt('18:00');

        // Check token expire and extend of Hanet
        $schedule->command('hanet:renewtoken')->dailyAt('00:00');

        // Debit request
        $schedule->command('debit:request')->hourly();

        // Remind Contract
        $schedule->command('remind:contract')->dailyAt('06:00')->timezone('Asia/Ho_Chi_Minh');

        $schedule->command('command:implementSalaryUpdate')->timezone('Asia/Ho_Chi_Minh')->dailyAt('00:00')->evenInMaintenanceMode();

        // Export data for power bi processing
        // 20:00 VN -> Power Bi pickup data lúc 22:00
        $schedule->command('powerbi:export')->dailyAt('20:00')->timezone('Asia/Ho_Chi_Minh');;

        // Create overtime by the end of the year
        $schedule->command('command:createOvertimeCategoryEveryYear')->dailyAt('03:00')->timezone('Asia/Ho_Chi_Minh');

        //Remove checking data
        $schedule->command('checkingData:remove')->monthlyOn(1, '00:00');

        // Check stuck calculation sheet
        $schedule->command('check:stuck-calculation-sheet')->everyMinute();

        //Notify web version
        $schedule->command('notify:web-version')->dailyAt('04:00')->timezone('Asia/Ho_Chi_Minh');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
