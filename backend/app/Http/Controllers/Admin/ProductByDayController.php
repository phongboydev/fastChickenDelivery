<?php

namespace App\Http\Controllers\Admin;

use App\Models\ProductByDay;
use App\Traits\ResponseTraits;
use Illuminate\Http\Request;

class ProductByDayController
{
    use  ResponseTraits;

    public function index()
    {
        $request = request()->all();

        $productByDays = ProductByDay::query();
        // Get param by condition

        // Search
        if(isset($request['q'])) {
            $productByDays->search($request['q']);
        }

        // Status
        if(isset($request['status'])) {
            $productByDays->status($request['status']);
        }

        // Product
        if(isset($request['product'])) {
            $productByDays->product($request['product']);
        }

        // Date
        if(isset($request['date'])) {
            $productByDays->where('date', $request['date']);
        }

        // Sort
        if(isset($request['sortBy'])) {
            $productByDays->sort($request['sortBy'], $request['orderBy']);
        } else {
            $productByDays->sort('date', 'desc');
        }

        $productByDays->with('product');

        // Pagination
        $productByDays = $productByDays->pagination($request['itemsPerPage'], $request['page']);

        return $this->responseData(200, 'Success', $productByDays);
    }

    public function show($id)
    {
        $productByDay = ProductByDay::find($id);
        if($productByDay) {
            return $this->responseData(200, 'Success', $productByDay);
        }
        return $this->responseData(404, 'ProductByDay not found');
    }

    public function update($id)
    {
        $request = request()->all();
        $productByDay = ProductByDay::find($id);
        if($productByDay) {
            $productByDay->update($request);
            return $this->responseData(200, 'ProductByDay updated', $productByDay);
        }
        return $this->responseData(404, 'ProductByDay not found');
    }

    public function store()
    {
        $request = request()->all();
        $productByDay = new ProductByDay($request);
        if($productByDay->save()) {
            return $this->responseData(201, 'ProductByDay created', $productByDay);
        }
        return $this->responseData(400, 'ProductByDay not created');
    }

    public function destroy($id)
    {
        $productByDay = ProductByDay::find($id);
        if($productByDay) {
            $productByDay->delete();
            return $this->responseData(200, 'ProductByDay deleted');
        }
        return $this->responseData(404, 'ProductByDay not found');
    }

    public function getProductByDays()
    {
        $productByDays = ProductByDay::all();
        return $this->responseData(200, 'Success', $productByDays);
    }

    public function getProductByDay($id)
    {
        $productByDay = ProductByDay::find($id);
        if($productByDay) {
            return $this->responseData(200, 'Success', $productByDay);
        }
        return $this->responseData(404, 'ProductByDay not found');
    }

    public function changeStatus($id)
    {
        $productByDay = ProductByDay::find($id);
        if($productByDay) {
            $productByDay->update(['status' => !$productByDay->status]);
            return $this->responseData(200, 'ProductByDay status changed', $productByDay);
        }
        return $this->responseData(404, 'ProductByDay not found');
    }
}
