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
use App\Models\Translation;
use Illuminate\Support\Facades\Auth;
use App\User;
use App\Support\Constant;
use App\Exceptions\CustomException;

class TranslationMutator
{
    public function getPaginate($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {

        $order      = isset($args['order']) ? $args['order'] : 'ASC';
        $filterCode = isset($args['filterCode']) ? trim($args['filterCode']) : false;
        
        $perpage = isset($args['perPage']) ? $args['perPage'] : 10;
        $page = isset($args['page']) ? $args['page'] : '1';
        
        $query = Translation::select(['translatable_id AS id', 'translatable_id'])->orderBy('translatable_id', $order);
        
        if( isset($args['filterCode']) && $args['filterCode'] ) {
            $query->where('translatable_id', '=', $args['filterCode']);
        }

        $query->groupBy('translatable_id');

        $items = $query->paginate($perpage, ['translatable_id'], 'page', $page);

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

    public function find($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $query = Translation::select('*')->where('translatable_id', '=', $args['id']);

        return $query->get();
    }

    public function delete($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {

        return Translation::where('translatable_id', '=', $args['id'])->delete();
    }

    public function translations($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $query = Translation::select('*');

        if(isset($args['locale'])){
            $query->where('language_id', '=', $args['locale']);
        }

        return $query->get();
    }

}
