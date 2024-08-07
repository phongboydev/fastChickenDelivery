<?php

namespace App\Observers;

use App\Models\AssignmentProject;

class AssignmentProjectObserver
{
    /**
     * Handle the assignment project "created" event.
     *
     * @param  \App\AssignmentProject  $assignmentProject
     * @return void
     */
    public function created(AssignmentProject $assignmentProject)
    {
        //
    }

    /**
     * Handle the assignment project "updated" event.
     *
     * @param  \App\AssignmentProject  $assignmentProject
     * @return void
     */
    public function updated(AssignmentProject $assignmentProject)
    {
        //
    }

    public function deleting(AssignmentProject $assignmentProject) {
        $assignmentProject->assignmentTasks()->delete();
        $assignmentProject->assignee()->delete();
    }

    /**
     * Handle the assignment project "deleted" event.
     *
     * @param  \App\AssignmentProject  $assignmentProject
     * @return void
     */
    public function deleted(AssignmentProject $assignmentProject)
    {
        //
    }

    /**
     * Handle the assignment project "restored" event.
     *
     * @param  \App\AssignmentProject  $assignmentProject
     * @return void
     */
    public function restored(AssignmentProject $assignmentProject)
    {
        //
    }

    /**
     * Handle the assignment project "force deleted" event.
     *
     * @param  \App\AssignmentProject  $assignmentProject
     * @return void
     */
    public function forceDeleted(AssignmentProject $assignmentProject)
    {
        //
    }
}
