<?php

namespace App\GraphQL\Mutations;

use App\Models\TrainingSeminar;
use App\Models\TrainingSeminarSchedule;
use App\Models\ClientDepartmentPositionTrainingSeminar;
use App\Models\ClientDepartmentTrainingSeminar;
use App\Models\ClientEmployeeTrainingSeminar;
use App\Models\ClientTeacherTrainingSeminar;
use App\Support\Constant;
use Carbon\Carbon;
use App\Exceptions\CustomException;
use App\Exports\TrainingSeminarDetailExport;
use App\Exports\TrainingSeminarExport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class TrainingSeminarMutator
{
    public function create($root, array $args)
    {

        if (auth()->user()->getRole() == Constant::ROLE_CLIENT_MANAGER || auth()->user()->hasDirectPermission('manage-training')) {

            DB::beginTransaction();

            try {

                // Validate
                if (count($args['training_seminar_schedule']) > 0 && count($args['client_employees']) > 0) {
                    $training = TrainingSeminar::create(array(
                        'code' => $args['code'],
                        'client_id' => auth()->user()->client_id,
                        'description' => $args['description'],
                        'attendance' => $args['attendance'],
                    ));

                    // ClientEmployeeTrainingSeminar
                    foreach ($args['client_employees'] as $item) {
                        ClientEmployeeTrainingSeminar::create(
                            array(
                                'client_id' => auth()->user()->client_id,
                                'client_employee_id' => $item['client_employee_id'],
                                'training_seminar_id' => $training->id,
                            )
                        );
                    }

                    // Insert TrainingSeminarSchedule
                    foreach ($args['training_seminar_schedule'] as $item) {

                        $startTime = Carbon::parse($item['start_time']);
                        $finishTime = Carbon::parse($item['end_time']);

                        TrainingSeminarSchedule::create(
                            array(
                                'training_seminar_id' => $training->id,
                                'start_time' => $item['start_time'],
                                'end_time' => $item['end_time'],
                                'duration' => $finishTime->diff($startTime)->format('%H:%I')
                            )
                        );
                    }

                    // Insert Teacher
                    if (isset($args['teacher_ids'])) {
                        foreach ($args['teacher_ids'] as $item) {
                            ClientTeacherTrainingSeminar::create(
                                array(
                                    'training_seminar_id' => $training->id,
                                    'teacher_training_id' => $item,
                                )
                            );
                        }
                    }

                    DB::commit();

                    return $training; // all good

                } else {
                    throw new CustomException(
                        'Please double check the information on the required fields.',
                        'ValidationException'
                    );
                }
            } catch (\Throwable $e) {

                DB::rollback();

                echo $e->getMessage();
                // something went wrong
                return 'not ok';
            }
        } else {
            throw new CustomException(
                'You do not have permission to use this feature.',
                'ValidationException'
            );
        }
    }

    public function update($root, array $args)
    {
        $training_seminar = TrainingSeminar::where(['id' => $args['id'], 'client_id' => auth()->user()->client_id])->exists();

        if (auth()->user()->getRole() == Constant::ROLE_CLIENT_MANAGER && $training_seminar || auth()->user()->hasDirectPermission('manage-training') && $training_seminar) {

            DB::beginTransaction();

            try {

                // Step 1 - Delete training schedule
                if (isset($args['training_seminar_schedule']['delete'])) {
                    foreach ($args['training_seminar_schedule']['delete'] as $item) {
                        TrainingSeminarSchedule::where([
                            'id' => $item['id'],
                            'training_seminar_id' => $args['id'],
                        ])->delete();
                    }
                }

                // Step 2 - Update training schedule
                if (isset($args['training_seminar_schedule']['update'])) {
                    foreach ($args['training_seminar_schedule']['update'] as $item) {
                        $schedule = TrainingSeminarSchedule::where([
                            'id' => $item['id'],
                            'training_seminar_id' => $args['id'],
                        ])->first();

                        if ($schedule) {
                            if ($schedule->getOriginal('start_time') != $item['start_time'] || $schedule->getOriginal('end_time') != $item['end_time']) {
                                $startTime = Carbon::parse($item['start_time']);
                                $finishTime = Carbon::parse($item['end_time']);
                                $schedule->start_time = $item['start_time'];
                                $schedule->end_time = $item['end_time'];
                                $schedule->duration = $finishTime->diff($startTime)->format('%H:%I');
                                $schedule->save();
                            }
                        }
                    }
                }

                // Step 3 - Create a training schedule
                if (isset($args['training_seminar_schedule']['create'])) {
                    foreach ($args['training_seminar_schedule']['create'] as $item) {
                        $startTime = Carbon::parse($item['start_time']);
                        $finishTime = Carbon::parse($item['end_time']);
                        TrainingSeminarSchedule::create([
                            'training_seminar_id' => $args['id'],
                            'start_time' => $item['start_time'],
                            'end_time' => $item['end_time'],
                            'duration' => $finishTime->diff($startTime)->format('%H:%I')
                        ]);
                    }
                }

                $training = TrainingSeminar::find($args['id']);
                $training->code = $args['code'];
                $training->client_id = auth()->user()->client_id;
                $training->description = $args['description'];
                $training->attendance = $args['attendance'];
                $training->save();

                if (isset($args['positions'])) {
                    // Delete record
                    ClientDepartmentPositionTrainingSeminar::where('training_seminar_id', $args['id'])->delete();
                    // Insert ClientDepartmentPositionTrainingSeminar
                    foreach ($args['positions'] as $item) {
                        ClientDepartmentPositionTrainingSeminar::create(
                            array(
                                'training_seminar_id' => $training->id,
                                'position' => $item['position']
                            )
                        );
                    }
                }

                if (isset($args['departments'])) {
                    // Delete record
                    ClientDepartmentTrainingSeminar::where('training_seminar_id', $args['id'])->delete();
                    // Insert ClientDepartmentTrainingSeminar
                    foreach ($args['departments'] as $item) {
                        ClientDepartmentTrainingSeminar::create(
                            array(
                                'training_seminar_id' => $training->id,
                                'client_department_id' => $item['department_id']
                            )
                        );
                    }
                }

                if (isset($args['teacher_ids'])) {
                    // Delete record
                    ClientTeacherTrainingSeminar::where('training_seminar_id', $args['id'])->delete();
                    // Insert Teacher
                    foreach ($args['teacher_ids'] as $item) {
                        ClientTeacherTrainingSeminar::create(
                            array(
                                'training_seminar_id' => $training->id,
                                'teacher_training_id' => $item,
                            )
                        );
                    }
                }


                DB::commit();

                return $training; // all good

            } catch (\Throwable $e) {

                DB::rollback();

                echo $e->getMessage();
                // something went wrong
                return 'not ok';
            }
        } else {
            throw new CustomException(
                'You do not have permission to use this feature.',
                'ValidationException'
            );
        }
    }

    public function export($root, array $args)
    {
        // Args
        $fromDate = isset($args['input']['from_date']) ? $args['input']['from_date'] : null;
        $toDate = isset($args['input']['to_date']) ? $args['input']['to_date'] : null;
        $type = $args['input']['client_id'] && isset($args['input']['employee_id']) ? 'USER' : 'CLIENT';
        $detail = isset($args['input']['training_seminar_id']) ? true : false;
        $training_seminar_id = isset($args['input']['training_seminar_id']) ? $args['input']['training_seminar_id'] : null;
        $employee_id = isset($args['input']['employee_id']) ? $args['input']['employee_id'] : null;
        $client_id = $args['input']['client_id'];

        if ($detail) {
            $fileName = "TrainingSeminarExportDetail_" . $type . "_" . time() . '.xlsx';
            $pathFile = 'TrainingSeminarExportDetail/' . $fileName;

            $data = TrainingSeminar::where('id', $training_seminar_id)
                ->withCount(['trainingSeminarSchedule', 'clientEmployee'])
                ->with(['clientEmployee' => function ($q) use ($training_seminar_id, $employee_id, $type) {
                    if ($type === 'USER') {
                        $q->where('client_employee_id', $employee_id);
                    }
                    $q->with([
                        "trainingSeminarAttendance" => function ($q) use ($training_seminar_id) {
                            $q->where('training_seminar_attendance.training_seminar_id', $training_seminar_id);
                            $q->with(["trainingSeminarSchedule"]);
                        },
                        "clientEmployee",
                    ]);
                }, 'trainingSeminarSchedule'])
                ->first();

            $total_rows = $data['clientEmployee']->reduce(function ($carry, $item) {
                $check = $item->client_employee_attendance_count === 0 ? 1 : $item->client_employee_attendance_count;
                return $carry + $check;
            }, 0);

            $params = [
                'data' => $data,
                'type' => $type,
                'total_rows_sheet_1' => $data->training_seminar_schedule_count,
                'total_rows_sheet_2' => $total_rows,
            ];

            Excel::store((new TrainingSeminarDetailExport($params)), $pathFile, 'minio');
        } else {
            $fileName = "TrainingSeminarExport_" . $type . "_" . time() . '.xlsx';
            $pathFile = 'TrainingSeminarExport/' . $fileName;

            $data = TrainingSeminar::where('client_id', $client_id)
                ->where(
                    function ($query) use ($fromDate, $toDate) {
                        if (isset($fromDate) && isset($toDate)) {
                            $query->whereDate('created_at', '>=', $fromDate)
                                ->whereDate('created_at', '<=', $toDate);
                        }
                    }
                )
                ->whereRelation('clientEmployee', function ($query) use ($type, $employee_id) {
                    if ($type === 'USER') {
                        $query->where('client_employee_id', $employee_id);
                    }
                })
                ->withCount(['trainingSeminarSchedule' => function ($query) use ($fromDate, $toDate) {
                    if (isset($fromDate) && isset($toDate)) {
                        $query->whereDate('training_seminar_schedule.created_at', '>=', $fromDate)
                            ->whereDate('training_seminar_schedule.created_at', '<=', $toDate);
                    }
                },])
                ->withCount(['clientEmployee'])
                ->having('training_seminar_schedule_count', '>', 0)
                ->orderBy('created_at', 'desc')->get();

            $total_rows = $data->reduce(function ($carry, $item) {
                return $carry + $item->training_seminar_schedule_count;
            }, 0);

            $params = [
                'data' => $data,
                'total_rows' => $total_rows,
                'type' => $type,
                'fromDate' => $fromDate,
                'toDate' => $toDate
            ];

            Excel::store((new TrainingSeminarExport($params)), $pathFile, 'minio');
        }

        $response = [
            'name' => $fileName,
            'url' => Storage::temporaryUrl($pathFile, Carbon::now()->addMinutes(config('app.media_temporary_time', 5)))
        ];

        return json_encode($response);
    }
}
