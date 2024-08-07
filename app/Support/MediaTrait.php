<?php

namespace App\Support;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Exception;
trait MediaTrait
{
    abstract public function getMediaModel();

    public function getMediaPathAttribute()
    {
        return env('MINIO_URL') . '/' . env('MINIO_BUCKET') . '/';
    }

    public function getPathAttribute()
    {
        $media = $this->getMediaModel();

        if( count($media) > 0 )
        {
            return $this->getPublicTemporaryUrl($media[0]);
        }else{
            return '';
        }
    }

    public function getAttachmentsAttribute()
    {
        $media = $this->getMediaModel();
        $attachments = [];

        if( count($media) > 0 )
        {
            foreach ($media as $key => $item) {
                $attachments[] = [
                    'path' => $this->getPublicTemporaryUrl($item),
                    'url' => $this->getPublicTemporaryUrl($item),
                    'id' => $item->id,
                    'file_name' => $item->file_name,
                    'name' => $item->name,
                    'mime_type' => $item->mime_type,
                    'collection_name' => $item->collection_name,
                    'created_at' => $item->created_at,
                    'human_readable_size' => $item->human_readable_size,
                    'file_size' => $item->size,
                    'description' => $item->hasCustomProperty('description') ? $item->getCustomProperty('description') : ''
                ];
            }
        }

        return $attachments;
    }

    public function getRelativePathAttribute()
    {
        $media = $this->getMediaModel();

        if( count($media) > 0 )
        {
            return $media[0]->getPath();
        }else{
            return '';
        }
    }

    public function getMediaTempAttribute()
    {
        $media = $this->getMediaModel();

        if( count($media) > 0 ) {
            $results = [];


            foreach($media as $m) {
                /** @var Media $m */
                $m->url = $this->getPublicTemporaryUrl($m);
                $results[] = $m;
            }

            return $results;
        }else{
            return [];
        }
    }

    protected function getPublicTemporaryUrl(Media $media)
    {
        // TODO need more abstract way, instead of hardcode "minio" disk
        $mediaTemporaryTime = config('app.media_temporary_time', 5);
        $endpointUrl = config("filesystems.disks.minio.endpoint");
        $publicUrl   = config("filesystems.disks.minio.url");

        try {
            $url = $media->getTemporaryUrl(Carbon::now()->addMinutes($mediaTemporaryTime));
            return str_replace($endpointUrl, $publicUrl, $url);
        } catch (Exception $e) {
            return '/img/theme/man.png';
        }
    }
}
