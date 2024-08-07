<?php

namespace App\GraphQL\Mutations;

use App\User;
use App\Models\AssignmentProjectUser;

class AssignmentProjectUserMutator
{
    public function get($root, array $args)
    {
        $keywords = isset($args['keywords']) ? $args['keywords'] : '';
        $query = AssignmentProjectUser::select('assignment_project_users.*', 'users.name')
                                        ->join('users', 'users.id', 'assignment_project_users.user_id')
                                        ->where('users.name', 'LIKE', "%$keywords%");

        return $query;
    }

    public function getUnassignedProjectUsers($root, array $args) {
        $projectId = $args['projectId'];
        $exceptUserIds = AssignmentProjectUser::where('assignment_project_id', $projectId)
                                                ->get()
                                                ->pluck('user_id')
                                                ->toArray();
        $query = User::whereNotIn('id', $exceptUserIds);

        return $query;
    }
}
