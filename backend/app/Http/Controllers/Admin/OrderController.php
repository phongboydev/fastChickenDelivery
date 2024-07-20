<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use App\Traits\ResponseTraits;
use Illuminate\Http\Request;

class OrderController
{
    use  ResponseTraits;

    public function index()
    {
        $request = request()->all();

        $orders = Order::query();
        // Get param by condition

        // Search
        if(isset($request['q'])) {
            $orders->search($request['q']);
        }

        // Status
        if(isset($request['status'])) {
            $orders->status($request['status']);
        }

        // Sort
        if(isset($request['sortBy'])) {
            $orders->sort($request['sortBy'], $request['orderBy']);
        } else {
            $orders->sort('created_at', 'desc');
        }

        $orders->with('user');

        // Pagination
        $orders = $orders->pagination($request['itemsPerPage'], $request['page']);

        return $this->responseData(200, 'Success', $orders);
    }

    public function show($id)
    {
        $order = Order::find($id);
        if($order) {
            return $this->responseData(200, 'Success', $order);
        }
        return $this->responseData(404, 'Order not found');
    }

    public function update(Request $request, $id)
    {
        $order = Order::find($id);
        if($order) {
            $order->update($request->all());
            return $this->responseData(200, 'Update order success', $order);
        }
        return $this->responseData(404, 'Order not found');
    }

    public function destroy($id)
    {
        $order = Order::find($id);
        if($order) {
            $order->delete();
            return $this->responseData(200, 'Delete order success');
        }
        return $this->responseData(404, 'Order not found');
    }

    public function total()
    {
        $total = Order::total();
        return $this->responseData(200, 'Success', $total);
    }

    public function totalByStatus($status)
    {
        $total = Order::totalByStatus($status);
        return $this->responseData(200, 'Success', $total);
    }

    public function store(Request $request)
    {
        $order = new Order($request->all());
        if($order->save()) {
            return $this->responseData(201, 'Create order success', $order);
        }
        return $this->responseData(400, 'Create order fail');
    }

    public function getOrders()
    {
        $orders = Order::all();
        return $this->responseData(200, 'Success', $orders);
    }

    public function getOrder($id)
    {
        $order = Order::find($id);
        if($order) {
            return $this->responseData(200, 'Success', $order);
        }
        return $this->responseData(404, 'Order not found');
    }
}
