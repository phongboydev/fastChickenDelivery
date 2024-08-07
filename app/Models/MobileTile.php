<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;

class MobileTile extends Model
{
    use UsesUuid;

    public $timestamps = false;
    protected $fillable = array(
        'row', 'col', 'tile_url', 'use_webview', 'icon_url', 'name'
    );
}
