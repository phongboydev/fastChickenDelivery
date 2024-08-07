<?php

namespace App\GraphQL\Mutations;

use App\Models\WorkSchedule;
use App\Models\WorkScheduleGroup;
use ErrorException;
use HttpException;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

use Maatwebsite\Excel\Facades\Excel;
use \Maatwebsite\Excel\Validators\ValidationException as ValidationException;
use App\Imports\TimesheetsImport;
use Illuminate\Support\Facades\Input;
use App\Exceptions\CustomException;

class WorkScheduleGenerateMutator
{
    /**
     * Upload a file, store it on the server and return the path.
     *
     * @param  mixed $root
     * @param  mixed[] $args
     * @return string|null
     */
    public function __invoke($root, array $args): array
    {

        // TODO check permission
        $rules = array(
            'client_id' => 'required',
            'begin_date' => 'required',
            'end_date' => 'required',
            'work_days' => 'required',
            'check_in' => 'required',
            'check_out' => 'required',
            'rest_hours' => 'required',
            'start_break' => 'required',
            'end_break' => 'required',
            'work_schedule_group_id' => 'required'
        );

        try {
            Validator::make($args, $rules);

            $ws = new WorkSchedule();
            $clientId = $args['client_id'];
            $beginDate = Carbon::parse($args['begin_date']);
            $endDate = Carbon::parse($args['end_date']);
            $genereatedWs = $ws->generateMonthWorkSchedules(
                $beginDate,
                $endDate,
                explode(',', $args['work_days']),
                $args['check_in'],
                $args['check_out'],
                $args['rest_hours'],
                $args['start_break'],
                $args['end_break'],
                $args['work_schedule_group_id']
            );

            // TODO skip day that already has schedule
            DB::beginTransaction();
            $genereatedWs->each(function (WorkSchedule $ws) use ($clientId) {
                $ws->client_id = $clientId;
                $ws->save();
            });

            // $workScheduleGroup = new WorkScheduleGroup();

            $workScheduleGroup = WorkScheduleGroup::select('*')->where('id', $args['work_schedule_group_id'])->first();

            $expectedWorkHours = $workScheduleGroup->calculateExpectedWorkHours($args['work_schedule_group_id']);

            WorkScheduleGroup::where('id', $args['work_schedule_group_id'])->update([
                'expected_work_hours' => $expectedWorkHours
            ]);

            DB::commit();
            return $genereatedWs->toArray();
        } catch (ValidationException $e) {
            throw new CustomException(
                'The given data was invalid.',
                'ValidationException'
            );
        } catch (ErrorException $e) {
            throw new CustomException(
                'The given data was invalid.',
                'ErrorException'
            );
        } catch (HttpException $e) {
            throw new CustomException(
                'The given data was invalid.',
                'HttpException'
            );
        }
    }
}
