<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Traits\ResponseTraits;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController
{
    use  ResponseTraits;

    public function index()
    {
        $request = request()->all();

        $orders = Order::query();
        // Get param by condition

        // User
        if(isset($request['userId'])) {
            $orders->where('user_id', $request['userId']);
        }

        // Order date
        if(isset($request['orderDate'])) {
            $orders->whereDate('order_date', $request['orderDate']);
        }

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
        $request = $request->all();

        DB::beginTransaction();
        try {
            $order = new Order();
            $order->order_number = $order->generateOrderNumber();
            $order->type = $request['type'];
            $order->order_date = Carbon::now();
            $order->payment_status = $request['paymentStatus'];
            $order->payment_method = $request['paymentMethod'];
            $order->user_id = $request['userId'];

            if($request['paymentStatus'] == 'paid') {
                $order->payment_date = Carbon::now();
            }

            // Save order
            $order->save();

            // Save order details
            $orderDetails = [];
            $totalPriceFinal = 0;
            foreach($request['orderDetails'] as $item) {
                $orderDetails[] = [
                    'id' => Str::uuid(),
                    'order_id' => $order->id,
                    'product_by_day_id' => $item['productId'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total_price' => $item['totalPrice'],
                ];

                $totalPriceFinal += $item['totalPrice'];
            }

            OrderDetail::insert($orderDetails);

            $order->total_price = $totalPriceFinal;
            $order->save();

            DB::commit();

            return $this->responseData(201, 'Create order success', $order);

        }catch (\Exception $e) {
            logger($e->getMessage());
            DB::rollBack();
            return $this->responseData(400, 'Create order fail');
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
