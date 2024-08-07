<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CustomerHeadCountChangeEmail extends Mailable
{
    use Queueable, SerializesModels;


    protected array $predefinedConfig;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($predefinedConfig)
    {
        $this->predefinedConfig = $predefinedConfig;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.customerHeadCountChange', $this->predefinedConfig);
    }
}
