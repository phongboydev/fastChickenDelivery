<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


/**
 * @property string $id
 * @property string $client_id
 * @property string $full_name
 * @property string $code
 * @property string $tax_code
 * @property string $id_number
 * @property string $created_at
 * @property string $updated_at
 */

class ImportablePITEmployee extends Model
{
    use HasFactory, UsesUuid, HasAssignment;

    protected $table = 'importable_pit_employees';

    public $timestamps = true;

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
    protected $fillable = [
        'client_id',
        'full_name',
        'code',
        'tax_code',
        'id_number',
    ];

    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return HasMany
     */
    public function importablePITData()
    {
        return $this->hasMany(ImportablePITData::class, 'importable_pit_employee_id');
    }
}
