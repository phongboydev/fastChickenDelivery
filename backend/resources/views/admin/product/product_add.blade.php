@extends('admin.layout')
@section('admin_content')
    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Thêm sản phẩm</h1>
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
                            <form id="quickForm" action="{{ URL::to(route('admin.product.store')) }}"
                                enctype="multipart/form-data" method="POST">
                                @csrf
                                <div class="card-body">
                                    <div class="form-group">
                                        <label class="required">Tên sản phẩm</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fas fa-address-card"></i></span>
                                            </div>
                                            <input type="text" name="name" class="form-control"
                                                placeholder="Nhập vào tên sản phẩm">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="required">Thương hiệu</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fas fa-bookmark"></i></span>
                                            </div>
                                            <select class="form-control select2bs4" name="brand">
                                                <option selected="selected" disabled>Chọn 1 thương hiệu</option>
                                                @foreach ($brands as $brand)
                                                    <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="required">Danh mục</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fas fa-th"></i></span>
                                            </div>
                                            <select class="form-control select2bs4" name="category">
                                                <option selected="selected" disabled>Chọn 1 danh mục</option>
                                                @foreach ($categories as $category)
                                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="required">Giá</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fas fa-dollar-sign"></i></span>
                                            </div>
                                            <input type="number" name="price" class="form-control"
                                                placeholder="Nhập vào giá">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="required">Giá khuyến mãi</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fas fa-dollar-sign"></i></span>
                                            </div>
                                            <input type="number" name="price_down" class="form-control"
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
                                            <input type="text" name="date_promotion" class="form-control float-right"
                                                id="reservation">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Mô tả</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fas fa-info-circle"></i></span>
                                            </div>
                                            <textarea class="form-control" name="short_description" rows="2" placeholder="Nhập vào mô tả"></textarea>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Hình ảnh đại diện</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fas fa-image"></i></span>
                                            </div>
                                            <div class="custom-file">
                                                <input type="file" name="image" accept="image/*" class="custom-file-input"
                                                    id="customFile">
                                                <label class="custom-file-label" for="customFile">Chọn 1 hình
                                                    ảnh</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Hình ảnh mô tả</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fas fa-image"></i></span>
                                            </div>
                                            <div class="custom-file">
                                                <input type="file" name="image_detail[]" accept="image/*" class="custom-file-input"
                                                    id="customFile">
                                                <label class="custom-file-label" for="customFile">Chọn 1 hình
                                                    ảnh</label>
                                            </div>
                                        </div>
                                        <div class="input-group mt-3">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fas fa-image"></i></span>
                                            </div>
                                            <div class="custom-file">
                                                <input type="file" name="image_detail[]" accept="image/*" class="custom-file-input"
                                                    id="customFile">
                                                <label class="custom-file-label" for="customFile">Chọn 1 hình
                                                    ảnh</label>
                                            </div>
                                        </div>
                                        <div class="input-group mt-3">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fas fa-image"></i></span>
                                            </div>
                                            <div class="custom-file">
                                                <input type="file" name="image_detail[]" accept="image/*" class="custom-file-input"
                                                    id="customFile">
                                                <label class="custom-file-label" for="customFile">Chọn 1 hình
                                                    ảnh</label>
                                            </div>
                                        </div>
                                        <div class="input-group mt-3">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fas fa-image"></i></span>
                                            </div>
                                            <div class="custom-file">
                                                <input type="file" name="image_detail[]" accept="image/*" class="custom-file-input"
                                                    id="customFile">
                                                <label class="custom-file-label" for="customFile">Chọn 1 hình
                                                    ảnh</label>
                                            </div>
                                        </div>
                                        <div class="input-group mt-3">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fas fa-image"></i></span>
                                            </div>
                                            <div class="custom-file">
                                                <input type="file" name="image_detail[]" accept="image/*" class="custom-file-input"
                                                    id="customFile">
                                                <label class="custom-file-label" for="customFile">Chọn 1 hình
                                                    ảnh</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group row pt-3 mt-3">
                                        <div class="col-md-6">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" name="active" class="custom-control-input"
                                                    id="customSwitch1">
                                                <label class="custom-control-label" for="customSwitch1">Hoạt
                                                    động</label>
                                            </div>
                                        </div>
                                        <div class="text-right col-md-6">
                                            <button type="submit" class="btn btn-primary">Lưu</button>
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
