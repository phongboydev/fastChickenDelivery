<?php

namespace App\Models\Concerns;

use App\Support\MediaHelper;
use Illuminate\Database\Eloquent\Builder;
use Spatie\MediaLibrary\InteractsWithMedia;

trait HasPdfMedia
{

    public function getPdfCollectionName(): string
    {
        return "pdf";
    }

    use InteractsWithMedia;

    public function scopeNotHasPdf(Builder $query)
    {
        $query->whereDoesntHave("media", function(Builder $subQuery) {
            $subQuery->where("collection_name", $this->getPdfCollectionName());
        });
    }

    public function scopeHasPdf(Builder $query)
    {
        $query->whereHas("media", function(Builder $subQuery) {
            $subQuery->where("collection_name", $this->getPdfCollectionName());
        });
    }

    public function registerPdfCollection(): void
    {
        $this
            ->addMediaCollection($this->getPdfCollectionName())
            ->singleFile();
    }

    public function getPdfPathAttribute(): string
    {
        $media = $this->getFirstMedia('pdf');
        return $media ? MediaHelper::getPublicTemporaryUrl($media->getPath()) : '';
    }
}
