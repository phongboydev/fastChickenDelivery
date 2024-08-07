<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Support\MediaTrait;

class TimeSheetEmployeeImport extends Model implements HasMedia
{
    use Concerns\UsesUuid;
    use InteractsWithMedia;
    use MediaTrait;
    use HasFactory;

    public $timestamps = true;

    public $incrementing = false;
    protected $table = 'time_sheet_employee_imports';

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'path',
        'status',
        'user_id',
        'from_date',
        'to_date',
        'note',
        'status'
    ];

    public function getMediaModel()
    {
        return $this->getMedia('TimeSheetEmployeeImport');
    }
}
