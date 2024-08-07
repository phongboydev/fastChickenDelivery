<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use App\Support\Constant;
use App\Support\MediaHelper;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class PowerBiReport extends Model implements HasMedia
{
    use InteractsWithMedia, UsesUuid;

    public function getReportFileUrlAttribute(): string
    {
        $media = $this->getFirstMedia();
        return $media ? MediaHelper::getPublicTemporaryUrl($media->getPath()) : "";
    }

    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        /** @var User $user */
        $user = Auth::user();
        $role = $user->getRole();

        if (!$user->isInternalUser()) {
            switch ($role) {
                default:
                    return $query->where('client_id', '=', $user->client_id);
            }
        } else {
            if($role == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return $query;
            }else{
                return $query->assignedTo($user->iGlocalEmployee->id);
            }
        }
    }
}
