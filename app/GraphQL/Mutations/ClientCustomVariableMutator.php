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
use App\Models\ClientCustomVariable AS ClientCustomVariable;
use Illuminate\Support\Facades\Auth;
use App\User;
use App\Support\Constant;
use App\Exceptions\CustomException;

class ClientCustomVariableMutator

{
    public function update($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::user();

        $id = (isset($args['id'])) ? $args['id'] : '';
        $clientId = (isset($args['filter_client_id'])) ? $args['filter_client_id'] : '';
        // Neu set id thi ko quan tam cac filter con lai
        if (isset($args['id'])) {
            $clientId = null;
        }

        $clientCustomVariables = ClientCustomVariable::select('*')
            ->where(function ($query) use ($id) {
                if ($id) {
                    $query->where('id', '=', $id);
                }
            })
            ->where(function ($query) use ($clientId) {
                if ($clientId) {
                    $query->where('client_id', '=', $clientId);
                }
            })
            ->get()->toArray();

        $modelUpdate = array();
        if ($clientCustomVariables) {
            DB::transaction(function () use ($args, $clientCustomVariables, &$modelUpdate, $user) {
                foreach ($clientCustomVariables as $clientCustomVariable) {
                    // Get model instance
                    $model = ClientCustomVariable::findOrFail($clientCustomVariable['id']);
                    $model->readable_name = $args['readable_name'];
                    $model->variable_name = $args['variable_name'];
                    $model->scope = $args['scope'];
                    $model->variable_value = (isset($args['variable_value'])) ? $args['variable_value'] : 0;
                    $model->sort_order = (isset($args['sort_order'])) ? $args['sort_order'] : 1;
                    $model->is_visible = $args['is_visible'];

                    if (isset($args['id'])) {
                        $model->client_id = $args['client_id'];
                    }

                    if ($user->can('update', $model)) {
                        // Save model
                        $model->saveOrFail();
                        array_push($modelUpdate, $model);
                    } else {
                        throw new CustomException(
                            'You are not authorized to access updateClientCustomVariable.',
                            'AuthorizedException'
                        );
                    }
                }
            });
        }

        return $modelUpdate;
    }

    public function delete($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::user();

        $id = (isset($args['id'])) ? $args['id'] : '';
        $clientId = (isset($args['filter_client_id'])) ? $args['filter_client_id'] : '';
        // Neu set id thi ko quan tam cac filter con lai
        if (isset($args['id'])) {
            $clientId = null;
        }

        $clientCustomVariables = ClientCustomVariable::select('*')
            ->where(function ($query) use ($id) {
                if ($id) {
                    $query->where('id', '=', $id);
                }
            })
            ->where(function ($query) use ($clientId) {
                if ($clientId) {
                    $query->where('client_id', '=', $clientId);
                }
            })
            ->get()->toArray();

        $modelDelete = array();
        if ($clientCustomVariables) {
            DB::transaction(function () use ($args, $clientCustomVariables, &$modelDelete, $user) {
                foreach ($clientCustomVariables as $clientCustomVariable) {
                    // Get model instance
                    $model = ClientCustomVariable::findOrFail($clientCustomVariable['id']);

                    if ($user->can('delete', $model)) {
                        // Delete model
                        $model->delete();
                        array_push($modelDelete, $model);
                    } else {
                        throw new CustomException(
                            'You are not authorized to access deleteClientCustomVariable.',
                            'AuthorizedException'
                        );
                    }
                }
            });
        }

        return $modelDelete;
    }

    public function getAllVariables($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $filter_client_id = isset($args['filter_client_id']) ? $args['filter_client_id'] : false;
        $filter_scope = isset($args['filter_scope']) ? $args['filter_scope'] : false;

        $query = ClientCustomVariable::select('*');

        if( $filter_client_id ) {
            $query->where('client_id', $filter_client_id);
        }

        if( $filter_scope ) {
            $query->where('scope', $filter_scope);
        }

        return $query->get();
    }
}
