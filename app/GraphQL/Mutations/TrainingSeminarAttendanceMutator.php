<?php

namespace App\GraphQL\Mutations;

use App\Models\TrainingSeminar;
use App\Models\TrainingSeminarAttendance;
use Illuminate\Support\Facades\DB;
use App\Support\Constant;
use App\Exceptions\CustomException;

class TrainingSeminarAttendanceMutator
{
    public function create($root, array $args)
    {

        $training_seminar = TrainingSeminar::where(['id' => $args['training_seminar_id'], 'client_id' => auth()->user()->client_id])->exists();

        if (auth()->user()->getRole() == Constant::ROLE_CLIENT_MANAGER && $training_seminar || auth()->user()->hasDirectPermission('manage-training') && $training_seminar) {

            DB::beginTransaction();

            try {

                foreach ($args['attendance'] as $attendance) {
                    foreach ($attendance['training_seminar_schedule'] as $item => $schedule) {

                        TrainingSeminarAttendance::updateOrCreate(
                            [
                                'client_employee_id'   => $attendance['client_employees_id'],
                                'training_seminar_schedule_id' => $attendance['training_seminar_schedule'][$item],
                                'training_seminar_id' => $args['training_seminar_id'],
                            ],
                            [
                                'state' => $args['state'],
                                'note' => (isset($args['note'])) ? $args['note'] : NULL // Keep Note
                            ],
                        );
                    }
                }

                DB::commit();

                return true; // all good

            } catch (\Throwable $e) {

                DB::rollback();

                echo $e->getMessage();
                // something went wrong
                return false;
            }
        } else {
            throw new CustomException(
                'You do not have permission to use this feature.',
                'ValidationException'
            );
        }
    }
}
