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
                                            <th>Cập nhật trạng thái</th>
                                            <th>Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $i = 1; ?>
                                        @foreach ($invoices as $key => $invoice)
                                            <tr>
                                                <td>{{ $i++ }}</td>
                                                <td>{{ $invoice->code_invoice }}</td>
                                                <td>{{ $invoice->created_at }}</td>
                                                <td> {{ Lang::get('message.before_unit_money') . number_format($invoice->into_money, 0, ',', '.') . Lang::get('message.after_unit_money') }}
                                                </td>
                                                <td>{{ $invoice->status_ship }}</td>
                                                <td>
                                                    @if ($invoice->status_ship == Lang::get('message.ready'))
                                                        <a
                                                            href="{{ URL::to(route('admin.invoice_export.up_status_ship', ['id' => $invoice->id])) }}">
                                                            {{ Lang::get('message.shipping') }}
                                                        </a>
                                                    @elseif ($invoice->status_ship == Lang::get('message.shipping'))
                                                        <a
                                                            href="{{ URL::to(route('admin.invoice_export.up_status_ship', ['id' => $invoice->id])) }}">
                                                            {{ Lang::get('message.ship_done') }}
                                                        </a>
                                                    @else
                                                        <i class="text-success fas fa-check ico"></i>
                                                    @endif
                                                </td>
                                                <td class="act">
                                                    <a
                                                        href="{{ URL::to(route('admin.invoice_export.invoice_view', ['id' => $invoice->id])) }}">
                                                        <i class="text-success fas fa-eye ico"></i>
                                                    </a>
                                                    @if ($invoice->status_ship != Lang::get('message.ship_done'))
                                                    <a id="delete-button"
                                                        href="{{ URL::to(route('admin.invoice_export.cancel_order', ['id' => $invoice->id])) }}"
                                                        onclick="return confirm( '{{ Lang::get('message.do_u_cancel') }} {{ $invoice->code_invoice }}?');">
                                                        <i class="text-danger fas fa-ban ico"></i>
                                                    </a>
                                                    @endif
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
