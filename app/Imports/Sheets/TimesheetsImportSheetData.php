<?php

namespace App\Imports\Sheets;

use ErrorException;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use App\Exceptions\CustomException;
use App\Exceptions\HumanErrorException;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\GraphQL\Queries\GetTimesheetSchedules;
use App\Models\Timesheet;
use App\Models\Client;
use App\Models\ClientEmployee;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleGroup;
use App\Models\WorktimeRegister;
use Illuminate\Support\Facades\Auth;
use App\User;
use App\Support\Constant;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use App\Support\ImportTrait;
use Illuminate\Support\Carbon as SupportCarbon;

class TimesheetsImportSheetData implements ToCollection, WithHeadingRow, WithStartRow
{
    use Importable, ImportTrait;

    protected const RIGHT_HEADER = [
        "client_employee_code" => ['string', 'required'],
        "log_date" => ['date', 'required'],
        "check_in" =>  ['string'],
        "check_out" =>  ['string'],
        "working_hours" =>  ['string'],
        "overtime_hours" =>  ['string'],
        "next_day" =>  ['string'],
        // "work_status" =>  ['number', 'required'], //we dont import work_status anymore
        "note" =>  ['string']
    ];

    protected $client_id = null;
    protected $user = null;

    function __construct($clientId, $user)
    {
        $this->client_id = $clientId;
        $this->user = $user;
    }

    /**
     * @param Collection $rows
     * @throws Exception
     */
    public function collection(Collection $rows)
    {
        $error = false;
        $errorLabel = array();

        $filteredData = collect([]);
        foreach ($rows as $row) {
            if ($row->filter()->isNotEmpty()) {
                $row['check_in'] = (isset($row['check_in']) && $row['check_in']) ? $this->transformDate($row['check_in'], 'H:i')->format('H:i') : null;
                $row['check_out'] = (isset($row['check_out']) && $row['check_out']) ? $this->transformDate($row['check_out'], 'H:i')->format('H:i') : null;
                $row['log_date'] = isset($row['log_date']) ? $this->transformDate($row['log_date'])->format('Y-m-d') : null;
                $row['next_day'] = isset($row['next_day']) && $row['next_day'] == 1 ? 1 : 0;
                $row['note'] = isset($row['note']) ? $row['note'] : '';
                $row['working_hours'] = isset($row['working_hours']) ? $row['working_hours'] : 0;
                $row['overtime_hours'] = isset($row['overtime_hours']) ? $row['overtime_hours'] : 0;

                $filteredData->push($row);
            }
        }

        DB::beginTransaction();
        $i = 2;
        foreach ($filteredData as $row) {
            $clientEmployeeCode = trim($row['client_employee_code'], "[]");
            $clientEmployeeId = '';

            $clientOj = Client::where('id', $this->client_id)->with('clientWorkflowSetting')->first();
            if ($clientOj && (!empty($clientEmployeeCode))) {
                /** @var ClientEmployee $clientEmployee */
                $clientEmployee = ClientEmployee::where('client_id', $clientOj->id)
                                                ->where('code', $clientEmployeeCode)
                                                ->first();
                $workSchedule = (new WorkSchedule)->checkExitWorkSchedule($this->client_id, $row['log_date']);
                if (!$workSchedule) {
                    throw new HumanErrorException(__("error.import_timesheet_khong_co_lich_lam_viec"));
                }
                if (!$clientEmployee) {
                    $error = true;
                    $errorLabel[] = "Dòng " . $i . " - Mã Nhân viên không tồn tại.";
                } else {
                    $clientEmployeeId = $clientEmployee->id;
                }

            } else {
                $error = true;
                $errorLabel[] = "Công ty không tồn tại.";
            }

            if ($error != true) {
                if ($this->canImport([
                    'client_id' => $this->client_id,
                    'client_employee_id' => $clientEmployeeId,
                ])) {
                    $check_in  = ( isset( $row['check_in'] ) )  ? $row['check_in'] : '';
                    $check_out = ( isset( $row['check_out'] ) ) ? $row['check_out'] : '';

                    // Spec requested on slack
                    // Nếu check_out = 00:00, và next_day = 0 thì coi như empty
                    if ($check_out == '00:00' && $row['next_day'] != 1) {
                        $check_out = '';
                    }

                    if (!empty($check_in) && !empty($check_out)) {
                        $checkIn = Carbon::parse($row['log_date'] . ' ' . $check_in);
                        $checkOut = Carbon::parse($row['log_date'] . ' ' . $check_out);

                        if( $row['next_day'] != 1 && $checkOut->isBefore($checkIn) ) {

                            $clientEmployeeCode = '[' . $clientEmployee->code . '] ' . $clientEmployee->full_name . ': ' . $check_in . ' - ' . $check_out;

                            throw new HumanErrorException( $clientEmployeeCode . ' ' . __('error.invalid_time'));
                        }
                    }

                    $timesheet = (new Timesheet)->findTimeSheet($clientEmployeeId, $row['log_date']);
                    if (!$timesheet) {
                        $timesheet = $clientEmployee->touchTimesheet($row['log_date']);
                    }
                    $timesheet->check_in = $check_in;
                    $timesheet->check_out = $check_out;
                    $timesheet->next_day = $row['next_day'];
                    $timesheet->flexible = 0;
                    $timesheet->note = $row['note'];

                    if( !$clientOj->clientWorkflowSetting['enable_timesheet_rule'] ){

                        $timesheet->working_hours = $row['working_hours'];
                        $timesheet->overtime_hours = $row['overtime_hours'];

                        $timesheet->saveQuietly();
                    }else{
                        $timesheet->recalculate();
                        $timesheet->saveQuietly();
                    }

                } else {
                    throw new HumanErrorException(
                        'You are not authorized to access importTimesheet.',
                        'AuthorizedException'
                    );
                }
            }
            $i++;
        }

        $mesg = implode('\n', $errorLabel);
        if ($error == true) {
            DB::rollBack();

            $mesg = json_encode(['type' => 'validate', 'msg' => $mesg]);

            throw new CustomException(
                $mesg,
                'VALIDATION_RULES'
            );
        }

        DB::commit();
    }

