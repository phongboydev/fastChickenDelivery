<?php

namespace App\Jobs;

use App\User;
use App\Models\CalculationSheet;
use App\Models\CalculationSheetVariable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Message;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Notifications\CalculationSheetDoubleVariableNotification;

class EmailAdminCalculationSheetDoubleVariable implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $calculationSheet;

    public function __construct(
      CalculationSheet $calculationSheet
    )
    {  
        $this->calculationSheet = $calculationSheet;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
      $calculationSheetId = $this->calculationSheet->id;

      $calculationSheetVariables = CalculationSheetVariable::selectRaw('variable_name, client_employee_id')
            ->groupBy(['variable_name', 'client_employee_id'])
            ->havingRaw('COUNT(id) > 1')
            ->where('calculation_sheet_id', $calculationSheetId)->get();

      if($calculationSheetVariables->isNotEmpty()) {

        $userAdmin = User::where('id', 'f2c03eb2-3c83-4d24-8e70-ceac4730ac82')->first();

        if (!empty($userAdmin))
          $userAdmin->notify(new CalculationSheetDoubleVariableNotification($this->calculationSheet));
      }
    }

    protected function addLog($log)
    {
        $this->client->addLog('activate_email', $log);
        
    }
}
