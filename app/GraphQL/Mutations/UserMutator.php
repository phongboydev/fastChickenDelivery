<?php

namespace App\GraphQL\Mutations;

use App\Exceptions\HumanErrorException;
use App\Models\ClientEmployee;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserMutator {

    public function createUserWithEmployee($root, array $args)
    {
        $user = Auth::user();

        if (!$args['client_id']) {
            throw new HumanErrorException(__('error.not_found', ['name' => __('client')]));
        }

        if ($user->isInternalUser()) {
            if (!$user->iGlocalEmployee->isAssignedFor($args['client_id'])) {
                throw new HumanErrorException(__('authorized'));
            }
        } else {
            if ($user->client_id != $args['client_id']) {
                throw new HumanErrorException(__('authorized'));
            }
        }

        $employee = ClientEmployee::find($args['client_employee_id']);
        if (empty($employee)) {
            throw new HumanErrorException(__('error.not_found', ['name' => __('employee')]));
        }

        if ($args['is_internal'] == 1) {
            throw new HumanErrorException(__('authorized'));
        }

        if (User::where('client_id', $args['client_id'])->where('username', $args['client_id']. "_". $args['username'])->count()) {
            throw new HumanErrorException(__('importing.already_taken_msg', ['msg' => __('fields.username')]));
        }

        $args['password'] = bcrypt(Str::random(10));

        DB::transaction(function () use ($args, $employee) {
            $user = User::create($args);
            $employee->user_id = $user->id;
            $employee->save();
        });
        return $user;
    }
}
