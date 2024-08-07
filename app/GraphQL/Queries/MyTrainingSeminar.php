<?php

namespace App\GraphQL\Queries;

use App\Models\ClientEmployeeTrainingSeminar;
use App\Models\TrainingSeminar;

class MyTrainingSeminar
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        $perpage = isset($args['perPage']) ? $args['perPage'] : 10;
        $page = isset($args['page']) ? $args['page'] : '1';

        $items = ClientEmployeeTrainingSeminar::where('client_employee_id', auth()->user()->clientEmployee->id)
            ->paginate($perpage, ['*'], 'page', $page);

        return [
            'data'       => $items,
            'pagination' => [
                'total'        => $items->total(),
                'count'        => $items->count(),
                'per_page'     => $items->perPage(),
                'current_page' => $items->currentPage(),
                'total_pages'  => $items->lastPage()
            ],
        ];
    }
}
