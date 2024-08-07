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
use App\Mail\CustomerResetPasswordEmail;

class SendCustomerResetPasswordEmail implements ShouldQueue
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
        Client $client,
        User $user,
        ClientEmployee $clientEmployee,
        string $password
    )
    {  
        $this->client = $client;
        $this->user = $user;
        $this->clientEmployee = $clientEmployee;
        $this->password = $password;
    }

    public function handle()
    {
        $client = $this->client;
        $clientEmployee = $this->clientEmployee;
        $user = $this->user;
        $password = $this->password;

        $username = Str::replaceFirst($user->client_id . '_', '', $user->username);

        $this->addLog("TK: {$username}, Code: [{$clientEmployee->code}], Email: {$user->email} - Email kích hoạt - Đang chuẩn bị gửi");

        if (!filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            $this->addLog("TK: {$username}, Code: [{$clientEmployee->code}], Email: {$user->email} - Email kích hoạt - Gửi không thành công, email không hợp lệ");
            return;
        }

        try {
            Mail::to($user->email)->send( new CustomerResetPasswordEmail($client, $user, $clientEmployee, $password));
            $this->addLog("TK: {$username}, Code: [{$clientEmployee->code}], Email: {$user->email} - Email kích hoạt - Đã gửi thành công");
        } catch (\Throwable $th) {
            $this->addLog("TK: {$username}, Code: [{$clientEmployee->code}], Email: {$user->email} - Email kích hoạt - Gửi không thành công");
            throw $th;
        }

    }

    protected function addLog($log)
    {
        $this->client->addLog('customer_reset_password_email', $log);
        
    }
}
