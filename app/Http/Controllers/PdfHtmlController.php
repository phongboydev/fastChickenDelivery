<?php

namespace App\Http\Controllers;

use App\Models\CalculationSheetClientEmployee;
use App\Pdfs\CalculationSheetClientEmployeeHtmlToPdf;

class PdfHtmlController extends Controller
{

    public function payslip($id)
    {
        $model = CalculationSheetClientEmployee::where('id', $id)->first();
        $pdf = new CalculationSheetClientEmployeeHtmlToPdf($model);
        echo $pdf->generateHtml();
    }
}
