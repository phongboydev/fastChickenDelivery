<?php

namespace App\Console\Commands;

use App\Models\ClientEmployee;
use Illuminate\Support\Facades\Storage;

use Illuminate\Console\Command;

class TidyUpdateAvatarHanet extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tidy:updateavatarhanet';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update field avatar hanet client employee';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $query = ClientEmployee::select('*')->withTrashed();
        $query->chunkById(100, function ($employees) {
            foreach ($employees as $clientEmployee) {
                $mediaHanet = $clientEmployee->getFirstMedia('avatar_hanet');
                if(!$mediaHanet) {
                    $mediaAvatar = $clientEmployee->getFirstMedia('avatar');
                    if($mediaAvatar) {
                        $path = $mediaAvatar->getPath();
                        if(!Storage::missing($path)) {
                            $this->line("Handle employee: " . $clientEmployee->full_name);
                            $clientEmployee->addMediaFromDisk($path, 'minio')
                                ->preservingOriginal()
                                ->storingConversionsOnDisk('minio')
                                ->toMediaCollection('avatar_hanet', 'minio');
                        }
                    }
                }
            }
        }, 'id');
    }
}
