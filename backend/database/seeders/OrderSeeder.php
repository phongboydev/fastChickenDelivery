<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $order = new \App\Models\Order();
        $order->user_id = 1;
        $order->order_number = 'ORD-2024-07-19-073449';
        $order->order_date = '2024-07-19';
        $order->total_price = 300000;
        $order->payment_status = 'Chưa thanh toán';
        $order->payment_method = 'Tiền mặt';
        $order->payment_date = '2024-07-19';
        $order->save();

        $orderDetail = new \App\Models\OrderDetail();
        $orderDetail->order_id = $order->id;
        $orderDetail->product_by_day_id = '434bf423-061e-475e-b786-bf75e1487f4b';
        $orderDetail->quantity = 1;
        $orderDetail->price = 300000;
        $orderDetail->total_price = 300000;
        $orderDetail->save();

        $order = new \App\Models\Order();
        $order->user_id = 3;
        $order->order_number = 'ORD-2024-07-19-073450';
        $order->order_date = '2024-07-19';
        $order->total_price = 200000;
        $order->payment_status = 'Chưa thanh toán';
        $order->payment_method = 'Tiền mặt';
        $order->payment_date = '2024-07-19';
        $order->save();

        $orderDetail = new \App\Models\OrderDetail();
        $orderDetail->order_id = $order->id;
        $orderDetail->product_by_day_id = 'd0605aed-55ea-4254-a17d-eb8c8b76a672';
        $orderDetail->quantity = 1;
        $orderDetail->price = 200000;
        $orderDetail->total_price = 200000;
        $orderDetail->save();


    }
}
