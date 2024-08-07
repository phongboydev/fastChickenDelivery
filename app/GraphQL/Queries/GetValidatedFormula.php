<?php

namespace App\GraphQL\Queries;

use Illuminate\Support\Collection;
use App\Support\ClientHelper;

class GetValidatedFormula
{

    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): Collection
    {
        $clientId = isset($args['client_id']) ? $args['client_id'] : '';
        return $this->handle($clientId);
    }

    /**
     * @param $clientId
     *
     * @return \Illuminate\Support\Collection
     */
    public function handle($clientId): Collection
    {
      return ClientHelper::getValidatedFormulas($clientId);
    }
}
