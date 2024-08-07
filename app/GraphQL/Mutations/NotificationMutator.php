<?php

namespace App\GraphQL\Mutations;

use Exception;
use App\Http\Controllers\Support\TemporaryMediaTrait;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;
use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\Paginator;

class NotificationMutator
{
    public function getNotifications($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {

        $user = Auth::user();

        $page = (isset($args['page'])) ? $args['page'] : 1;
        $unreadOnly = (isset($args['unread'])) ? $args['unread'] : 0;
        $limit = (isset($args['limit'])) ? $args['limit'] : 0;
        $type = !empty($args['type_notification']) ? $args['type_notification'] : '';
        $clientId = !empty($args['client_id']) ? $args['client_id'] : '';

        $pagination = $this->getData($unreadOnly, $limit, $page, $type)->toArray();

        $pagination['data'] = collect(array_map(function ($item) {
            $processed = $item['data'];
            $processed['created_at'] = $item['created_at'];
            $processed['id'] = $item['id'];
            return $processed;
        }, $pagination['data']))->sortBy('created_at');


        if (!empty($clientId)) {
            $pagination['data'] = $pagination['data']->where('client_id', $clientId);
        }
        $pagination['data'] = $pagination['data']->toArray();
         if (!empty($type) && !empty($clientId)) {
                $pagination['unread_total'] = count($pagination['data']);
            } else {
                $pagination['unread_total'] = $user->unreadNotifications()->count();
            }
        return json_encode($pagination);
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    protected function getData($unreadOnly, $limit, $page = 1, $type = '')
    {
        $user = Auth::user();

        if ($unreadOnly == 1) {
            $query = $user->unreadNotifications();

        } else {
            $query = $user->notifications();
        }

        if (!empty($type)) {
            $query = $query->where('type', 'App\Notifications\\' . $type);
        }

        if ( $limit == 0 ) {
            $limit   =   $query->count();
       }

        return $query->paginate($limit, ['*'], 'page', $page);
    }

    /**
     *
     */
    public function markRead($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::user();

        if( !empty($user->unreadNotifications) ) {

            if( isset($args['id']) ) {
                foreach ($user->unreadNotifications as $notification) {
                    if($notification->id == $args['id']) {
                        $notification->markAsRead();
                    }
                }
            }else{
                if (isset($args['ids'])) {
                     $user->unreadNotifications()->whereIn('id', $args['ids'])->update(['read_at' => now()]);
                } else {
                    $user->unreadNotifications()->update(['read_at' => now()]);
                }

            }
        }

        return json_encode(['state' => 'ok']);
    }
}
