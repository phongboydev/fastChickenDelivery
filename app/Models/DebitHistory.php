<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\UsesUuid;
use App\Models\DebitSetup;
use App\Support\MediaTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class DebitHistory extends Model implements HasMedia
{
    use UsesUuid;
    use InteractsWithMedia;
    use MediaTrait;

    protected $fillable = array(
        'client_id',
        'updated_by',
        'previous_debit_amount',
        'current_debit_amount',
        'changed_amount',
        'transaction_date',
        'transaction_type',
        'status',
        'note',
    );

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function scopeInternalUser($query) {

        $user = auth()->user();

        if (!$user->isInternalUser()) {
            return $query->whereNull('id');
        } else {
            return $query;
        }
    }

    protected static function boot() {
        parent::boot();
        static::created(function ($debitHistory) {
            $debitSetup = DebitSetup::where('client_id', $debitHistory->client_id)->first();
            if ($debitSetup) {
                $previousDebitAmount = $debitSetup->current_debit_amount;
                $changeAmount = ($debitHistory->transaction_type == 'received' ? 1 : -1) * $debitHistory->changed_amount;
                $currentDebitAmount = $previousDebitAmount + $changeAmount;
                $debitHistory->previous_debit_amount = $previousDebitAmount;
                $debitHistory->current_debit_amount = $currentDebitAmount;
                $debitHistory->save();
            } 
        });

        static::updated(function ($debitHistory) {
            \Log::info("Update");
            \Log::info("Status------------------> $debitHistory->status");
            if ($debitHistory->status == 'completed') {
                $debitSetup = DebitSetup::where('client_id', $debitHistory->client_id)->first();
                if ($debitSetup) {
                    $debitSetup->current_debit_amount = $debitHistory->current_debit_amount;
                    \Log::info("Debit setup------------------> $debitSetup->current_debit_amount");
                    $debitSetup->save();
                }
            }
        });
    }

    public function getMediaModel() { 
        return $this->getMedia('DebitHistory');
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->performOnCollections('images')
              ->width(368)
              ->height(232)
              ->sharpen(10);
    }
}
