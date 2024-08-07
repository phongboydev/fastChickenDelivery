<?php

namespace App\Policies;

use App\Exceptions\HumanErrorException;
use App\Models\HeadcountPeriodSetting;
use App\Support\Constant;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class HeadcountPeriodSettingPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user, array $injected)
    {
        //
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\HeadcountPeriodSetting  $headcountPeriodSetting
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, HeadcountPeriodSetting $headcountPeriodSetting)
    {
        return $this->generateValidation($user, ['client_id' => $headcountPeriodSetting->client_id]);
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user, array $injected)
    {
        return $this->generateValidation($user, $injected)
                && $this->periodValidation($injected)
                && $this->rangeSettingValidation($injected['headcountCostSetting']['create'] ?: []);
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\HeadcountPeriodSetting  $headcountPeriodSetting
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, HeadcountPeriodSetting $headcountPeriodSetting, array $injected)
    {
        return $this->generateValidation($user, $injected)
            && $this->periodValidation($injected, $headcountPeriodSetting->id)
            && $this->rangeSettingValidation($injected['headcountCostSetting']['create'] ?: []);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\HeadcountPeriodSetting  $headcountPeriodSetting
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, HeadcountPeriodSetting $headcountPeriodSetting)
    {
        return $this->generateValidation($user, ['client_id' => $headcountPeriodSetting->client_id]);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\HeadcountPeriodSetting  $headcountPeriodSetting
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, HeadcountPeriodSetting $headcountPeriodSetting)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\HeadcountPeriodSetting  $headcountPeriodSetting
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, HeadcountPeriodSetting $headcountPeriodSetting)
    {
        //
    }

    /**
     * @return \Illuminate\Auth\Access\Response|bool
    */
    private function generateValidation($user, $injected)
    {
        if (!$user->isInternalUser()) return false;

        if (empty($injected['client_id'])) return false;

        if ($injected['client_id'] == Constant::INTERNAL_DUMMY_CLIENT_ID) { // update internal default setting
            if ($user->getRole() != Constant::ROLE_INTERNAL_DIRECTOR) {
                throw new HumanErrorException(__("error.permission"));
            }
        } else { // update client setting.
            if (!$user->iGlocalEmployee->isAssignedFor($injected['client_id'])) {
                throw new HumanErrorException(__("error.permission"));
            }
        }

        return true;
    }

    /**
     * @return \Illuminate\Auth\Access\Response|bool
     */
    private function periodValidation($injected, $ID = '')
    {
        $from = $injected['from_date'];
        $to = $injected['to_date'] ?? "";
        $query = HeadcountPeriodSetting::where('client_id', $injected['client_id'])
            ->where(function ($subQuery) use ($from, $to) {
                $subQuery->whereBetween('from_date', [$from, $to])
                    ->orWhereBetween('to_date', [$from, $to,])
                    ->orWhere(function ($query) use ($from) {
                        $query->where('from_date', '<=', $from)
                            ->where('to_date', '>=', $from);
                    });
            });
        if (!empty($ID)) {
            $query->where('id', '!=', $ID);
        }
        $existedOverlapPeriod = $query->count();
        if ($existedOverlapPeriod) {
            throw new HumanErrorException(__('overlapPeriod'));
        }
        return true;
    }

    private function rangeSettingValidation($headcountCostSettings)
    {
        $blockOn = [];
        $max = 0;
        foreach ($headcountCostSettings as $key => $costSetting) {
            if (empty($costSetting['max_range'])) {
                if (empty($blockOn)) {
                    $blockOn = $costSetting;
                    unset($headcountCostSettings[$key]);
                } else {
                    throw new HumanErrorException(__('have2maxrange'));
                }
            } else if ($costSetting['min_range'] > $costSetting['max_range']) {
                throw new HumanErrorException(__('min_range_max_range'));
            }
            if (!empty($costSetting['max_range']) && $costSetting['max_range'] > $max) {
                $max = $costSetting['max_range'];
            }
        }

        if (!empty($blockOn['min_range']) && $blockOn['min_range'] <= $max)
            throw new HumanErrorException(__('overlapRangeSetting'));

        foreach ($headcountCostSettings as $key => $costSetting) {
            for ($i = $key + 1; $i < count($headcountCostSettings); $i++) {
                if (!( ($costSetting['min_range'] < $headcountCostSettings[$i]['min_range']
                        && $costSetting['max_range'] < $headcountCostSettings[$i]['min_range'])
                    || ($costSetting['min_range'] > $headcountCostSettings[$i]['max_range']
                        && $costSetting['max_range'] > $headcountCostSettings[$i]['max_range'])
                    )) {
                    throw new HumanErrorException(__('overlapRangeSetting'));
                }
            }
        }

        return true;
    }
}
