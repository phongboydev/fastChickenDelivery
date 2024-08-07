<?php

namespace App\Support;

use App\Models\Client;
use App\Models\ClientEmployee;

trait ClientNameTrait
{
    protected function getFullname($client)
    {
        if ($client instanceof Client) {
            return $client->company_name;
        }
        if ($client instanceof ClientEmployee) {
            return $client->full_name;
        }
        logger()->warning("Client notification should be applied on either Client or ClientEmployee model");
        return '';
    }
}
