@extends('admin.layout')
@section('admin_content')
    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Thông tin đơn đặt hàng {{ $order->code_invoice }}</h1>
                    </div><!-- /.col -->
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ URL::to(route('screen_admin_home')) }}">Trang
                                    chủ</a></li>
                            <li class="breadcrumb-item active">Hóa đơn</li>
                        </ol>
                    </div><!-- /.col -->
                </div><!-- /.row -->
            </div><!-- /.container-fluid -->
        </div>
        <!-- /.content-header -->
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        @if (session('message'))
                            <div class="card-header">
                                <p class="noti">{{ session('message') }}</p>
                            </div>
                        @endif
                        <!-- Main content -->
                        <div class="invoice p-3 mb-3">
                            <!-- Table row -->
                            <div class="row">
                                <div class="col-12 table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Số thứ tự</th>
                                                <th>Sản phẩm</th>
                                                <th>Số lượng</th>
                                                <th>Đơn giá</th>
                                                <th>Thành tiền</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $i = 1; ?>
                                            @foreach ($order->detailInvoiceExport->sortByDesc('created_at') as $key => $detailInvoiceExport)
                                                <tr>
                                                    <td>{{ $i++ }}</td>
                                                    <td> {{ $detailInvoiceExport->product->name }}</td>
                                                    <td> {{ number_format($detailInvoiceExport->quantity, 0, ',', '.') }}
                                                    </td>
                                                    <td> {{ Lang::get('message.before_unit_money') . number_format($detailInvoiceExport->product->price, 0, ',', '.') . Lang::get('message.after_unit_money') }}
                                                    </td>
                                                    <td> {{ Lang::get('message.before_unit_money') . number_format($detailInvoiceExport->into_money, 0, ',', '.') . Lang::get('message.after_unit_money') }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <!-- /.col -->
                            </div>
                            <!-- /.row -->
                            <div class="row">
                                <div class="col-6"></div>
                                <div class="col-6">
                                    <p class="lead">Thông tin đơn đặt hàng </p>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <tr>
                                                <th style="width:50%">Tổng tiền</th>
                                                <td> {{ Lang::get('message.before_unit_money') . number_format($order->into_money, 0, ',', '.') . Lang::get('message.after_unit_money') }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th style="width:50%">Khách hàng</th>
                                                <td> {{ $order->name_user }}</td>
                                            </tr>
                                            <tr>
                                                <th style="width:50%">Số điện thoại</th>
                                                <td> {{ $order->phone_user }}</td>
                                            </tr>
                                            <tr>
                                                <th style="width:50%">Phương thức thanh toán</th>
                                                <td>
                                                    @if ($order->is_pay_cod)
                                                        {{ Lang::get('message.pay_cod') }}
                                                    @else
                                                        {{ Lang::get('message.pay_online') }}
                                                    @endif
                                                </td>
                                            </tr>
                                            <tr>
                                                <th style="width:50%">Trạng thái thanh toán</th>
                                                <td>
                                                    @if ($order->is_payment)
                                                        {{ Lang::get('message.paid') }}
                                                    @else
                                                        {{ Lang::get('message.pay_not') }}
                                                    @endif
                                                </td>
                                            </tr>
                                            <tr>
                                                <th style="width:50%">Thông tin đơn hàng</th>
                                                <td> {{ $order->status_ship }}</td>
                                            </tr>
                                            <tr>
                                                <th style="width:50%">Thông tin thêm</th>
                                                <td> {{ $order->message }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                <!-- /.col -->
                            </div>
                            <!-- /.row -->
                            @if ($order->status_ship == Lang::get('message.received'))
                                <div class="row no-print">
                                    <div class="col-12">
                                        <a href="{{ URL::to(route('admin.invoice_export.accept_order', ['id' => $order->id])) }}"
                                            class="btn btn-success float-right">
                                            <i class="far fa-credit-card"></i>
                                            Xác nhận
                                        </a>
                                    </div>
                                </div>
                            @endif
                        </div>
                        <!-- /.invoice -->
                    </div><!-- /.col -->
                </div><!-- /.row -->
            </div><!-- /.container-fluid -->
        </section>
        <!-- /.content -->
    </div>
@endsection
