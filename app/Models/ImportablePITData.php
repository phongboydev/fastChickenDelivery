<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $importable_pit_employee_id
 * @property integer $month
 * @property integer $year
 * @property integer $resident_status
 * @property string $taxable_income
 * @property integer $number_of_dependants
 * @property string $deduction_for_dependants
 * @property string $compulsory_social_insurance
 * @property string $assessable_income
 * @property string $pit
 * @property string $created_at
 * @property string $updated_at
 */
class ImportablePITData extends Model
{
    use HasFactory, UsesUuid, HasAssignment;

    protected $table = 'importable_pit_data';

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
        'importable_pit_employee_id',
        'month',
        'year',
        'resident_status',
        'taxable_income',
        'number_of_dependants',
        'deduction_for_dependants',
        'compulsory_social_insurance',
        'assessable_income',
        'pit',
    ];

    /**
     * @return BelongsTo
     */
    public function importablePITEmployee()
    {
        return $this->belongsTo(ImportablePITEmployee::class, 'importable_pit_employee_id');
    }
}
