<?php

namespace App\GraphQL\Mutations;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Support\TemporaryMediaTrait;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use App\Models\DebitNote;
use Illuminate\Support\Facades\Auth;
use App\User;
use App\Support\Constant;
use App\Exceptions\CustomException;

class DebitNoteMutator
{
    public function get($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::user();

        $query = DebitNote::authUserAccessible()->offset(0)->limit(60)->orderBy('created_at', 'desc');

        if( $args['filterStatus'] ) {
            $query->where('status', '=', $args['filterStatus']);
        }

        return $query->get();
    }

    public function setPaid($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        DebitNote::authUserAccessible()->where('id', '=', $args['id'])->update(['status' => 'paid']);

        return DebitNote::authUserAccessible()->where('id', '=', $args['id'])->first();

    }
}
