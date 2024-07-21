<?php

namespace App\Http\Controllers\Admin;

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

        // get all users not password
        $users->whereNull('password');

        // Sort
        if(isset($request['sortBy'])) {
            if($request['sortBy'] = 'user') $request['sortBy'] = 'id';
            $users->orderBy($request['sortBy'], $request['orderBy']);
        }

        // Pagination
        $users = $users->paginate($request['itemsPerPage'], ['*'], 'page', $request['page']);

        return $this->responseData(200, 'Success', $users);
    }

    public function show($id)
    {
        $user = User::find($id);
        if($user) {
            return $this->responseData(200, 'Success', $user);
        }
        return $this->responseData(404, 'User not found');
    }

    public function update($id)
    {
        $request = request()->all();
        $user = User::find($id);
        if($user) {
            $user->update($request);
            return $this->responseData(200, 'Success', $user);
        }
        return $this->responseData(404, 'User not found');
    }

    public function destroy($id)
    {
        $user = User::find($id);
        if($user) {
            $user->delete();
            return $this->responseData(200, 'Success');
        }
        return $this->responseData(404, 'User not found');
    }

    public function store()
    {
        $request = request()->all();
        $user = new User($request);
        if($user->save()) {
            return $this->responseData(201, 'Success', $user);
        }
        return $this->responseData(400, 'Failed');
    }

    public function changePassword($id)
    {
        $request = request()->all();
        $user = User::find($id);
        if($user) {
            $user->update(['password' => bcrypt($request['password'])]);
            return $this->responseData(200, 'Success', $user);
        }
        return $this->responseData(404, 'User not found');
    }

    public function changeStatus($id)
    {
        $request = request()->all();
        $user = User::find($id);
        if($user) {
            $user->update(['status' => $request['status']]);
            return $this->responseData(200, 'Success', $user);
        }
        return $this->responseData(404, 'User not found');
    }

    public function changeRole($id)
    {
        $request = request()->all();
        $user = User::find($id);
        if($user) {
            $user->update(['role' => $request['role']]);
            return $this->responseData(200, 'Success', $user);
        }
        return $this->responseData(404, 'User not found');
    }

    public function changePlan($id)
    {
        $request = request()->all();
        $user = User::find($id);
        if($user) {
            $user->update(['current_plan' => $request['current_plan']]);
            return $this->responseData(200, 'Success', $user);
        }
        return $this->responseData(404, 'User not found');
    }

    public function changeProfile($id)
    {
        $request = request()->all();
        $user = User::find($id);
        if($user) {
            $user->update($request);
            return $this->responseData(200, 'Success', $user);
        }
        return $this->responseData(404, 'User not found');
    }

    public function changeAvatar($id)
    {
        $request = request()->all();
        $user = User::find($id);
        if($user) {
            $user->update(['avatar' => $request['avatar']]);
            return $this->responseData(200, 'Success', $user);
        }
        return $this->responseData(404, 'User not found');
    }

    public function getUsers()
    {
        $users = User::all();
        return $this->responseData(200, 'Success', $users);
    }
}
