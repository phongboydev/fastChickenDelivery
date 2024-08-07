<?php

namespace App\GraphQL\Mutations;

use App\Models\ClientPayrollHeadCount;
use App\Models\HeadcountPeriodSetting;
use App\Support\Constant;
use Carbon\Carbon;

class ClientPayrollHeadcountMutator
{
    public function getPayrollHeadcountCost($root, array $args)
    {
        $month = $args['month'];
        $year = $args['year'];
        $client_id = $args['client_id'] ?? '';
        $date = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        $clientPayrollHeadcount = ClientPayrollHeadCount::select([
            'total',
            'client_id'
            ])->with(['client' => function($query) {
                $query->withTrashed();
            }])
            ->authUserAccessible()
            ->where('month', $month)
            ->where('year', $year);
        if (!empty($client_id)) {
            $clientPayrollHeadcount = $clientPayrollHeadcount->where('client_id', $client_id);
        }
        $clientPayrollHeadcount = $clientPayrollHeadcount->get();
        if($clientPayrollHeadcount->isEmpty()) return;

        //VPO settings
        $headcountVPOSetting = HeadcountPeriodSetting::with('headcountCostSetting')
            ->where('from_date', '<=', $date)
            ->where('to_date', '>=', $date)
            ->where('client_id', Constant::INTERNAL_DUMMY_CLIENT_ID)
            ->first();
        if(!$headcountVPOSetting) return;

        //client settings
        $headcountSetting = HeadcountPeriodSetting::with('headcountCostSetting')
            ->authUserAccessible()
            ->where('from_date', '<=', $date)
            ->where('to_date', '>=', $date)
            ->where('client_id', '!=', Constant::INTERNAL_DUMMY_CLIENT_ID);
        if (!empty($client_id)) {
            $headcountSetting = $headcountSetting->where('client_id', $client_id);
        }
        $headcountSetting = $headcountSetting->get()->keyBy('client_id');

        $clientPayrollHeadcount->each(function ($item, $key) use($headcountSetting, $headcountVPOSetting) {
            $item->company_name = $item->client->company_name ?? "";
            if ($headcountSetting->has($item->client_id)) {
                $total = $item->total;
                $hs = $headcountSetting->get($item->client_id);
                $item->cost = ($hs->headcountCostSetting->first(function ($value, $key) use($total) {
                    return ($value->min_range <= $total
                        && ($value->max_range >= $total || empty($value->max_range))
                    );
                })->cost ?? 0) * $total;
            } else {
                $total = $item->total;
                $item->cost = ($headcountVPOSetting->headcountCostSetting->first(function ($value, $key) use($total) {
                    return ($value->min_range <= $total
                        && ($value->max_range >= $total || empty($value->max_range))
                    );
                })->cost ?? 0) * $total;
            }
        });

        return $clientPayrollHeadcount;
    }
}
