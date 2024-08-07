<?php

namespace App\Http\Controllers;

use App\Exceptions\DownloadFileErrorException;
use App\Imports\SpecialTimesheetImport;
use App\Jobs\SpecialTimesheetJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\ClientEmployee;
use App\Models\SyncTimesheetTmp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class TimesheetController extends Controller
{
    //
    public function syncTimeSheet(Request $request)
    {
        $user = Auth::user();
        if($user->id) {
            if($user->allow_call_api) {
                $validator = Validator::make($request->all(), [
                    'codeEmployee' => 'required',
                    'datetimeStart' => 'required|date_format:Y-m-d H:i:s',
                    'datetimeEnd' => 'required|date_format:Y-m-d H:i:s',
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'result' => 'fail',
                        'errors' => $validator->errors()
                    ], 400);
                }

                $start_time = Carbon::parse($request->get('datetimeStart'));
                $end_time = Carbon::parse($request->get('datetimeEnd'));
                if ($end_time->lessThan($start_time)) {
                    return response()->json([
                        'result' => 'fail',
                        'errors' => ['Thời gian kết thúc không được nhỏ hơn thời gian bắt đầu.']
                    ], 400);
                }


                $codeEmployee = $request->input("codeEmployee");

                if(!empty($user->client_id)) {
                    $employee = ClientEmployee::where('client_id', $user->client_id)
                    ->where('code',$codeEmployee)->first();
                    if(empty($employee->id)) {
                        return response()->json([
                            'result' => 'fail',
                            'errors' => ['Mã nhân viên ' . $codeEmployee . ' không tồn tại.']
                        ], 400);
                    }
                } else {
                    return response()->json([
                        'result' => 'fail',
                        'errors' => ['Công ty không tồn tại.']
                    ], 400);
                }

                $data = [
                    'client_id' => $user->client_id,
                    'client_employee_id' => $employee->id,
                    'code' => $codeEmployee,
                    'datetimeStart' => $request->get('datetimeStart'),
                    'datetimeEnd' => $request->get('datetimeEnd'),
                    'data_request' => $request->all()
                ];

                $this->storeTimeSheetCheckinTemp($data);
                return response()->json([
                    'result' => 'success',
                    'message' => 'Successful!'
                ], 200);
            } else {
                return response()->json([
                    'result' => 'fail',
                    'message' => 'Tài khoản không được phép!'
                ], 200);
            }

        } else {
            return response()->json([
                'result' => 'fail',
                'message' => 'Vui lòng đăng nhập!'
            ], 200);
        }

    }

    public function importTimeSheetFile(Request $request)
    {
        $user = Auth::user();
        if (!$user->allow_call_api) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:xls,xlsx|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => 'fail',
                'errors' => $validator->errors()
            ], 400);
        }

        $extension = $request['file']->extension();
        $filename = time() . "_" . $request['file']->getClientOriginalName();
        if ($user->isInternalUser()) {
            $clientCode = $user->client_id;
        } else {
            $clientCode = $user->client->code;
        }

        $errors = [
            'formats' => [],
            'startRow' => 0,
        ];

        try {
            $timesheetImport = new SpecialTimesheetImport($user->client_id, $user->id);

            Excel::import($timesheetImport, $request['file']);

            foreach ($timesheetImport->failures() as $failure) {
                foreach ($failure->errors() as $error) {
                    $errors['formats'][] = [
                        'row' => $failure->row(),
                        'col' => (int)$failure->attribute() + 1,
                        'error' => $error
                    ];
                }
            }
            if ($errors['formats']) {
                $inputFileName = 'timesheet_import_' . time() . '.' . $extension;
                $inputFileImport = 'TimesheetImport/' . $inputFileName;

                Storage::disk('local')->putFileAs(
                    'TimesheetImport',
                    $request['file'],
                    $inputFileName
                );

                throw new DownloadFileErrorException([$timesheetImport->getSheetName() => $errors], $inputFileImport);
            }
            foreach ($timesheetImport->getClientEmployeesImport() as $clientEmployeeId) {
                dispatch(new SpecialTimesheetJob($user->client_id, $timesheetImport->getImportKey(), $clientEmployeeId));
            } 

            Storage::disk('minio')->putFileAs("ImportTimesheetExternal/{$clientCode}/", $request['file'], $filename);

        } catch (DownloadFileErrorException $e) {
            $ms = json_decode($e->getMessage());

            return response()->json([
                'result' => 'fail',
                'error_file' => $ms->download,
                'message' => __('warning.WR0001.import'),
            ], 400);

        } catch (\Exception $e) {

            return response()->json([
                'message' => __('error.internal'),
            ], 500);

        }

        return response()->json([
            'result' => 'success',
            'message' => __('processing_state.processing')
        ], 200);
    }

    private function storeTimeSheetCheckinTemp($data) {
        $date = Carbon::parse($data['datetimeStart'])->format('Y-m-d');
        $timeChecking  = SyncTimesheetTmp::whereDate('date_time',$date)->where('status', 0)->first();
        if($timeChecking) {
            $data['data_request']['created_at'] = Carbon::now()->format('Y-m-d') ;
            $dataTmp = json_decode($timeChecking->data,true);
            $dataTmp[] = $data['data_request'];
            $timeChecking->data =  json_encode($dataTmp);
            $timeChecking->save();
        } else {
            SyncTimesheetTmp::updateOrCreate(
                [
                    'data' => json_encode([$data['data_request']]),
                    'code' => $data['code']
                ],
                [
                    'client_id' =>  $data['client_id'],
                    'client_employee_id' => $data['client_employee_id'],
                    'date_time' => $data['datetimeStart'],
                    'status' => 0,
                ]);
        }

    }
}
