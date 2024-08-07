<?php

namespace App\GraphQL\Mutations;

use App\Models\TaxOfficeDistrict;
use App\Models\TaxOfficeProvince;
use App\Models\TaxOfficeWard;

class TaxOfficeMutator
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        // TODO implement the resolver

    }

    public function getTaxOfficeByProvince($root, array $args)
    {
        return TaxOfficeProvince::orderBy('tax_office_code')->get();
    }

    public function getTaxOfficeByDistrict($root, array $args)
    {
        return TaxOfficeDistrict::where(
            [
                'tax_office_province_id' => $args['parent_id']
            ]
        )->whereNotIn('tax_office_code', ['0'])->orderBy('tax_office_code')->get();
    }

    public function listOfAdministrativeDivisions($root, array $args)
    {
        switch ($args['level']) {
            case 'province':
                return TaxOfficeProvince::where('is_administrative_division', TRUE)
                    ->orderBy('administrative_division_code')
                    ->get();
                break;
            case 'district':
                return TaxOfficeDistrict::where([
                    'tax_office_province_id' => $args['parent_id'],
                    'is_administrative_division' => TRUE
                ])
                    ->whereNotIn('administrative_division_code', ['0'])
                    ->orderBy('administrative_division_code')
                    ->get();
                break;
            case 'ward':
                return TaxOfficeWard::where([
                    'tax_office_district_id' => $args['parent_id'],
                    'is_administrative_division' => TRUE
                ])
                    ->whereNotIn('administrative_division_code', ['0'])
                    ->orderBy('administrative_division_code')
                    ->get();
                break;
        }
    }
}
