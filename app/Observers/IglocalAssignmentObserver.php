<?php

namespace App\Observers;

use App\Models\IglocalAssignment;

class IglocalAssignmentObserver
{
    /**
     * Handle the iglocal assignment "created" event.
     *
     * @param  \App\IglocalAssignment  $iglocalAssignment
     * @return void
     */
    public function created(IglocalAssignment $iglocalAssignment)
    {
        $client = $iglocalAssignment->client;
        $project = $client->assignmentProject;
        $employee = $iglocalAssignment->iglocalEmployee;
        if ($project && $employee) {
            $accessLevel = $employee->role == 'leader' ? 'leader' : ($employee->role == 'director' ? 'manager' : 'member');
            $assignee = [
                'assignment_project_id' => $project->id,
                'user_id' => $employee->user_id,
                'access_level' => $accessLevel,
                'inviter_user_id' => null,

            ];
            $project->assignee()->create($assignee);
            \Log::info("Assign employee: $employee->name to project $project->name");
        } else {
            \Log::info("Cannot assign employee to project");
        }
    }

    /**
     * Handle the iglocal assignment "updated" event.
     *
     * @param  \App\IglocalAssignment  $iglocalAssignment
     * @return void
     */
    public function updated(IglocalAssignment $iglocalAssignment)
    {
        //
    }

    public function deleting(IglocalAssignment $iglocalAssignment)
    {
        $client = $iglocalAssignment->client;
        $employee = $iglocalAssignment->iglocalEmployee;
        $project = $client->assignmentProject;
        if ($project) {
            $project->assignee()->where('user_id', $employee->user_id)->delete();
        }
    }

    /**
     * Handle the iglocal assignment "deleted" event.
     *
     * @param  \App\IglocalAssignment  $iglocalAssignment
     * @return void
     */
    public function deleted(IglocalAssignment $iglocalAssignment)
    {
        //
    }

    /**
     * Handle the iglocal assignment "restored" event.
     *
     * @param  \App\IglocalAssignment  $iglocalAssignment
     * @return void
     */
    public function restored(IglocalAssignment $iglocalAssignment)
    {
        //
    }

    /**
     * Handle the iglocal assignment "force deleted" event.
     *
     * @param  \App\IglocalAssignment  $iglocalAssignment
     * @return void
     */
    public function forceDeleted(IglocalAssignment $iglocalAssignment)
    {
        //
    }
}
