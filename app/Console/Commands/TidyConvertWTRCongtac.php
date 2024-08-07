<?php

namespace App\Console\Commands;

use App\Models\WorktimeRegister;
use App\Models\Approve;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class TidyConvertWTRCongtac extends Command
{

    protected $signature = 'tidy:convert_wtr_congtac';

    protected $description = 'Convert wtr leave_request sang công tác';

    public function handle()
    {
        $items = [
            ['from' => 'leave_request,outside_working', 'to' => 'congtac_request,outside_working'],
            ['from' => 'leave_request,wfh ', 'to' => 'congtac_request,wfh'],
        ];

        foreach ($items as $item) {
            $fromTypes = explode(',', $item['from']);
            $toTypes = explode(',', $item['to']);

            WorktimeRegister::select('*')->where('type', $fromTypes[0])->where('sub_type', $fromTypes[1])
                ->chunkById(100, function ($fromItems) use ($toTypes) {
                    foreach ($fromItems as $fromItem) {

                        $this->line("Converting ... " . $fromItem->code);

                        WorktimeRegister::where('id', $fromItem->id)->update([
                            'type' => $toTypes[0],
                            'sub_type' => $toTypes[1]
                        ]);

                        Approve::where('target_id', $fromItem->id)->update([
                            'type' => 'CLIENT_REQUEST_CONG_TAC'
                        ]);
                    }
                }, 'id');
        }
    }
}
