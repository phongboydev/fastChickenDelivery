@extends('admin.layout')
@section('admin_content')
    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Danh sách bác sĩ</h1>
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
                                            <th>Tên bác sĩ</th>
                                            <th>Email</th>
                                            <th>Hình ảnh</th>
                                            <th>Cấp bậc</th>
                                            <th>Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $i = 1; ?>
                                        @foreach ($doctors as $key => $doctor)
                                            <tr>
                                                <td>{{ $i++ }}</td>
                                                <td>{{ $doctor->name }}</td>
                                                <td>{{ $doctor->email }}</td>
                                                <td>
                                                    @if ($doctor->image)
                                                        <img class="img-ctr" src="{{ asset('' . $doctor->image) }}" />
                                                    @else
                                                        <img class="img-ctr" src="{{ asset('' . Config::get('app.image.default')) }}"/><img>
                                                    @endif
                                                </td>
                                                <td>{{ $doctor->levelDoctor->name }}</td>
                                                <td class="act">
                                                    <div class="row pd-12">
                                                        <a
                                                            href="{{ URL::to(route('admin.doctor.edit', ['doctor' => $doctor->id])) }}">
                                                            <i class="fas fa-edit ico"></i>
                                                        </a>
                                                        <form
                                                            action="{{ URL::to(route('admin.doctor.destroy', ['doctor' => $doctor->id])) }}"
                                                                method="POST">
                                                            @csrf
                                                            <input name="_method" type="hidden" value="DELETE">
                                                            <button
                                                                onclick="return confirm( '{{ Lang::get('message.do_u_delete') }} {{ $doctor->name }}?');"
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
