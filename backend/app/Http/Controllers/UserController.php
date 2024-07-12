<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\ResponseTraits;
use Illuminate\Routing\Controller;

class UserController extends Controller
{
    use  ResponseTraits;

    public function index()
    {
        $request = request()->all();

        $users = User::query();
        // Get param by condition
        // Role
        if(isset($request['role'])) {
            $users->where('role', $request['role']);
        }

        // Search
        if(isset($request['q'])) {
            $users->where('full_name', 'like', '%'.$request['q'].'%');
        }

        // Status
        if(isset($request['status'])) {
            $users->where('status', $request['status']);
        }

        // Plan
        if(isset($request['plan'])) {
            $users->where('current_plan', $request['plan']);
        }

        // Sort
        if(isset($request['sort'])) {
            $users->orderBy($request['sortBy'], $request['orderBy']);
        }

        // Pagination
        $users = $users->paginate($request['itemsPerPage'], ['*'], 'page', $request['page']);

        return $this->responseData(200, 'Success', $users);
    }
}
