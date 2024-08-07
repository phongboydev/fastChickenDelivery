<?php

namespace App\Jobs;

use App\Models\ContractTemplate;
use App\Models\Contract;
use App\Models\ClientEmployeeSalaryHistory;
use App\Pdfs\ContractDocToPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;


class CreateClientEmployeeContract implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $employee;
    protected $variables;
    protected $customVariables;
    protected $contract;
    protected $contractData;

    public function __construct($employee, $variables, $customVariables, $contract, $contractData)
    {
        $this->employee = $employee;
        $this->variables = $variables;
        $this->customVariables = $customVariables;
        $this->contract = $contract;
        $this->contractData = $contractData;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->exportFile($this->contractData['template_id']);
    }

    public function getCodeNameContract($template, $variables)
    {

        $m = new \Mustache_Engine(array('entity_flags' => ENT_QUOTES));

        return $m->render($template, $variables);
    }

    private function exportFile($templateId): void
    {
        $contract = $this->contract;
        $variables = $this->customVariables;

        $contractTemplate = ContractTemplate::select('*')->where('id', $templateId)->first();

        if (!$contractTemplate) return;

        $mediaItem = $contractTemplate->getFirstMedia('ContractTemplate');

        if (!$mediaItem) return;

        $templatePath = $mediaItem->getPath();

        $fileName = strtolower(str_replace(' ', '', str_replace('/', '_', $contract->contract_no))) . '_' . time();

        // TODO (security) kiểm tra khả năng trùng file, nếu 2 hợp đồng cùng tên
        $contractPath = 'Contract/' . $fileName . '.docx';

        Storage::disk('local')->put($contractPath, Storage::disk('minio')->get($templatePath));

        $templateProcessor = new TemplateProcessor(storage_path('app/' . $contractPath));

        if ($variables) {
            foreach ($variables as $key => $value) {
                if ($key == 'SALARY') {
                    $salaryHistory = ClientEmployeeSalaryHistory::select("new_salary")->where('id', $this->contract['salary_history_id'])->first();
                    $value = $salaryHistory->new_salary;
                }
                $templateProcessor->setValue($key, $value);
            }
        }

        $templateProcessor->saveAs(storage_path('app/' . $contractPath));

        $contract->addMediaFromDisk($contractPath, 'local')
            ->storingConversionsOnDisk('minio')
            ->toMediaCollection(Contract::CONTRACT_MEDIA_COLLECTION, 'minio');

        $refreshedContract = Contract::where('id', $contract->id)->first();
        if ($refreshedContract) {
            $pdf = new ContractDocToPdf($refreshedContract);
            $job = new GeneratePdfJob($pdf);
            dispatch($job);
        }

        Storage::disk('local')->delete($contractPath);
    }
}
