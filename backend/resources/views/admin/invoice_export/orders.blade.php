@extends('admin.layout')
@section('admin_content')
    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Danh sách đơn đặt hàng</h1>
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
        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            @if (session('message'))
                                <div class="card-header">
                                    <p class="noti">{{ session('message') }}</p>
                                </div>
                            @endif
                            <!-- /.card-header -->
                            <div class="card-body">
                                <table id="example1" class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Số thứ tự</th>
                                            <th>Mã đơn hàng</th>
                                            <th>Thời gian tạo</th>
                                            <th>Tổng tiền</th>
                                            <th>Trạng thái đơn hàng</th>
                                            <th>Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $i = 1; ?>
                                        @foreach ($orders as $key => $order)
                                            <tr>
                                                <td>{{ $i++ }}</td>
                                                <td>{{ $order->code_invoice }}</td>
                                                <td>{{ $order->created_at }}</td>
                                                <td> {{ Lang::get('message.before_unit_money') . number_format($order->into_money, 0, ',', '.') . Lang::get('message.after_unit_money') }}
                                                </td>
                                                <td>{{ $order->status_ship }}</td>
                                                <td class="act">
                                                    <a
                                                        href="{{ URL::to(route('admin.invoice_export.order_view', ['id' => $order->id])) }}">
                                                        <i class="text-success fas fa-eye ico"></i>
                                                    </a>
                                                    <a href="{{ URL::to(route('admin.invoice_export.cancel_order', ['id' => $order->id])) }}"
                                                        onclick="return confirm( '{{ Lang::get('message.do_u_cancel') }} {{ $order->code_invoice }}?');">
                                                        <i class="text-danger fas fa-ban ico"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <!-- /.card-body -->
                        </div>
                        <!-- /.card -->
                    </div>
                    <!-- /.col -->
                </div>
                <!-- /.row -->
            </div>
            <!-- /.container-fluid -->
        </section>
        <!-- /.content -->
    </div>
@endsection
