<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class TaxOfficeProvince extends Model
{
    use HasFactory, HasTranslations;

    protected $table = 'tax_office_provinces';
    public $timestamps = true;
    public $translatable = ['tax_office', 'administrative_division'];

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    protected $fillable = [
        'administrative_division_code', 'tax_office_code', 'tax_office', 'administrative_division', 'tax_office_active',
        'administrative_division_active', 'is_administrative_division'
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'tax_office_name_with_code', 'administrative_division_name_with_code'
    ];

    public function getTaxOfficeNameWithCodeAttribute()
    {
        return $this->tax_office_code . ' - ' . $this->tax_office;
    }

    public function getAdministrativeDivisionNameWithCodeAttribute()
    {
        return $this->administrative_division_code . ' - ' . $this->administrative_division;
    }

    public function taxOfficeDistrict()
    {
        return $this->hasMany(TaxOfficeDistrict::class);
    }
}
