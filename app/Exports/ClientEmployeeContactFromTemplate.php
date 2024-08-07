<?php

namespace App\Exports;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\Exportable;
use App\Models\CalculationSheetClientEmployee;
use App\Models\CalculationSheetVariable;
use App\Models\Client;
use App\Models\ClientEmployee;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Events\BeforeExport;
use Maatwebsite\Excel\Excel;

use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use App\Support\FormatHelper;

class ClientEmployeeContactFromTemplate implements WithEvents, ShouldAutoSize
{
    use Exportable;

    protected $clientId;
    protected $templateExport;
    protected $templateVariable;
    protected $rows;
    protected  $groupIds;

    private $total_list = 0;
    private $total_variables = 0;

    public function __construct(string $clientId, string $templateExport, array $templateVariable, $rows, $groupIds = [])
    {
        $this->clientId = $clientId;
        $this->templateExport = $templateExport;
        $this->templateVariable = $templateVariable;
        $this->rows = $rows;
        $this->groupIds = $groupIds;

        return $this;
    }

    public function registerEvents()
    : array
    {

        return [
            BeforeExport::class => function(BeforeExport $event){
                
                if( $this->templateExport ) {

                    $path = storage_path('app/' . $this->templateExport);

                    $pathInfo = pathinfo($path);

                    if( !in_array($pathInfo['extension'], ['xls', 'xlsx']) ) { return; }

                    $extension = $pathInfo['extension'] == 'xls' ? Excel::XLS : Excel::XLSX;

                    $event->writer->reopen( new \Maatwebsite\Excel\Files\LocalTemporaryFile($path), $extension);
        
                    $event->writer->getSheetByIndex(0);

                    $sheet = $event->getWriter()->getSheetByIndex(0);


                    $calculationSheetClientEmployeeData = [];

                    $employees = ClientEmployee::select('*')->with('contracts')->where('client_id', $this->clientId)->whereNull('deleted_at')->orderBy('full_name', 'ASC');
                    if(!empty($this->groupIds)) {
                        $user = Auth::user();
                        $listClientEmployeeId = $user->getListClientEmployeeByGroupIds($user, $this->groupIds);
                        if(!empty($listClientEmployeeId)) {
                            $employees = $employees->whereIn('id', $listClientEmployeeId);
                        }
                    }
                    $employees = $employees->get();

                    if (!empty($employees)) {

                        foreach ($employees as $cIndex => $item) {

                            $calculationSheetClientEmployeeDataTmp = [
                                'NO' => $cIndex + 1,
                                'CODE' => $item->code,
                                'NAME' => $item->full_name,
                                'S_DATE_OF_BIRTH' => FormatHelper::date($item->date_of_birth),
                                'S_GENDER' => $item->sex,
                            ];
                            
                            if($item->contracts->count() > 0) {

                                $lastestContacts = $this->filterLastestContracts($item->contracts->toArray());

                                foreach($lastestContacts as $contact) {

                                    if( $contact ) {

                                        $contract_signing_date = isset($contact['contract_signing_date']) ? FormatHelper::date($contact['contract_signing_date']) : '';
                                        $contract_end_date = isset($contact['contract_end_date']) ? FormatHelper::date($contact['contract_end_date']) : '';
                                        $resignation_date = FormatHelper::date($contact['created_at']);

                                        switch($contact['contract_type']) {
                                            case 'thuviec':
                                                $calculationSheetClientEmployeeDataTmp = array_merge($calculationSheetClientEmployeeDataTmp, [
                                                    'S_CONTRACT_NO_PROBATION' => $contact['contract_code'],
                                                    'S_PROBATION_START_DATE' => $contract_signing_date,
                                                    'S_PROBATION_END_DATE' => $contract_end_date,
                                                    'S_RESIGNATION_DATE' => $resignation_date
                                                ]);
                                                break;
                                            case 'co-thoi-han-lan-1':
                                                $calculationSheetClientEmployeeDataTmp = array_merge($calculationSheetClientEmployeeDataTmp, [
                                                    'S_CONTRACT_NO_DEFINITE_TERM_CONTRACT_FIRST_TIME' => $contact['contract_code'],
                                                    'S_DEFINITE_TERM_CONTRACT_FIRST_TIME_START_DATE' => $contract_signing_date,
                                                    'S_DEFINITE_TERM_CONTRACT_FIRST_TIME_END_DATE' => $contract_end_date,
                                                    'S_RESIGNATION_DATE' => $resignation_date
                                                ]);
                                                break;
                                            case 'co-thoi-han-lan-2':
                                                $calculationSheetClientEmployeeDataTmp = array_merge($calculationSheetClientEmployeeDataTmp, [
                                                    'S_CONTRACT_NO_DEFINITE_TERM_CONTRACT_SECOND_TIME' => $contact['contract_code'],
                                                    'S_DEFINITE_TERM_CONTRACT_SECOND_TIME_START_DATE' => $contract_signing_date,
                                                    'S_DEFINITE_TERM_CONTRACT_SECOND_TIME_END_DATE' => $contract_end_date,
                                                    'S_RESIGNATION_DATE' => $resignation_date
                                                ]);
                                                break;
                                            case 'khong-xac-dinh-thoi-han':
                                                $calculationSheetClientEmployeeDataTmp = array_merge($calculationSheetClientEmployeeDataTmp, [
                                                    'S_CONTRACT_NO_INDEFINITE_TERM_CONTRACT' => $contact['contract_code'],
                                                    'S_INDEFINITE_TERM_CONTRACT_START_DATE' => $contract_signing_date,
                                                    'S_RESIGNATION_DATE' => $resignation_date
                                                ]);
                                                break;
                                        }

                                    }

                                }

                            }
                            
                            $calculationSheetClientEmployeeData[] = $calculationSheetClientEmployeeDataTmp;

                        }

                        for($i = $this->templateVariable['$LOOP_START'][0][1]; $i < 18; $i++) {
                            
                            $colIndex = Coordinate::stringFromColumnIndex($i + 1);
                                
                            $sheet->setCellValue($colIndex . ($this->templateVariable['$LOOP_START'][0][0] + 2), null);
                        }

                        foreach( $this->templateVariable as $cIndex => $row ) {

                            foreach( $row as $cRow ) {
                                
                                $colIndex = Coordinate::stringFromColumnIndex($cRow[1] + 1);
                                
                                $sheet->setCellValue($colIndex . ($cRow[0]+1), null);

                            }
                            
                        } 

                        $totalRow = [];

                        foreach( $calculationSheetClientEmployeeData as $cIndex => $cRow ) {
    
                            foreach($cRow as $cKey => $value) {
    
                                $c = '$'.strtoupper($cKey);

                                if(isset($this->templateVariable[$c]) && !empty($this->templateVariable[$c])) {

                                    $totalRow[$cKey] = 0;
                                    
                                    foreach( $this->templateVariable[$c] as $dRow ) {

                                        $colIndex = Coordinate::stringFromColumnIndex($dRow[1] + 1);
                                       
                                        $sheet->setCellValue($colIndex . ($dRow[0] + $cIndex), $value);

                                    }
                                }
                                    
                            }

                            if( !empty($this->rows) ) {
                                $newRowIndex = $this->templateVariable['$LOOP_START'][0][0] + 1 + $cIndex;
                                
                                foreach ($this->rows[0] as $cellIndex => $cell) {   

                                    $column = Coordinate::stringFromColumnIndex($cellIndex + 1);
                                    
                                    $originRowIndex = $this->templateVariable['$LOOP_START'][0][0] + 2;
                                    
                                    $orginStyle = $sheet->getDelegate()->getStyle($column . $originRowIndex);

                                    $range = $column . $newRowIndex . ":" . $column . $newRowIndex;

                                    $sheet->getDelegate()->duplicateStyle($orginStyle, $range);

                                    $h = $sheet->getRowDimension($this->templateVariable['$LOOP_START'][0][0] + 1)->getRowHeight();

                                    $sheet->getRowDimension($newRowIndex)->setRowHeight($h);
                                }
                            }
                        }

                        if( !empty($calculationSheetClientEmployeeData) ) {
                            foreach( $calculationSheetClientEmployeeData as $items ) {
                                foreach ($items as $c => $item) {
                                    if(is_numeric($item) && isset($totalRow[$c])){
                                        $totalRow[$c] += $item;
                                    }
                                }
                            }
                        }

                    }
                    
                }
            },
        ];
    }

    private function filterLastestContracts($contracts)
    {
        
        $contractMap = [

            "thuviec" => false,
         
            "co-thoi-han-lan-1" => false,
         
            "co-thoi-han-lan-2" => false,
         
            "khong-xac-dinh-thoi-han" => false,
         
         ];
         
         $contractLatest = [
         
            "thuviec" => false,
         
            "co-thoi-han-lan-1" => false,
         
            "co-thoi-han-lan-2" => false,
         
            "khong-xac-dinh-thoi-han" => false,
         
         ];
         
         foreach($contracts as $contract) 
         {
             if ( isset($contractMap[$contract['contract_type']]) 
             && (!$contractMap[$contract['contract_type']] || strtotime($contractLatest[$contract['contract_type']]) < strtotime($contract['contract_signing_date'])) ) {
         
                   $contractMap[$contract['contract_type']] = $contract;
         
                   $contractLatest[$contract['contract_type']] = $contract['contract_signing_date'];
         
              }
         }
         
         return $contractMap;
    }
}
