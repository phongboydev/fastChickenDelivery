<?php

namespace App\Imports;

use App\Exceptions\HumanErrorException;
use App\Models\Client;
use App\Models\ClientCustomVariable;
use App\Models\ClientEmployee;
use App\Models\ClientEmployeeCustomVariable;
use App\Models\CalculationSheetClientEmployee;
use App\Models\CalculationSheetVariable;

use App\Exports\DebitPaymentOcbExport;
use App\Models\DebitSetup;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use App\Exports\DebitPaymentExport;
use Illuminate\Http\File;

class DebitPaymentImport implements ToCollection
{

    protected $bank;
    protected $templateVariable;
    protected $payroll;
    protected $pathFile;

    public function __construct($bank, $payroll, string $pathFile)
    {
        $this->bank = $bank;
        $this->payroll = $payroll;
        $this->pathFile = $pathFile;

        return $this;
    }

    public function collection(Collection $rows)
    {

        $bank = $this->bank;
        $templateVariable = $this->getTemplateVariables($rows);

        // $salaryNeedToPaid = CalculationSheetClientEmployee::where('calculation_sheet_id', $payroll->id)->sum('calculated_value');
        // $debitSetup = DebitSetup::where('client_id', $payroll->client_id)->first();

        // if (empty($debitSetup)) {
        //     throw new HumanErrorException('Công ty chưa được thiết lập điều kiện');
        // }

        // $salary_amount_need_to_pay = $debitSetup->salary_amount_need_to_pay;
        // $current_debit_amount = $debitSetup->current_debit_amount;
        // $debit_threshold_payment = $debitSetup->debit_threshold_payment;

        // logger('DebitPaymentExport payroll ' . $payroll->id, [$bank, $salaryNeedToPaid, $salary_amount_need_to_pay, $current_debit_amount, $debit_threshold_payment]);

        // if (($salaryNeedToPaid > $salary_amount_need_to_pay) && (($salaryNeedToPaid - $current_debit_amount) > $debit_threshold_payment)) {

        //     $errorContent = "<p><strong>salaryNeedToPaid</strong>: {$salaryNeedToPaid}</p>";
        //     $errorContent .= "<p><strong>salary_amount_need_to_pay</strong>: {$salary_amount_need_to_pay}</p>";
        //     $errorContent .= "<p><strong>current_debit_amount</strong>: {$current_debit_amount}</p>";
        //     $errorContent .= "<p><strong>debit_threshold_payment</strong>: {$debit_threshold_payment}</p>";

        //     throw new HumanErrorException('<p>Không thể xuất file</p>' . $errorContent);
        // }

        switch ($bank) {
            case 'ocb':
                Excel::store((new DebitPaymentOcbExport($this->payroll, $templateVariable)), $this->pathFile, 'minio');
                break;
        }
    }

    protected function getTemplateVariables($rows)
    {
        $templateVariable = [
            '$LOOP_START' => [],
            '$FULL_NAME' => [],
            '$BANK_ACCOUNT_NUMBER' => [],
            '$SALARY' => [],
            '$BANK_CODE' => [],
            '$BANK_ACCOUNT' => [],
            '$PAYMENT_DETAIL' => [],
        ];

        foreach ($rows as $rowIndex => $row) {

            foreach ($row as $key => $value) {

                foreach ($templateVariable as $d => $v) {

                    if ($value === $d) {

                        $templateVariable[$d][] = [$rowIndex, $key];
                    }
                }
            }
        }



        return $templateVariable;
    }
}
