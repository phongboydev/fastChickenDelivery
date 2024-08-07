<?php

namespace App\DTO;

use Spatie\DataTransferObject\DataTransferObject;

class ImportResponse extends DataTransferObject
{
    public $ok;
    public $error;
    public $messages;
}