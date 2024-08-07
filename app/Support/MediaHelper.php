<?php


namespace App\Support;

use App\Models\Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaHelper
{
    public static function getPublicTemporaryUrl(string $pathFile)
    {
        $mediaTemporaryTime = config('app.media_temporary_time', 5);
        $endpointUrl = config("filesystems.disks.minio.endpoint");
        $publicUrl   = config("filesystems.disks.minio.url");

        $url = Storage::temporaryUrl(
            $pathFile, now()->addMinutes($mediaTemporaryTime)
        );
        return str_replace($endpointUrl, $publicUrl, $url);
    }
}
