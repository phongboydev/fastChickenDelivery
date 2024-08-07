<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AppliedDocumentCategory extends Model
{
    use SoftDeletes;
    use UsesUuid;

    protected $fillable = ['name','name_jp','name_en', 'parent_id'];


    public function getNameCurrentAttribute()
    {
        $user = auth()->user();
        $lang = $user->prefered_language ?? app()->getLocale();
        $value = $this->name;
        if($lang === 'en' && !empty($this->name_en)) {
            $value = $this->name_en;
        }
        if($lang === 'ja' && !empty($this->name_jp)) {
            $value = $this->name_jp;
        }

        return $value;
    }
    public function parent()
    {
        return $this->belongsTo(AppliedDocumentCategory::class, 'parent_id', 'id');
    }

    public function children()
    {
        return $this->hasMany(AppliedDocumentCategory::class, 'parent_id', 'id');
    }

    public static function boot() {
        parent::boot();
        self::deleting(function($model) {
            $model->children()->update(['parent_id' => '']);
        });
    }
}
