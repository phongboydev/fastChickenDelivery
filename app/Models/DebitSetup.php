<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\UsesUuid;
use App\Models\IglocalAssignment;
use App\Support\Constant;

class DebitSetup extends Model
{
    use UsesUuid;

    protected $fillable = array(
        'client_id',
        'debit_date',
        'due_date',
        'due_month',
        'cutoff_date',
        'cutoff_month',
        'current_debit_amount',
        'salary_amount_need_to_pay',
        'debit_threshold_request',
        'debit_threshold_payment',
        'last_run_at',
        'next_run_at',
    );

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function scopeHasInternalAssignment($query)
    {
        $user = auth()->user();

        if (!$user->isInternalUser()) {
            return $query->whereNull('id');
        } else {
            $employee = $user->iGlocalEmployee;

            if ($employee->role == Constant::ROLE_INTERNAL_DIRECTOR || $employee->role == Constant::ROLE_INTERNAL_ACCOUNTANT) {
                return $query;
            }

            $assignedClientIds = IglocalAssignment::where('iglocal_employee_id', $employee->id)->pluck('client_id')->toArray(); 

            return $query->whereIn('client_id', $assignedClientIds);
        }
    }
}
