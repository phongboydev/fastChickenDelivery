<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessScheduleMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $target;
    private string $target_id;
    private string $callback;
    private array $params;

    /**
     * Create a new job instance.
     *
     * @param string $target class name
     * @param string $target_id instant id
     * @param string $callback function
     * @param array $params
     *
     * @return void
     */
    public function __construct(string $target, string $target_id, string $callback, array $params = [])
    {
        $this->target = $target;
        $this->target_id = $target_id;
        $this->callback = $callback;
        $this->params = $params;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $target = $this->target::find($this->target_id);
        if ($target)
        {
            if (method_exists($target, $this->callback))
            {
                $method = $this->callback;
                return $target->$method($this->params);
            }
            else
            {
                return null;
            }
        }
    }
}
