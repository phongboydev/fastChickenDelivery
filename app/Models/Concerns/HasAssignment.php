<?php

namespace App\Models\Concerns;

use App\Models\IglocalEmployee;
use App\Models\ClientEmployee;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait HasAssignment
 * @package App\Models\Concerns
 * @method belongToClientAssignedTo (IglocalEmployee $employee, string $throughEmployeeRelationName = null)
 */
trait HasAssignment
{

    /**
     * Scope query by internal employee's id who assigned to related Client
     *
     * @param Builder         $query
     * @param IglocalEmployee $employee
     * @param string          $throughEmployeeRelationName
     */
    public function scopeBelongToClientAssignedTo(Builder $query, IglocalEmployee $employee,
                                       string $throughEmployeeRelationName = null
    )
    {
        $internalEmployeeId = $employee->id;
        $clientSubQuery = function($clientEmployee) use ($internalEmployeeId) {
            // This sub-query must be a query of ClientEmployee
            $clientEmployee->whereHas('client', function($client) use ($internalEmployeeId) {
                $client->assignedTo($internalEmployeeId);
            });
        };

        if ($throughEmployeeRelationName) {
            // has assignments through relationship with ClientEmployee
            $query->whereHas($throughEmployeeRelationName, $clientSubQuery);
        } else {
            // has assignments by associated directly with Client
            $clientSubQuery($query);
        }
    }

    public function scopeBelongToClientTo(Builder $query, ClientEmployee $employee)
    {
        $clientId = $employee->client_id;
        $clientSubQuery = function($clientEmployee) use ($clientId) {
            // This sub-query must be a query of ClientEmployee
            $clientEmployee->whereHas('clientEmployee', function($client) use ($clientId) {
                $client->isClientTo($clientId);
            });
        };

        $clientSubQuery($query);
    }
}
