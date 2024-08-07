<?php

namespace App\GraphQL\Mutations;

use ErrorException;
use HttpException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use App\User;
use App\Models\IglocalEmployee;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use App\Support\Constant;

use App\Notifications\IglocalEmployeeResetPasswordNotification;

class IglocalEmployeeMutator
{
    
    public function resetPassword($root, array $args)
    {

        $employee = IglocalEmployee::where('id', $args['id'])->first();
        
        $user = User::find($employee->user_id);

        $random_password = Str::random(10);
        $user->changed_random_password = 0;
        $user->password = Hash::make($random_password);
        $user->update();

        $user->notify(new IglocalEmployeeResetPasswordNotification($user, $employee, $random_password));

        return $employee;
    }

    public function changeRandomPassword($root, array $args)
    {
        $user = Auth::user();

        $user->password = Hash::make($args['password']);
        $user->changed_random_password = 1;
        $user->update();

        return $user;
    }
}