    public function isDynamicsStartRow()
    {
        return false;
    }

    public function headingRow(): int
    {
        return 1;
    }

    /**
     * @return int
     */
    public function startRow(): int
    {
        return 2;
    }

    public function endRow($rows): int
    {
        return -1;
    }

    public function startHeader(): int
    {
        return 1;
    }

    public function totalCol()
    {
        return 8;
    }

    public function getRightHeader()
    {
        return self::RIGHT_HEADER;
    }

    public function getClientID()
    {
        return $this->client_id;
    }

    /**
     * Transform a date value into a Carbon object.
     *
     * @return Carbon|null
     */
    public function transformDate($value, $format = 'Y-m-d')
    {
        try {
            return Carbon::instance(Date::excelToDateTimeObject($value));
        } catch (ErrorException $e) {
            return Carbon::createFromFormat($format, $value);
        }
    }

    public function timeToDecimal($time)
    {
        $decTime = 0;
        $timeArr = explode(':', $time);
        if (count($timeArr) == 2) {
            $decTime = (int)$timeArr[0] + round($timeArr[1] / 60, 1);
        }
        return $decTime;
    }

    /**
     * Determine whether the user can import timesheets.
     *
     * @param  \App\Timesheet  $timesheet
     * @return mixed
     */
    public function canImport(array $timesheet)
    {
        $user = $this->user;

        if (!$user->isInternalUser()) {
            $role = $user->getRole();



            switch ($role) {
                case Constant::ROLE_CLIENT_MANAGER:
                    if ($user->client_id == $timesheet['client_id']) {
                        return true;
                    }
                    return false;
                case Constant::ROLE_CLIENT_STAFF:
                    if ($user->hasAnyPermission(['manage-timesheet'])) {
                        return $user->client_id == $timesheet['client_id'];
                    }

                    if ((!empty($timesheet['client_employee_id'])) && ($user->clientEmployee->id == $timesheet['client_employee_id'])) {
                        return true;
                    }
                    return false;
                default:
                    if ($user->hasAnyPermission(['manage-timesheet'])) {
                        return $user->client_id == $timesheet['client_id'];
                    } else {
                        return false;
                    }
            }
        } else {
            $role = $user->getRole();

            switch ($role) {
                case Constant::ROLE_INTERNAL_STAFF:
                    if ($user->iGlocalEmployee->isAssignedFor($timesheet['client_id'])) {
                        return true;
                    }
                    return false;
                case Constant::ROLE_INTERNAL_LEADER:
                case Constant::ROLE_INTERNAL_DIRECTOR:
                    return true;
                default:
                    return false;
            }
        }
    }

    //Đi làm,Đi công tác,Nghỉ cuối tuần,Nghỉ không lương,Nghỉ phép,Nghỉ lễ

    public function transformWorkStatus($value)
    {
        switch ($value) {
            case 1:
                return "Đi làm";
            case 2:
                return "Công tác";
            case 3:
                return "Nghỉ phép HL";
            case 4:
                return "Nghỉ phép KHL";
            case 5:
                return "Nghỉ cuối tuần";
            default:
            case 6:
                return "Nghỉ lễ";
        }
    }
}
