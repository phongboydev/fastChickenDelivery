<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthenticationException;
use Illuminate\Http\Request;
use Joselfonseca\LighthouseGraphQLPassport\GraphQL\Mutations\BaseAuthResolver;

class BasePolicy extends BaseAuthResolver
{
    use HandlesAuthorization;
    public $user;

    public function __construct()
    {
        logger("BasePolicy::__construct BEGIN");
        // TODO Không cần check authentication ở đây
        $user = Auth::check();
        if (!$user) {
            logger("BasePolicy::__construct Unauthorized. User not logged in");
            throw new AuthenticationException('You are not authorized to access.');
        }
        logger("BasePolicy::__construct END");
    }
}
