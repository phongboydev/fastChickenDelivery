<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\UsesUuid;
use App\Support\MediaTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class DebitRequest extends Model implements HasMedia
{
    use UsesUuid;
    use InteractsWithMedia;
    use MediaTrait;

    protected $fillable = array(
        'client_id',
        'due_date',
        'debit_amount',
        'adjusted_debit_amount',
        'status',
        'debit_amount_received',
        'date_received',
        'cutoff_date',
        'current_debit_amount',
    );

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
    
    public function getMediaModel() { 
        return $this->getMedia('DebitRequest');
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
