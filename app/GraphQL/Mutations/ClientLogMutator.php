<?php

namespace App\GraphQL\Mutations;

use App\Models\ClientLog;

class ClientLogMutator
{
    /**
     * Upload a file, store it on the server and return the path.
     *
     * @param  mixed $root
     * @param  mixed[] $args
     * @return string|null
     */
    public function viewAll($root, array $args)
    {
        $page = $args['page'];
        $perPage = 1000;

        $logs = ClientLog::select('*')->where('client_id', '=', $args['client_id'])
            ->orderBy('created_at', 'DESC')
            ->paginate($perPage, ['*'], 'page', $page);

        $text = '';

        if( !empty($logs) ) {
            foreach($logs as $index => $log) {
                $text .= $log->log_content . '\n';
            }
        }

        return [
            'data'       => $text,
            'pagination' => [
                'total'        => $logs->total(),
                'count'        => $logs->count(),
                'per_page'     => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'total_pages'  => $logs->lastPage()
            ],
        ];
    }

    public function clearAll($root, array $args)
    {
        ClientLog::where('client_id', '=', $args['client_id'])->delete();

        return 'ok';
    }
}
