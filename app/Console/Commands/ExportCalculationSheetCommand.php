<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\GraphQL\Mutations\CalculationSheetMutator;

class ExportCalculationSheetCommand extends Command
{

    protected $signature = 'calculationSheet:export {id} {forceExport}';

    protected $description = 'Export calculation sheet';

    public function handle()
    {
        $id = $this->argument("id");
        $forceExport = $this->argument("forceExport");

        $variables = [
            "code",
            "full_name",
            "S_SALARY",
            "F_SI_SALARY",
            "F_UI_SALARY",
            "I_STANDARD_WORK_DAYS",
            "S_WORK_HOURS_OT_HOLIDAY",
            "S_WORK_HOURS_OT_WEEKDAY",
            "I_TOTAL_PAID_LEAVE_HOURS",
            "I_REMAIN_PAID_LEAVE_HOURS",
            "S_WORK_HOURS_OT_WEEKEND",
            "S_NUMBER_OF_DEPENDENTS",
            "F_HOUR_WAGES",
            "I_TIMESHEET_WORK_HOURS",
            "I_OFF_HOURS_WITH_SALARY",
            "I_HOLIDAY_LEAVE",
            "I_WORK_HOURS_OT_ON_WEEKDAYS_FROM_10PM",
            "I_WORK_HOURS_OT_ON_WEEKDAYS_BEFORE_10PM",
            "I_WORK_HOURS_OT_ON_HOLIDAY",
            "I_WORK_HOURS_OT_ON_WEEKEND",
            "F_TOTAL_OT_ALLOWANCE",
            "I_BUSINESS_TRIP_ALLOWANCE",
            "I_PHONE_ALLOWANCE",
            "I_OFF_HOURS_NO_SALARY",
            "I_STUDY_ALLOWANCE",
            "I_TRANSPORTATION_ALLOWANCE",
            "I_BONUS",
            "F_TOTAL_INCOME",
            "F_OT_NOT_PIT",
            "F_TOTAL_TAX_EXEMPTION",
            "F_TAXABLE_INCOME",
            "F_SOCIAL_INSURANCE",
            "F_HEALTH_INSURANCE",
            "F_UNEMPLOYMENT_INSURANCE",
            "F_TOTAL_INSURANCE",
            "F_ALLOWANCE_FOR_DEPENDENTS",
            "F_ASSESSABLE_INCOME",
            "F_PIT_MONTHLY",
            "F_SI_COMPANY",
            "F_HI_COMPANY",
            "F_UI_COMPANY",
            "F_TOTAL_INSURANCE_COMPANY",
            "F_TRADE_UNION",
            "calculated_value"
        ];

        $calculationSheetMutator = new CalculationSheetMutator();

        $results = $calculationSheetMutator->salaryExport([], [
            'id' => $id,
            'variables' => $variables,
            'forceExport' => $forceExport
        ]);

        $results = json_decode($results, true);

        if (!$results['error']) {

            $this->line("Download: " . $results['file']);
        } else {
            $this->line("Error: can not export");
        }
    }
}
