<?php

namespace App\Pdfs;

use App\Models\CalculationSheetClientEmployee;
use App\Models\CalculationSheetVariable;
use LightnCandy\Flags;
use LightnCandy\LightnCandy;
use Storage;

class CalculationSheetClientEmployeeHtmlToPdf extends AbstractHtmlToPdfGenerator
{

    protected CalculationSheetClientEmployee $model;

    public function __construct(CalculationSheetClientEmployee $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public function generateHtml(): string
    {
        $model = $this->model;
        /** @var \App\User $user */
        $user = $model->user;
        /** @var \App\Models\Client $client */
        $client = $model->client;
        /** @var \App\Models\Client $client */
        $clientEmployee = $model->clientEmployee;
        /** @var \App\Models\CalculationSheet $client */
        $calculationSheet = $model->calculationSheet;

        $language = $user && $calculationSheet->is_multilingual ? $user->prefered_language : 'en';
        $vars = CalculationSheetVariable::query()
                                        ->where('client_employee_id', $model->client_employee_id)
                                        ->where('calculation_sheet_id', $model->calculation_sheet_id)
                                        ->get()
                                        ->keyBy('variable_name')
                                        ->map(fn($var) => $var->variable_value)
                                        ->toArray();

        $extraVars = [
            'COMPANY_NAME' => $client->getTranslation('company_name', $language),
            'COMPANY_ADDRESS' => $client->address,
            'CODE' => $clientEmployee->code,
            'FULL_NAME' => $clientEmployee->full_name,
            'DATE' => $calculationSheet->month.'/'.$calculationSheet->year,
            'SOCIAL_INSURANCE_NUMBER' => $clientEmployee->social_insurance_number,
            'YEAR' => $calculationSheet->year,
            'MONTH' => $calculationSheet->month,
            'SALARY_FOR_SOCIAL_INSURANCE_PAYMENT' => $clientEmployee->salary_for_social_insurance_payment,
            'EFFECTIVE_DATE_OF_SOCIAL_INSURANCE' => $clientEmployee->effective_date_of_social_insurance,
            'MEDICAL_CARE_HOSPITAL_NAME' => $clientEmployee->medical_care_hospital_name,
            'MEDICAL_CARE_HOSPITAL_CODE' => $clientEmployee->medical_care_hospital_code,
            'HOUSEHOLD_CODE' => $clientEmployee->household_code,
            'MST_CODE' => $clientEmployee->mst_code,
            'PROBATION_START_DATE' => $clientEmployee->probation_start_date,
            'TOTAL' => $this->model->calculated_value,
            'POSITION' => $clientEmployee->client_position_name,
            'DEPARTMENT' => $clientEmployee->client_department_name,
            'ONBOARD_DATE' => $clientEmployee->onboard_date,
            'TITLE' => $clientEmployee->title,
            'EMAIL' => $user ? $user->email : '',
            'COMPANY_PHONE' => $client->company_contact_phone,
            'COMPANY_FAX' => $client->company_contact_fax,
            'COMPANY_EMAIL' => $client->company_contact_email,
            // Previous version compatible
            'SALARY' => $this->model->calculated_value,
        ];

        $vars = array_merge($extraVars, $vars);
        $template = view('pdfs.pdfLayout', [
            'html' => $model->calculationSheet->getAttribute('payslip_html_template_'
                    .$language) ?? '',
        ]);

        // Handlebars rendering
        $php = LightnCandy::compile($template, [
            'flags' => Flags::FLAG_HANDLEBARSJS,
            'helpers' => [
                'numberFormat' => function ($arg1, $options) {
                    $hash = $options['hash'];
                    $decimalLength = $hash['decimalLength'] ?? 2;
                    $thousandsSep = $hash['thousandsSep'] ?? ',';
                    $decimalSep = $hash['decimalSep'] ?? '.';

                    if (is_numeric($arg1)) {
                        return number_format(floatval($arg1), $decimalLength, $decimalSep, $thousandsSep);
                    }
                    return $arg1;
                },
            ],
        ]);
        $path = 'tmp_compiled_template_'.uniqid().'.php';
        Storage::disk('local')->put($path, '<?php '.$php.'?>');
        $renderer = include(Storage::disk('local')->path($path));
        $result = $renderer($vars);
        Storage::disk('local')->delete($path);
        // dd($result);
        return $result;
    }

    protected function getFileName(): string
    {
        return 'calculation_sheet_client_employee_'.$this->model->id.'.pdf';
    }
}
