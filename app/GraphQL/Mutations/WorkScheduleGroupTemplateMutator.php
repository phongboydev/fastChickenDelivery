<?php

namespace App\GraphQL\Mutations;

use App\Models\WorkScheduleGroupTemplate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class WorkScheduleGroupTemplateMutator
{
    public function getLongestScheduleGroupTemplate($root, array $args)
    {
        $now = Carbon::now()->toDateString();
        $user = Auth::user();
        $client_id = !$user->isInternalUser() ? $user->client_id : $args['client_id'] ?? '';
        $wsgt = WorkScheduleGroupTemplate::with('workScheduleGroup')
            ->where("client_id", $client_id)
            ->whereHas('workScheduleGroup', function($q) use($now) {
                $q->whereDate('timesheet_to', '>=', $now);
            })
            ->get();
        return $wsgt->reduce(function ($carry, $item) {
            if (!$carry || $carry->workScheduleGroup->count() < $item->workScheduleGroup->count()) {
                return $item;
            } else {
                return $carry;
            }
        });
    }
}
