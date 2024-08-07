<?php

namespace App\Observers;

use App\Models\LibraryQuestionAnswer;
use App\Models\LibraryQuestionAnswerHistory;
use Illuminate\Support\Facades\Auth;

class LibraryQuestionAnswerObserver
{
    /**
     * Handle the LibraryQuestionAnswerMutator "created" event.
     *
     * @param LibraryQuestionAnswer $libraryQuestionAnswer
     * @return void
     */
    public function creating(LibraryQuestionAnswer $libraryQuestionAnswer)
    {

    }

    /**
     * Handle the LeaveCategory "created" event.
     *
     * @param LibraryQuestionAnswer $libraryQuestionAnswer
     * @return void
     */
    public function created(LibraryQuestionAnswer $libraryQuestionAnswer)
    {

    }

    /**
     * Handle the LeaveCategory "updated" event.
     *
     * @param LibraryQuestionAnswer $libraryQuestionAnswer
     * @return void
     */
    public function updating(LibraryQuestionAnswer $libraryQuestionAnswer)
    {

    }

    /**
     * Handle the LeaveCategory "updated" event.
     *
     * @param LibraryQuestionAnswer $libraryQuestionAnswer
     * @return void
     */
    public function updated(LibraryQuestionAnswer $libraryQuestionAnswer)
    {
        $libraryQuestionAnswerHistory = new LibraryQuestionAnswerHistory();
        $libraryQuestionAnswerHistory->library_question_answer_id = $libraryQuestionAnswer->id;
        $libraryQuestionAnswerHistory->new_value = json_encode($libraryQuestionAnswer->getAttributes());
        $libraryQuestionAnswerHistory->old_value = json_encode($libraryQuestionAnswer->getOriginal());
        $libraryQuestionAnswerHistory->updater_id = Auth::user()->id;
        $libraryQuestionAnswerHistory->save();
    }

    /**
     * Handle the LeaveCategory "deleting" event.
     *
     * @param LibraryQuestionAnswer $libraryQuestionAnswer
     * @return void
     */
    public function deleting(LibraryQuestionAnswer $libraryQuestionAnswer)
    {

    }

    /**
     * Handle the LeaveCategory "deleted" event.
     *
     * @param LibraryQuestionAnswer $libraryQuestionAnswer
     * @return void
     */
    public function deleted(LibraryQuestionAnswer $libraryQuestionAnswer)
    {

    }

    /**
     * Handle the LeaveCategory "restored" event.
     *
     * @param LibraryQuestionAnswer $libraryQuestionAnswer
     * @return void
     */
    public function restored(LibraryQuestionAnswer $libraryQuestionAnswer)
    {
        //
    }

    /**
     * Handle the LeaveCategory "force deleted" event.
     *
     * @param LibraryQuestionAnswer $libraryQuestionAnswer
     * @return void
     */
    public function forceDeleted(LibraryQuestionAnswer $libraryQuestionAnswer)
    {

    }

}
