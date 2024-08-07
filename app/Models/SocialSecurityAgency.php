<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\UsesUuid;
use Spatie\Translatable\HasTranslations;

class SocialSecurityAgency extends Model
{
    use UsesUuid, HasTranslations;

    protected static $logAttributes = ['*'];

    protected $table = 'social_security_agencies';

    protected $guarded = [];

    public $timestamps = true;

    public $translatable = ['name'];

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
    protected $fillable = ['province_id', 'code', 'name'];

    public function scopeAuthUserAccessible($query)
    {
        return $query;
    }

    public function getTranslationsAttribute()
    {
        return $this->getTranslations('name');
    }

    public function getVietnameseVersionAttribute()
    {
        return $this->getTranslation('name', 'vi');
    }

    public function getEnglishVersionAttribute()
    {
        return $this->getTranslation('name', 'en');
    }

    public function getJapaneseVersionAttribute()
    {
        return $this->getTranslation('name', 'ja');
    }

    public function province()
    {
        return $this->belongsTo(Province::class);
    }
}
