<?php

namespace App\DTO;

use Spatie\DataTransferObject\DataTransferObject;

class DepartmentSalarySummary extends DataTransferObject
{

    public $department;
    public $position;
    public $salary;

}
