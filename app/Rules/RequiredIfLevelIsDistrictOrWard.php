<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class RequiredIfLevelIsDistrictOrWard implements Rule
{
    protected $levelValues = ['district', 'ward'];

    public function passes($attribute, $value)
    {
        $level = request()->input('level');

        if (in_array($level, $this->levelValues)) {
            return !empty($value);
        }

        // If the level is not "district" or "ward", the parent_id is not required
        return true;
    }

    public function message()
    {
        return 'The :attribute field is required when level is district or ward.';
    }
}
