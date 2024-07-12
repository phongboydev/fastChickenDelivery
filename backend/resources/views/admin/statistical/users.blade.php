@extends('admin.layout')
@section('admin_content')
    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Thống kê khách hàng @if (isset($date))
                                từ ngày {{ date('d-m-Y', strtotime($date['start'])) }} đến ngày
                                {{ date('d-m-Y', strtotime($date['end'])) }}
                            @endif
                        </h1>
                    </div><!-- /.col -->
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ URL::to(route('screen_admin_home')) }}">Trang
                                    chủ</a></li>
                            <li class="breadcrumb-item active">Thống kê</li>
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
                                <form action="{{ URL::to(route('admin.statistical.users')) }}" method="GET">
                                    <div class="form-group row">
                                        <label>Chọn mốc thời gian:</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <i class="far fa-calendar-alt"></i>
                                                </span>
                                            </div>
                                            <input type="text" name="date" class="form-control float-right"
                                                id="reservation">
                                            <div class="input-group-append">
                                                <button type="submit" class="btn btn-primary">Xác nhận</button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                                <table id="example1" class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>Số thứ tự</th>
                                            <th>Khách hàng</th>
                                            <th>Số điện thoại</th>
                                            <th>Số lượng hóa đơn</th>
                                            <th>Tổng tiền</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if ($invoices)
                                            <?php $i = 1; ?>
                                            @foreach ($invoices as $key => $invoice)
                                                <tr>
                                                    <th>{{ $i++ }}</th>
                                                    <td>{{ $invoice->name_user }}</td>
                                                    <td>{{ $invoice->phone_user }}</td>
                                                    <td>{{ $invoice->quantity_invoice }}</td>
                                                    <td>{{ Lang::get('message.before_unit_money') . number_format($invoice->sum_money, 0, ',', '.') . Lang::get('message.after_unit_money') }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endif
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
