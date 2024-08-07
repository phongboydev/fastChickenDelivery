<?php

namespace App\Jobs;

use App\Models\ClientDepartment;
use App\Models\ClientEmployee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UpdateClientDepartment implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $args;

    /**
     * Create a new job instance.
     *
     * @param SurveyJob           $job
     * @param SurveyJobSubmission $submission
     * @param array               $subjects
     * @param array               $htmls
     * @param string|null         $emailOverride
     */
    public function __construct($args)
    {

        $this->args = $args;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        $args = $this->args;

        $clientId = $args['client_id'];

        $clientEmployeeDepartment = ClientEmployee::select(['client_id', 'department', 'position','id'])
        ->where('client_id', $clientId)
        ->whereNotIn('id', DB::table('client_department_employees')->where('client_id', $clientId)->pluck('client_employee_id'))
        ->distinct()
        ->get()
        ->toArray();

        if(empty($clientEmployeeDepartment)) return;

        $datetime = Carbon::now()->format('Y-m-d H:i:s');

        foreach($clientEmployeeDepartment as &$item) {
            $item['id'] = Str::uuid();
            $item['code'] = '00000';
            $item['created_at'] = $datetime;
            $item['updated_at'] = $datetime;
        }

        // ClientDepartment::insert($clientEmployeeDepartment);
    }
}
