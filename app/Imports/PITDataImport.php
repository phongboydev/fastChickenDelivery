<?php

namespace App\Imports;
use App\Exceptions\CustomException;
use App\Exceptions\DownloadFileErrorException;
use App\Models\ImportablePITData;
use App\Models\ImportablePITEmployee;
use Carbon\Carbon;
use Illuminate\Http\File;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithGroupedHeadingRow;

class PITDataImport implements ToCollection, WithHeadingRow, WithGroupedHeadingRow
{
    private string $code_col = "0";
    private string $full_name_col = "1";
    private string $tax_code_col = "2";
    private string $id_number_col = "3";

    private array  $data_group_col = [
        'resident_status' => 0,
        'taxable_income' => 1,
        'number_of_dependants' => 2,
        'deduction_for_dependants' => 3,
        'compulsory_social_insurance' => 4,
        'assessable_income' => 5,
        'pit' => 6,
    ];
    private int $total_month = 0;

    protected string $path;
    protected string $client_id;

    /**
     * @param  string $path
     * @param  string $client_id
     */
    public function __construct(
        string $path,
        string $client_id
    ) {
        $this->path = $path;
        $this->client_id = $client_id;
    }

    public function collection(Collection $rows)
    {
        $this->validate($rows);

        $dataUpsert = [];

        DB::beginTransaction();
        try {
            //$row is record of each employee.
            foreach ($rows as $row) {
                $PITEmployee = ImportablePITEmployee::updateOrCreate(
                    [
                        'client_id' => $this->client_id,
                        'code' => $row[$this->code_col]
                    ],
                    [
                        'full_name' => $row[$this->full_name_col],
                        'tax_code' => $row[$this->tax_code_col],
                        'id_number' => $row[$this->id_number_col]
                    ]
                );

                //$data is values of each month
                foreach ($row as $key => $data) {
                    if (!is_array($data) || count($data) != count($this->data_group_col)) {
                        continue;
                    }
                    $split = explode('_', $key);
                    $dataUpsert[] = [
                        'id' => Str::uuid(),
                        'importable_pit_employee_id' => $PITEmployee->id,
                        'month' => $split[0],
                        'year' => $split[1],
                        'resident_status' => $data[$this->data_group_col['resident_status']],
                        'taxable_income' => $data[$this->data_group_col['taxable_income']],
                        'number_of_dependants' => $data[$this->data_group_col['number_of_dependants']],
                        'deduction_for_dependants' => $data[$this->data_group_col['deduction_for_dependants']],
                        'compulsory_social_insurance' => $data[$this->data_group_col['compulsory_social_insurance']],
                        'assessable_income' => $data[$this->data_group_col['assessable_income']],
                        'pit' => $data[$this->data_group_col['pit']],
                    ];
                }
            }

            if (empty($dataUpsert)) {
                DB::rollBack();
                throw new CustomException(
                    'no_data',
                    'ErrorException'
                );
            }

            ImportablePITData::upsert($dataUpsert,
                [
                    'importable_pit_employee_id',
                    'month',
                    'year'
                ],
                [
                    'resident_status',
                    'taxable_income',
                    'number_of_dependants',
                    'deduction_for_dependants',
                    'compulsory_social_insurance',
                    'assessable_income',
                    'pit'
                ]
            );

            DB::commit();

        } catch(\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function validate($rows): void
    {
        $errors = [
            'formats' => [],
            'startRow' => 0
        ];

        if (!$rows[0]) {
            throw new CustomException(
                'no_data',
                'ErrorException'
            );

        }

        foreach ($rows[0] as $key => $data) {
            if (is_array($data)) {
                try {
                    $split = explode('_', $key);
                    Carbon::createFromDate($split[1], $split[0], 1);
                } catch (\Exception $e) {
                    throw new CustomException(
                        'Kiểm tra lại format ngày tháng',
                        'ErrorException'
                    );
                }

                if (count($data) != count($this->data_group_col)) {
                    throw new CustomException(
                        'Khoảng thời gian của các trường dữ liệu không khớp với nhau',
                        'ErrorException'
                    );
                }
                $this->total_month++;
            }
        }

        foreach ($rows as $row_number => $row) {
            $validator = Validator::make($row->toArray(), [
                $this->code_col => 'required',
                $this->full_name_col => 'required',
                $this->tax_code_col => 'required|digits:10',
                $this->id_number_col => 'required|min:8|max:12',
            ]);

            if ($validator->fails()) {
                $errorsMsg = $validator->errors()->toArray();

                foreach ($errorsMsg as $col => $msg) {
                    $errors['formats'][] = ['row' => $row_number + 5, 'col' => $col + 1, 'error' => $msg];
                }
            }

            $index = 0;
            foreach ($row as $data) {
                if (is_array($data)) {
                    $validator = Validator::make($data, [
                        $this->data_group_col['resident_status'] => 'integer|min:0|max:7',
                        $this->data_group_col['taxable_income'] => 'nullable|numeric',
                        $this->data_group_col['number_of_dependants'] => 'nullable|numeric',
                        $this->data_group_col['deduction_for_dependants'] => 'nullable|numeric',
                        $this->data_group_col['compulsory_social_insurance'] => 'nullable|numeric',
                        $this->data_group_col['assessable_income'] => 'nullable|numeric',
                        $this->data_group_col['pit'] => 'nullable|numeric',
                    ]);

                    if ($validator->fails()) {
                        $errorsMsg = $validator->errors()->toArray();

                        foreach ($errorsMsg as $key => $msg) {
                            $col = 5 + $index + ($key * $this->total_month);
                            $errors['formats'][] = ['row' => $row_number + 5, 'col' => $col, 'error' => $msg];
                        }
                    }
                    $index++;
                }
            }
        }

        if ($errors['formats']) {
            $inputFileName = 'pit_employee_import_' . time() . '.xlsx';
            $inputFileImport = 'PITEmployee/' . $inputFileName;
            Storage::disk('local')->putFileAs(
                'PITEmployee',
                new File($this->path),
                $inputFileName
            );
            throw new DownloadFileErrorException(['import_template' => $errors], $inputFileImport);
        }
    }

    public function headingRow(): int
    {
        return 4;
    }
}
