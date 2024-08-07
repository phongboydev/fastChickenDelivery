<?php

namespace App\DTO;

use Spatie\DataTransferObject\DataTransferObject;

class ContractSignResult extends DataTransferObject
{

    public bool $is_done = false;
    public bool $need_plugin_sign = false;
    public string $base64 = "";

}
