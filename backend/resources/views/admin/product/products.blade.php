@extends('admin.layout')
@section('admin_content')
    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Danh sách sản phẩm</h1>
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
                                            <th>Tên</th>
                                            <th>Số lượng tồn</th>
                                            <th>Hình ảnh</th>
                                            <th>Hoạt động</th>
                                            @if (auth()->user()->role->name === Config::get('auth.roles.manager'))
                                                <th>Người tạo</th>
                                            @endif
                                            <th>Thời gian tạo</th>
                                            <th>Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $i = 1; ?>
                                        @foreach ($products as $product)
                                            <tr>
                                                <td>{{ $i++ }}</td>
                                                <td>{{ $product->name }}</td>
                                                <td>{{ number_format($product->quantity, 0, ',', '.') }}</td>
                                                <td>
                                                    @if ($product->image)
                                                        <img class="img-ctr"
                                                            src="{{ asset('' . $product->image) }}" />
                                                    @else
                                                        <img class="img-ctr"
                                                            src="{{ asset('' . Config::get('app.image.default')) }}" />
                                                        <img>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($product->active)
                                                        <span class="badge bg-success">Hoạt động</span>
                                                    @else
                                                        <span class="badge bg-danger">Ngừng hoạt động</span>
                                                    @endif
                                                </td>
                                                @if (auth()->user()->role->name === Config::get('auth.roles.manager'))
                                                    <td>{{ $product->user->name }}</td>
                                                @endif
                                                <td>{{ $product->created_at }}</td>
                                                <td>
                                                    <div class="row pd-12">
                                                        <a
                                                            href="{{ URL::to(route('admin.product.show', ['product' => $product->id])) }}">
                                                            <i class="text-success fas fa-eye ico"></i>
                                                        </a>
                                                        <a
                                                            href="{{ URL::to(route('admin.product.edit', ['product' => $product->id])) }}">
                                                            <i class="fas fa-edit ico"></i>
                                                        </a>
                                                        <form
                                                            action="{{ URL::to(route('admin.product.destroy', ['product' => $product->id])) }}"
                                                            method="POST">
                                                            @csrf
                                                            <input name="_method" type="hidden" value="DELETE">
                                                            <button
                                                                onclick="return confirm( '{{ Lang::get('message.do_u_delete') }} {{ $product->name }}?');"
                                                                class="btn-ico" type="submit"><i
                                                                    class="text-danger fas fa-trash-alt ico"></i>
                                                            </button>
                                                        </form>
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
