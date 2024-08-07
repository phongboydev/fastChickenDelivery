<?php

namespace App\Models;

use App\Models\Concerns\HasPdfMedia;
use App\Models\Concerns\UsesUuid;
use App\Support\MediaTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ContractSignStep extends Model implements HasMedia
{

    use UsesUuid, HasPdfMedia, InteractsWithMedia, MediaTrait;

    public $fillable = [
        "step",
        "sign_area_enabled",
        "page_no",
        "sign_x",
        "sign_y",
        "sign_w",
        "sign_h",
        "signed_at"
    ];

    public $casts = [
        "signed_at" => "datetime"
    ];

    public function getMediaModel(): Collection
    {
        return $this->getMedia($this->getPdfCollectionName());
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function registerMediaCollections(): void
    {
        $this->registerPdfCollection();
    }
}
