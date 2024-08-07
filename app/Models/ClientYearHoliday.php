<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\User;
use Illuminate\Support\Facades\Auth;

/**
 * @property string $id
 * @property string $date
 * @property string $client_id
 * @property string $created_at
 * @property string $updated_at
 */
class ClientYearHoliday extends Model
{
    use Concerns\UsesUuid;

    protected $table = 'client_year_holidays';

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var array
     */
    protected $fillable = ['date', 'client_id', 'name','group_id'];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function getStartDateAttribute() {
        $startDate = $this->date;
        if(!is_null($this->group_id)) {
            $result =  ClientYearHoliday::where('group_id', $this->group_id)->orderBy('date','asc')->get();
            if(count($result) > 0) {
                $startDate = $result[0]->date;
            }
        }

        return $startDate;
    }
    public function getEndDateAttribute()
    {
        $endDate = $this->date;
        if(!is_null($this->group_id)) {
            $result =  ClientYearHoliday::where('group_id', $this->group_id)->orderBy('date','desc')->get();
            if(count($result) > 0) {
                $endDate = $result[0]->date;
            }
        }
        return $endDate;
    }
    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        /** @var User */
        $user = Auth::user();

        return !$user->isInternalUser();
    }
}
