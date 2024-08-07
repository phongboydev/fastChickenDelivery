<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class TaxOfficeWard extends Model
{
    use HasFactory, HasTranslations;

    protected $table = 'tax_office_wards';
    public $timestamps = true;
    public $translatable = ['administrative_division'];

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    protected $fillable = [
        'tax_office_district_id', 'administrative_division_code', 'administrative_division', 'administrative_division_active', 'is_administrative_division'
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'tax_office_name_with_code',
    ];

    public function getAdministrativeDivisionNameWithCodeAttribute()
    {
        return $this->administrative_division_code . ' - ' . $this->administrative_division;
    }
}
