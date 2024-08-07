<?php

namespace App\GraphQL\Queries;
use App\Models\JobboardApplication;

class JobboardApplicationDuplicates
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        // Get all job board applications that have the same email or same  phone
        $applicationDuplicates = JobboardApplication::groupBy('appliant_email', 'appliant_tel','jobboard_job_id')
                                ->havingRaw('COUNT(*) > 1')
                                ->where('client_id', $args['client_id'])
                                ->with('jobboardJob')
                                ->get();
       return $applicationDuplicates;
    }
}
