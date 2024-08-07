<?php

namespace App\GraphQL\Mutations;

use App\Models\Approve;
use Illuminate\Support\Carbon;

class RejectApproveMutator
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        $id = $args['id'];
        $approve = Approve::query()->where('id', $id)->first();
        if ($approve) {
            $comment = $args['comment'] ? $args['comment'] : '';
            $approve->declined_at = Carbon::now()->format('Y-m-d H:i:s');
            $approve->approved_comment = $comment;
            $approve->source = isset($args['source']) ?? $args['source'];
            $approve->save();
        }
        return $approve;
    }
}
