<?php

namespace App\Http\Controllers\Admin;

use App\Models\Invoice;
use App\Traits\ResponseTraits;

class InvoiceController
{
    use  ResponseTraits;

    public function index()
    {
        $request = request()->all();

        $invoices = Invoice::query();
        // Get param by condition

        // Search
        if(isset($request['q'])) {
            $invoices->search($request['q']);
        }

        // Status
        if(isset($request['status'])) {
            $invoices->status($request['status']);
        }

        // User
        if(isset($request['user'])) {
            $invoices->where('user_id', $request['user']);
        }

        // Issue date
        if(isset($request['date'])) {
            $invoices->where('issue_date', $request['date']);
        }

        // Sort
        if(isset($request['sortBy'])) {
            $invoices->sort($request['sortBy'], $request['orderBy']);
        } else {
            $invoices->sort('created_at', 'desc');
        }

        $invoices->with('user');

        // Pagination
        $invoices = $invoices->pagination($request['itemsPerPage'], $request['page']);

        return $this->responseData(200, 'Success', $invoices);
    }

    public function show($id)
    {
        $invoice = Invoice::find($id)->with('user', 'details')->first();
        logger(['invoice' => $invoice]);
        if($invoice) {
            return $this->responseData(200, 'Success', $invoice);
        }
        return $this->responseData(404, 'Invoice not found');
    }

    public function update($id)
    {
        $request = request()->all();
        $invoice = Invoice::find($id);
        if($invoice) {
            $invoice->update($request);
            return $this->responseData(200, 'Update invoice success', $invoice);
        }
        return $this->responseData(404, 'Invoice not found');
    }

    public function store()
    {
        $request = request()->all();
        $invoice = Invoice::create($request);
        return $this->responseData(200, 'Create invoice success', $invoice);
    }

    public function destroy($id)
    {
        $invoice = Invoice::find($id);
        if($invoice) {
            $invoice->delete();
            return $this->responseData(200, 'Delete invoice success');
        }
        return $this->responseData(404, 'Invoice not found');
    }

    public function getInvoices()
    {
        $invoices = Invoice::all();
        return $this->responseData(200, 'Success', $invoices);
    }
}
