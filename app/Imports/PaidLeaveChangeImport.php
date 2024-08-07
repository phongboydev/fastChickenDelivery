<?php

namespace App\Imports;

use App\Models\ClientEmployeeLeaveManagement;
use ErrorException;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithMappedCells;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Validators\Failure;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

use App\User;
use App\Models\Client;
use App\Models\ClientEmployee;
use App\Models\PaidLeaveChange;

use Illuminate\Support\Facades\Request;
use Illuminate\Support\Collection;
use App\Exceptions\CustomException;
use App\Support\Constant;
use Illuminate\Support\MessageBag;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use App\Support\ImportTrait;

class PaidLeaveChangeImport implements ToCollection, WithHeadingRow, WithStartRow
{
    use Importable, ImportTrait;

    protected $client_id = null;
    protected $type = 'tang_hang_nam';

    protected const RIGHT_HEADER = [
        "code" => ['code_employee_exists', 'required'],
        "full_name" => ['string', 'required'],
        "start_import_paidleave" => ['date', 'required'],
        "hours_import_paidleave" => ['string', 'required'],
    ];

    function __construct($clientId, $type)
    {
        $this->client_id = $clientId;
        $this->type = $type;
    }

    public function collection(Collection $rows)
    {
        $error = false;
        $errorLabel = array();
        $filteredData = collect([]);
        foreach ($rows as $key => $row) {
            $allColsIsEmpty = empty(array_filter($row->toArray(), function ($v) {
                return !empty($v);
            }));
            if (!$allColsIsEmpty) {

                $r = array_filter($row->toArray(), function ($v, $k) {
                    return $k;
                }, ARRAY_FILTER_USE_BOTH);

                $filteredData->push($r);
            }
        }

        // Date validation
        $dateErrors = new MessageBag();
        $filteredData = $filteredData->map(function ($data, $key) use ($dateErrors) {

            $checkDate = function ($fieldName) use ($dateErrors, &$data, $key) {
                if (isset($data[$fieldName]) && !empty($data[$fieldName])) {

                    $value = $this->transformDate($data[$fieldName], 'Y-m-d');

                    $value = (explode(' ', $value))[0];

                    if ($value) {
                        $data[$fieldName] = $value;
                    } else {
                        $field = $key . '.' . $fieldName;
                        $dateErrors->add($field, trans('validation.date', ['attribute' => $field]));
                    }
                } else {
                    unset($data[$fieldName]);
                }
            };

            $checkDate('start_import_paidleave');

            return $data;
        });

        if (!$dateErrors->isEmpty()) {
            throw new CustomException(
                $dateErrors,
                'ValidationException'
            );
        }


        DB::beginTransaction();
        $i = 4;
        if (!empty($this->client_id)) {

            foreach ($filteredData as $row) {

                $clientEmployee = ClientEmployee::where('client_id', '=', $this->client_id)
                    ->where('code', '=', $row['code'])
                    ->first();
                if (!empty($clientEmployee)) {

                    $data = $row;

                    // prepare user import file data
                    foreach ($data as $key => $value) {
                        $intData = array("hours_import_paidleave");

                        foreach ($intData as $d) {
                            if (!isset($data[$d])) {
                                $data[$d] = 0;
                            }
                        }
                    }

                    unset($data['']);

                    $nowDate = new Carbon('now');

                    $effectiveAt = Carbon::parse($data['start_import_paidleave']);

                    $startedImportPaidLeave = Carbon::parse($data['start_import_paidleave']);

                    $hours_import_paidleave = $data['hours_import_paidleave'];

                    $hours = $clientEmployee->year_paid_leave_count;

                    switch($this->type){
                        case 'tang_hang_thang':

                            $hours += $hours_import_paidleave;
                            $startedImportPaidLeave->addMonth(1);

                            break;
                        case 'tang_hang_nam':

                            $hours = $hours_import_paidleave;
                            $startedImportPaidLeave->addYear(1);

                            break;
                        default:
                            break;
                    }

                    if (($nowDate->format('Y-m-d') == $effectiveAt->format('Y-m-d')) || !$effectiveAt->greaterThan($nowDate)) {
                        $clientEmployee->update([
                            'start_import_paidleave' => $effectiveAt->format('Y-m-d'),
                            'case_import_paidleave' => $this->type,
                            'hours_import_paidleave' => $data['hours_import_paidleave'],
                            'year_paid_leave_count' => $hours,
                            'started_import_paidleave' => $startedImportPaidLeave->format('Y-m-d'),
                        ]);

                        // Update leave hours with year leave type
                        $clientEmployeeLeaveManagement = ClientEmployeeLeaveManagement::where('client_employee_id', $clientEmployee->id)->whereHas('leaveCategory', function ($query) use ($nowDate) {
                            $query->where('type', 'authorized_leave')
                                ->where('sub_type', 'year_leave')
                                ->where('start_date', '<=', $nowDate)
                                ->where('end_date', '>=', $nowDate);
                        })
                            ->first();
                        if ($clientEmployeeLeaveManagement) {
                            if ($this->type == 'tang_hang_thang') {
                                $clientEmployeeLeaveManagement->entitlement = $clientEmployeeLeaveManagement->entitlement + $hours_import_paidleave;
                            } elseif ($this->type == 'tang_hang_nam') {
                                $clientEmployeeLeaveManagement->entitlement = $hours;
                            }
                            $clientEmployeeLeaveManagement->save();
                        }
                    }else{
                        $clientEmployee->update([
                            'start_import_paidleave' => $effectiveAt->format('Y-m-d'),
                            'case_import_paidleave' => $this->type,
                            'hours_import_paidleave' => $data['hours_import_paidleave'],
                            'started_import_paidleave' => $effectiveAt->format('Y-m-d'),
                        ]);
                    }
                }

                $i++;
            }
        }

        $mesg = implode('<br/>', $errorLabel);
        if ($error == true) {
            DB::rollBack();

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

    /**
     * @return int
     */
    public function startRow(): int
    {
        return 4;
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
        return 6;
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
        if ((is_int((int)$value)) && (strlen((string)$value) == 4)) {
            $value = (string)$value . '-' . Constant::DEFAULT_APPEND_TO_DATE;
            return Carbon::createFromFormat($format, $value);
        }

        try {

            return Carbon::instance(Date::excelToDateTimeObject($value));
        } catch (ErrorException $e) {
            return null;
        }
    }
}
