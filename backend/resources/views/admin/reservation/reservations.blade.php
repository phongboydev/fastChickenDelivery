@extends('admin.layout')
@section('admin_content')
    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Danh sách lịch hẹn</h1>
                    </div><!-- /.col -->
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ URL::to(route('screen_admin_home')) }}">Trang
                                    chủ</a></li>
                            <li class="breadcrumb-item active">Danh sách lịch hẹn</li>
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
                                <form action="{{ URL::to(route('admin.reservation.index')) }}" method="GET">
                                    <div class="form-group row">
                                        <label>Chọn thời gian:</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <i class="far fa-calendar-alt"></i>
                                                </span>
                                            </div>
                                            <input type="text" name="date" class="form-control float-right" id="reservation">
                                            <div class="input-group-append">
                                                <button type="submit" class="btn btn-primary">Xác nhận</button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                                <table id="example1" class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Số thứ tự</th>
                                            <th>Ngày</th>
                                            <th>Tên bác sĩ</th>
                                            <th>Tên người đặt</th>
                                            <th>Dịch vụ</th>
                                            <th>Số điện thoại</th>
                                            <th>Thời gian</th>
                                            <th>Trạng thái</th>
                                            <th>Thời gian tái khám</th>
                                            <th>Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $i = 1; ?>
                                        @foreach ($reservations as $key => $reservation)
                                            <tr>
                                                <td>{{ $i++ }}</td>
                                                <td>{{ $reservation->date }}</td>
                                                <td>{{ $reservation->doctor->name }}</td>
                                                <td>{{ $reservation->user->name ?? $reservation->name }}</td>
                                                <td>{{ $reservation->service->name ?? null}}</td>
                                                <td>{{ $reservation->user->phone ?? $reservation->phone }}</td>
                                                <td>{{ $reservation->time }}</td>
                                                <td>{{ $reservation->status == 1 ? 'Xác nhận' : 'Hủy' }}</td>
                                                <td>{{ $reservation->date_recheck }}</td>
                                                <td class="act">
                                                    <div class="row pd-12">
                                                        <a
                                                            href="{{ URL::to(route('admin.reservation.edit', ['reservation' => $reservation->id])) }}">
                                                            <i class="fas fa-edit ico"></i>
                                                        </a>
                                                    </div>
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
