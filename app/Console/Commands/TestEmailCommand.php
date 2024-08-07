<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Mail\Message;
use Mail;

class TestEmailCommand extends Command
{

    protected $signature = 'test:email';

    protected $description = 'Command description';

    public function handle()
    {
        Mail::send([], [], function (Message $message) {
            $message->to("nhut.tran@tmnsolutions.vn");
            $message->setSubject("Hello world");
            $message->setBody("Hello world");
            $message->from("send@vina-payroll.com");
        });
    }
}
