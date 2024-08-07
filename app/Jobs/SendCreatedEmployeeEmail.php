<?php

namespace App\Jobs;

use App\User;
use App\Models\ClientEmployee;
use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Message;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

use App\Mail\AccountCreatedEmail;

class SendCreatedEmployeeEmail implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $client;
    protected $user;
    protected $clientEmployee;
    protected $password;
    protected $htmls;
    protected $subjects;
    protected $email;
    protected $senderEmail;
    protected $senderName;
    protected $language;
    protected $replyEmail;
    protected $emailName = "Generic";

    public function __construct(
        ClientEmployee $clientEmployee
    )
    {  
        $this->clientEmployee = $clientEmployee;
        $this->client = $clientEmployee->client;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $client = $this->client;
        $clientEmployee = $this->clientEmployee;

        if(!empty($clientEmployee->user_id))
        {

            $password = Str::random(10);
            $user = User::find($clientEmployee->user_id);
            $user->password = Hash::make($password);
            $user->update();

            $username = Str::replaceFirst($user->client_id . '_', '', $user->username);

            $this->addLog("TK: {$username}, Code: [{$clientEmployee->code}], Email: {$user->email} - Email kích hoạt - Đang chuẩn bị gửi");

            // Validate e-mail
            if (!filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                $this->addLog("TK: {$username}, Code: [{$clientEmployee->code}], Email: {$user->email} - Email kích hoạt - Gửi không thành công, email không hợp lệ");
                return;
            }

            try {
                Mail::to($user->email)->send( new AccountCreatedEmail($client, $user, $clientEmployee, $password));
                $this->addLog("TK: {$username}, Code: [{$clientEmployee->code}], Email: {$user->email} - Email kích hoạt - Đã gửi thành công");
            } catch (\Throwable $th) {
                $this->addLog("TK: {$username}, Code: [{$clientEmployee->code}], Email: {$user->email} - Email kích hoạt - Gửi không thành công");
            }
        }

    }

    protected function addLog($log)
    {
        $this->client->addLog('activate_email', $log);
        
    }
}
