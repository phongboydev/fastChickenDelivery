@extends('admin.layout')
@section('admin_content')
    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Danh sách danh mục</h1>
                    </div><!-- /.col -->
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ URL::to(route('screen_admin_home')) }}">Trang
                                    chủ</a></li>
                            <li class="breadcrumb-item active">Danh mục sản phẩm</li>
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
                                            <th>Tên danh mục</th>
                                            <th>Hình ảnh</th>
                                            @if (auth()->user()->role->name === Config::get('auth.roles.manager'))
                                                <th>Người tạo</th>
                                            @endif
                                            <th>Thời gian tạo</th>
                                            <th>Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $i = 1; ?>
                                        @foreach ($categories as $key => $category)
                                            <tr>
                                                <td>{{ $i++ }}</td>
                                                <td>{{ $category->name }}</td>
                                                <td>
                                                    @if ($category->image)
                                                        <img class="img-ctr"
                                                            src="{{ asset('' . $category->image) }}" />
                                                    @else
                                                        <img class="img-ctr"
                                                            src="{{ asset('' . Config::get('app.image.default')) }}" />
                                                        <img>
                                                    @endif
                                                </td>
                                                @if (auth()->user()->role->name === Config::get('auth.roles.manager'))
                                                    <td>{{ $category->user->name }}</td>
                                                @endif
                                                <td>{{ $category->created_at }}</td>
                                                <td class="act">
                                                    <a
                                                        href="{{ URL::to(route('admin.category.edit', ['category' => $category->id])) }}">
                                                        <i class="fas fa-edit ico"></i>
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
