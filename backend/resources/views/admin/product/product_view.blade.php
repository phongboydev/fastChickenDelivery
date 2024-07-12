@extends('admin.layout')
@section('admin_content')
    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Chi tiết sản phẩm</h1>
                    </div><!-- /.col -->
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ URL::to(route('screen_admin_home')) }}">Trang
                                    chủ</a></li>
                            <li class="breadcrumb-item active">Sản phẩm</li>
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
                            <div class="card-body">
                                <div class="form-group">
                                    <label class="required">Tên sản phẩm</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">
                                                <i class="fas fa-address-card"></i>
                                            </span>
                                        </div>
                                        <input type="text" name="name" class="form-control"
                                            placeholder="Nhập vào tên sản phẩm" value="{{ $product->name }}" disabled>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="required">Thương hiệu</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-bookmark"></i></span>
                                        </div>
                                        <select class="form-control select2bs4" name="brand" disabled>
                                            <option>
                                                {{ $product->brand->name }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="required">Danh mục</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-th"></i></span>
                                        </div>
                                        <select class="form-control select2bs4" name="category" disabled>
                                            <option>
                                                {{ $product->category->name }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="required">Giá</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-dollar-sign"></i></span>
                                        </div>
                                        <input type="text" name="price" class="form-control" placeholder="Nhập vào giá"
                                            value="{{ Lang::get('message.before_unit_money') . number_format($product->price, 0, ',', '.') . Lang::get('message.after_unit_money') }}"
                                            disabled>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="required">Giá khuyến mãi</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-dollar-sign"></i></span>
                                        </div>
                                        <input type="text" disabled name="price_down" value="{{ Lang::get('message.before_unit_money') . number_format($product->price_down, 0, ',', '.') . Lang::get('message.after_unit_money') }}"
                                        class="form-control"
                                            placeholder="Nhập vào giá">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Thời gian áp dụng:</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">
                                                <i class="far fa-calendar-alt"></i>
                                            </span>
                                        </div>
                                        <input type="text" disabled name="date_promotion" value="{{$product->start_promotion . " - " . $product->end_promotion}}"class="form-control float-right"
                                            >
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Mô tả</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-info-circle"></i></span>
                                        </div>
                                        <textarea class="form-control" name="short_description" rows="2" placeholder="Nhập vào mô tả"
                                            disabled>{{ $product->short_description }}</textarea>
                                    </div>
                                </div>
                                @if ($product->image)
                                    <div class="form-group">
                                        <label>Hình ảnh</label>
                                        <div class="input-group">

                                        </div>
                                        <img class="img-vie" src="{{ asset('' . $product->image) }}" />
                                    </div>
                                @endif
                                <div class="form-group row pt-3">
                                    <div class="col-md-6">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" name="active" class="custom-control-input"
                                                id="customSwitch1" disabled
                                                @if ($product->active) checked @endif>
                                            <label class="custom-control-label" for="customSwitch1">Hoạt dộng</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- /.card-body -->
                            </form>
                        </div>
                        <!-- /.card -->
                    </div>
                    <!--/.col (left) -->
                </div>
                <!-- /.row -->
            </div><!-- /.container-fluid -->
        </section>
        <!-- /.content -->
    </div>
@endsection
