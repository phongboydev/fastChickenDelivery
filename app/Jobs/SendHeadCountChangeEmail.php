<?php

namespace App\Jobs;

use App\Mail\CustomerHeadCountChangeEmail;
use App\Mail\InternalHeadCountChangeEmail;
use App\Models\Client;
use App\Support\Constant;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class SendHeadCountChangeEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    protected User $user;
    protected array $predefinedConfig;
    protected Client $client;

    public function __construct(User $change_by_user, Client $client, $params)
    {
        $this->user = $change_by_user;
        $this->client = $client;
        $this->predefinedConfig['company_name'] = !empty($client->company_name) ? $client->company_name : '';
        $this->predefinedConfig['old_number'] = !empty($params['old_number']) ? $params['old_number'] : 0;
        $this->predefinedConfig['new_number'] = !empty($params['new_number']) ? $params['new_number'] : 0;
        $this->predefinedConfig['changed_at'] = !empty($params['changed_at']) ? $params['changed_at'] : Carbon::now(Constant::TIMESHEET_TIMEZONE)->toDateTimeString();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->sendToCustomer();
        $this->sendToInternal();

    }

    private function sendToCustomer()
    {
        try {
            Mail::to($this->user->email)->send( new CustomerHeadCountChangeEmail($this->predefinedConfig));
            $this->addLog("Customer HeadCount - Email kích hoạt - Đã gửi thành công");
        } catch (\Throwable $th) {
            $this->addLog("Customer HeadCount - Email kích hoạt - Gửi không thành công");
        }
    }

    private function sendToInternal()
    {
        try {
            $mailToInternalUsers = [];
            $this->client->assignedInternalEmployees()
                ->chunkById(100, function ($internalEmployees) use (&$mailToInternalUsers) {
                    foreach ($internalEmployees as $internalEmployee) {
                        /** @var User $user */
                        $user = $internalEmployee->user;
                        $mailToInternalUsers[] = $user->email;
                    }
                });
            Mail::to($mailToInternalUsers)->send( new InternalHeadCountChangeEmail($this->predefinedConfig));
            $this->addLog("Internal HeadCount - Email kích hoạt - Đã gửi thành công");
        } catch (\Throwable $th) {
            $this->addLog("Internal HeadCount - Email kích hoạt - Gửi không thành công");
        }
    }

    protected function addLog($log)
    {
        $this->client->addLog('head_count_change_email', $log);

    }
}
