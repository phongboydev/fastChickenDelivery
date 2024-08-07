<?php

namespace App\Observers;


use App\Jobs\SendDocumentDeliveryNotificationJob;
use App\Models\ClientAppliedDocument;
use App\Models\Comment;
use App\Support\Constant;
use App\User;
use Illuminate\Support\Facades\Auth;

class CommentObserver
{

    /**
     * Handle the client "created" event.
     *
     * @param Comment $comment
     * @return void
     */
    public function created(Comment $comment)
    {
        if($comment->target_type == 'App\Models\ClientAppliedDocument') {
            $clientAppliedDocument = ClientAppliedDocument::where('id', $comment->target_id)->first();

            //update status = processing if current status = new
            if (!isset(Constant::STATUS_RANK[$clientAppliedDocument->status])
                || Constant::STATUS_RANK[Constant::PROCESSING_STATUS] > Constant::STATUS_RANK[$clientAppliedDocument->status])
            {
                $clientAppliedDocument->status = Constant::PROCESSING_STATUS;
                $clientAppliedDocument->saveQuietly();
            }

            /** @var User $user */
            $user = Auth::user();
            dispatch(new SendDocumentDeliveryNotificationJob($clientAppliedDocument, $user, $comment));
        }
    }

}
