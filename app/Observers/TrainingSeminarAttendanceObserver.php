<?php

namespace App\Observers;

use App\Models\ClientEmployeeTrainingSeminar;
use App\Models\TrainingSeminarAttendance;
use App\Models\TrainingSeminar;
use App\Models\ClientEmployee;
use App\Notifications\TrainingSeminarAttendanceNotification;

class TrainingSeminarAttendanceObserver
{
    /**
     * Handle the TrainingSeminarAttendance "created" event.
     *
     * @param  \App\Models\TrainingSeminarAttendance  $trainingSeminarAttendance
     * @return void
     */
    public function created(TrainingSeminarAttendance $trainingSeminarAttendance)
    {
        try {
            $user = ClientEmployee::find($trainingSeminarAttendance->client_employee_id)->user()->first();
            $training = TrainingSeminar::where('id', $trainingSeminarAttendance->training_seminar_id)->first();

            $client_employee_training_seminar = ClientEmployeeTrainingSeminar::where([
                'client_employee_id' => $trainingSeminarAttendance->client_employee_id,
                'training_seminar_id' => $trainingSeminarAttendance->training_seminar_id
            ])->first();

            $data = [
                'client_employee' => $client_employee_training_seminar,
                'training' => $training,
                'user' => $user,
                'attendance' => $trainingSeminarAttendance,
                'state' => 'created'
            ];

            $user->notify((new TrainingSeminarAttendanceNotification($data))->delay(now()->addSecond(15)));
        } catch (\Throwable $th) {
            logger()->warning('TrainingSeminarAttendanceObserver -> TrainingSeminarAttendanceNotification: created' . $th);
        }
    }

    /**
     * Handle the TrainingSeminarAttendance "updated" event.
     *
     * @param  \App\Models\TrainingSeminarAttendance  $trainingSeminarAttendance
     * @return void
     */
    public function updated(TrainingSeminarAttendance $trainingSeminarAttendance)
    {
        try {
            $user = ClientEmployee::find($trainingSeminarAttendance->client_employee_id)->user()->first();
            $training = TrainingSeminar::where('id', $trainingSeminarAttendance->training_seminar_id)->first();

            $client_employee_training_seminar = ClientEmployeeTrainingSeminar::where([
                'client_employee_id' => $trainingSeminarAttendance->client_employee_id,
                'training_seminar_id' => $trainingSeminarAttendance->training_seminar_id
            ])->first();

            $data = [
                'client_employee' => $client_employee_training_seminar,
                'training' => $training,
                'user' => $user,
                'attendance' => $trainingSeminarAttendance,
                'state' => 'updated'
            ];

            $user->notify((new TrainingSeminarAttendanceNotification($data))->delay(now()->addSecond(15)));
        } catch (\Throwable $th) {
            logger()->warning('TrainingSeminarAttendanceObserver -> TrainingSeminarAttendanceNotification: updated' . $th);
        }
    }

    /**
     * Handle the TrainingSeminarAttendance "deleted" event.
     *
     * @param  \App\Models\TrainingSeminarAttendance  $trainingSeminarAttendance
     * @return void
     */
    public function deleted(TrainingSeminarAttendance $trainingSeminarAttendance)
    {
        try {

            $user = ClientEmployee::find($trainingSeminarAttendance->client_employee_id)->user()->first();
            $training = TrainingSeminar::where('id', $trainingSeminarAttendance->training_seminar_id)->first();

            $client_employee_training_seminar = ClientEmployeeTrainingSeminar::where([
                'client_employee_id' => $trainingSeminarAttendance->client_employee_id,
                'training_seminar_id' => $trainingSeminarAttendance->training_seminar_id
            ])->first();

            $data = [
                'client_employee' => $client_employee_training_seminar,
                'training' => $training,
                'user' => $user,
                'attendance' => $trainingSeminarAttendance,
                'state' => 'deleted'
            ];

            $user->notify((new TrainingSeminarAttendanceNotification($data))->delay(now()->addSecond(15)));
        } catch (\Throwable $th) {
            logger()->warning('TrainingSeminarAttendanceObserver -> TrainingSeminarAttendanceNotification: deleted' . $th);
        }
    }

    /**
     * Handle the TrainingSeminarAttendance "restored" event.
     *
     * @param  \App\Models\TrainingSeminarAttendance  $trainingSeminarAttendance
     * @return void
     */
    public function restored(TrainingSeminarAttendance $trainingSeminarAttendance)
    {
        //
    }

    /**
     * Handle the TrainingSeminarAttendance "force deleted" event.
     *
     * @param  \App\Models\TrainingSeminarAttendance  $trainingSeminarAttendance
     * @return void
     */
    public function forceDeleted(TrainingSeminarAttendance $trainingSeminarAttendance)
    {
        //
    }
}
