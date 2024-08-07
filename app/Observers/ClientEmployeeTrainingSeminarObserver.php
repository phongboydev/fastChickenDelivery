<?php

namespace App\Observers;

use App\Models\ClientEmployee;
use App\Models\ClientEmployeeTrainingSeminar;
use App\Models\TrainingSeminar;
use App\Notifications\ClientEmployeeTrainingSeminarNotification;

class ClientEmployeeTrainingSeminarObserver
{

    /**
     * Handle the ClientEmployeeTrainingSeminar "created" event.
     *
     * @param  \App\Models\ClientEmployeeTrainingSeminar  $clientEmployeeTrainingSeminar
     * @return void
     */
    public function created(ClientEmployeeTrainingSeminar $clientEmployeeTrainingSeminar)
    {
        try {
            $user = ClientEmployee::find($clientEmployeeTrainingSeminar->client_employee_id)->user()->first();
            $training = TrainingSeminar::where('id', $clientEmployeeTrainingSeminar->training_seminar_id)->with(['trainingSeminarSchedule'])->first();

            $data = [
                'id' => $clientEmployeeTrainingSeminar->id,
                'user' => $user,
                'training' => $training,
                'state' => 'created'
            ];

            $user->notify((new ClientEmployeeTrainingSeminarNotification($data))->delay(now()->addSecond(15)));
        } catch (\Throwable $th) {
            logger()->warning('ClientEmployeeTrainingSeminarObserver -> ClientEmployeeTrainingSeminarNotification: created' . $th);
        }
    }

    /**
     * Handle the ClientEmployeeTrainingSeminar "updated" event.
     *
     * @param  \App\Models\ClientEmployeeTrainingSeminar  $clientEmployeeTrainingSeminar
     * @return void
     */
    public function updated(ClientEmployeeTrainingSeminar $clientEmployeeTrainingSeminar)
    {
        //
    }

    /**
     * Handle the ClientEmployeeTrainingSeminar "deleted" event.
     *
     * @param  \App\Models\ClientEmployeeTrainingSeminar  $clientEmployeeTrainingSeminar
     * @return void
     */
    public function deleted(ClientEmployeeTrainingSeminar $clientEmployeeTrainingSeminar)
    {
        try {
            $user = ClientEmployee::find($clientEmployeeTrainingSeminar->client_employee_id)->user()->first();
            $training = TrainingSeminar::find($clientEmployeeTrainingSeminar->training_seminar_id)->first();
            $data = [
                'user' => $user,
                'training' => $training,
                'state' => 'deleted'
            ];

            $user->notify((new ClientEmployeeTrainingSeminarNotification($data))->delay(now()->addSecond(15)));
        } catch (\Throwable $th) {
            logger()->warning('ClientEmployeeTrainingSeminarObserver -> ClientEmployeeTrainingSeminarNotification: deleted' . $th);
        }
    }

    /**
     * Handle the ClientEmployeeTrainingSeminar "restored" event.
     *
     * @param  \App\Models\ClientEmployeeTrainingSeminar  $clientEmployeeTrainingSeminar
     * @return void
     */
    public function restored(ClientEmployeeTrainingSeminar $clientEmployeeTrainingSeminar)
    {
        //
    }

    /**
     * Handle the ClientEmployeeTrainingSeminar "force deleted" event.
     *
     * @param  \App\Models\ClientEmployeeTrainingSeminar  $clientEmployeeTrainingSeminar
     * @return void
     */
    public function forceDeleted(ClientEmployeeTrainingSeminar $clientEmployeeTrainingSeminar)
    {
        //
    }
}
