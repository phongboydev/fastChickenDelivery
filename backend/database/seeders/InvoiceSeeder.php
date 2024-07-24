<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\InvoiceDetail;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class InvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $invoice = new Invoice();
        $invoice->number = 'INV-2024-0001';
        $invoice->user_id = 1;
        $invoice->total_price = 1300000;
        $invoice->issue_date = '2024-07-22';
        $invoice->due_date = '2024-07-29';
        $invoice->status = 'unpaid';
        $invoice->save();

        $invoiceDetail = new InvoiceDetail();
        $invoiceDetail->invoice_id = $invoice->id;
        $invoiceDetail->order_id = 1;
        $invoiceDetail->product_id = 1;
        $invoiceDetail->product_name = 'Product 1';
        $invoiceDetail->product_price = 1000000;
        $invoiceDetail->product_quantity = 1;
        $invoiceDetail->product_discount = 0;
        $invoiceDetail->product_total = 1000000;
        $invoiceDetail->save();
    }
}
