@extends('admin.layout')
@section('admin_content')
    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Sửa dịch vụ</h1>
                    </div><!-- /.col -->
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ URL::to(route('screen_admin_home')) }}">Trang
                                    chủ</a></li>
                            <li class="breadcrumb-item active">Dịch vụ</li>
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
                    <!-- left column -->
                    <div class="col-md-12">
                        <!-- jquery validation -->
                        <div class="card">
                            @if (session('message'))
                                <div class="card-header">
                                    <p class="noti">{{ session('message') }}</p>
                                </div>
                            @endif
                            <!-- /.card-header -->
                            <!-- form start -->
                            <form id="quickForm"
                                action="{{ URL::to(route('admin.service.update', ['service' => $service->id])) }}" enctype="multipart/form-data" method="POST">
                                @csrf
                                <input name="_method" type="hidden" value="PUT">
                                <div class="card-body">
                                    <div class="form-group">
                                        <label for="exampleInputEmail1" class="required">Tên dịch vụ</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fas fa-address-card"></i></span>
                                            </div>
                                            <input type="text" name="name" class="form-control" id="exampleInputEmail1"
                                                value="{{ $service->name }}" placeholder="Nhập vào tên thương hiệu">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="required">Thời gian thực hiện</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="far fa-clock"></i></span>
                                            </div>
                                            <select class="form-control select2bs4" name="work_time">
                                                <option selected="selected" disabled>Chọn thời gian thực hiện</option>
                                                @foreach ($workTimes as $key => $workTime)
                                                    <option @if ((string)date("H:i", strtotime($service->work_time)) == $key) selected @endif value="{{ $key }}">{{ $workTime }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Thời gian tái khám</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                                            </div>
                                            <select class="form-control select2bs4 col-5" name="number_recheck">
                                                <option selected="selected" disabled>Chọn thời gian tái khám</option>
                                                @for ($i = 1; $i <= 30; $i++)
                                                    <option  @if ($service->number_recheck == $i) selected @endif  value="{{ $i }}">{{ $i }}</option>
                                                @endfor
                                            </select>
                                            <select class="form-control select2bs4 col-5" name="unit_recheck">
                                                <option selected="selected" disabled>Chọn đơn vị</option>
                                                <option @if ($service->unit_recheck == 'day') selected @endif value="day">Ngày</option>
                                                <option @if ($service->unit_recheck == 'month') selected @endif value="month">Tháng</option>
                                                <option @if ($service->unit_recheck == 'year') selected @endif value="year">Năm</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Giới thiệu</label>
                                        <textarea id="summernote" name="introduce" placeholder="Nhập vào thông tin giới thiệu dịch vụ"> {{$service->introduce}}</textarea>
                                    </div>
                                    <div class="form-group row pt-3 mt-3">
                                        <div class="col-md-6">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" name="active" class="custom-control-input"
                                                    id="customSwitch1" @if ($service->active) checked @endif>
                                                <label class="custom-control-label" for="customSwitch1">Hoạt
                                                    động</label>
                                            </div>
                                        </div>
                                        <div class="text-right col-md-6">
                                        </div>
                                    </div>
                                </div>
                                <!-- /.card-body -->
                                <div class="card-footer text-center">
                                    <button type="submit" class="btn btn-primary">Lưu</button>
                                </div>
                            </form>
                        </div>
                        <!-- /.card -->
                    </div>
                    <!--/.col (left) -->
                    <!-- right column -->
                    <div class="col-md-6">

                    </div>
                    <!--/.col (right) -->
                </div>
                <!-- /.row -->
            </div><!-- /.container-fluid -->
        </section>
        <!-- /.content -->
    </div>
@endsection
