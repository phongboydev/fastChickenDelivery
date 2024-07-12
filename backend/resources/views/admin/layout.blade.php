@php use Illuminate\Support\Facades\Request; @endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin</title>
    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="{{ asset('plugins/fontawesome-free/css/all.min.css') }}">
    <!-- Ionicons -->
    <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
    <!-- Tempusdominus Bootstrap 4 -->
    <link rel="stylesheet" href="{{ asset('plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css') }}">
    <!-- iCheck -->
    <link rel="stylesheet" href="{{ asset('plugins/icheck-bootstrap/icheck-bootstrap.min.css') }}') }}">
    <!-- JQVMap -->
    <link rel="stylesheet" href="{{ asset('plugins/jqvmap/jqvmap.min.css') }}">
    <!-- Theme style -->
    <link rel="stylesheet" href="{{ asset('dist/css/adminlte.min.css') }}">
    <!-- overlayScrollbars -->
    <link rel="stylesheet" href="{{ asset('plugins/overlayScrollbars/css/OverlayScrollbars.min.css') }}">
    <!-- Daterange picker -->
    <link rel="stylesheet" href="{{ asset('plugins/daterangepicker/daterangepicker.css') }}">
    <!-- summernote -->
    <link rel="stylesheet" href="{{ asset('plugins/summernote/summernote-bs4.min.css') }}">
    <!-- My style -->
    <link rel="stylesheet" href="{{ asset('css/mystyle.css') }}">
    <!-- Data table -->
    <link rel="stylesheet" href="{{ asset('plugins/datatables-bs4/css/dataTables.bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/datatables-responsive/css/responsive.bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/datatables-buttons/css/buttons.bootstrap4.min.css') }}">
    <!-- Select2 -->
    <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/summernote/summernote-bs4.min.css') }}">
    <!-- daterange picker -->
    <link rel="stylesheet" href="{{ asset('plugins/daterangepicker/daterangepicker.css') }}">
    @vite('resources/css/app.css')

</head>
<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">
        <!-- Preloader -->
        <div class="preloader flex-column justify-content-center align-items-center">
            <img class="animation__shake" src="{{ asset('dist/img/AdminLTELogo.png') }}" alt="AdminLTELogo" height="60" width="60">
        </div>

        <!-- Navbar -->
        <nav class="main-header navbar navbar-expand navbar-white navbar-light">
             <!-- Left navbar links -->
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
                </li>
            </ul>
            <!-- Right navbar links -->
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link" data-widget="fullscreen" href="#" role="button">
                        <i class="fas fa-expand-arrows-alt"></i>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ URL::to(route('admin_logout')) }}" role="button">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </li>
            </ul>
        </nav>


        <aside class="main-sidebar sidebar-dark-primary elevation-4" style=" background-color: black; ">
            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Sidebar user panel (optional) -->
                <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                    <div class="info">
                        <a class="d-block">{{ auth()->user()->name }}</a>
                    </div>
                </div>
                <!-- SidebarSearch Form -->
                {{--            <div class="form-inline">--}}
                {{--                <div class="input-group" data-widget="sidebar-search">--}}
                {{--                    <input class="form-control form-control-sidebar" type="search" placeholder="Tìm kiếm"--}}
                {{--                        aria-label="Search">--}}
                {{--                    <div class="input-group-append">--}}
                {{--                        <button class="btn btn-sidebar">--}}
                {{--                            <i class="fas fa-search fa-fw"></i>--}}
                {{--                        </button>--}}
                {{--                    </div>--}}
                {{--                </div>--}}
                {{--            </div>--}}
                <!-- Sidebar Menu -->
                <?php $path =  Request::path() ?>
                <nav class="mt-2">
                    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                        <li class="nav-item">
                            <a href="{{ URL::to(route('screen_admin_home')) }}" class="nav-link">
                                <i class="nav-icon fas fa-tachometer-alt"></i>
                                <p>Trang chủ</p>
                            </a>
                        </li>
                        <li class="nav-header">Thông tin</li>
                        <li class="nav-item {{strpos($path, 'brand') ? 'menu-open' : ''}}">
                            <a href="#" class="nav-link {{strpos($path, 'brand') ? 'active' : ''}}">
                                <i class="nav-icon fas fa-bookmark"></i>
                                <p>
                                    Thương hiệu
                                    <i class="right fas fa-angle-left"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="{{ URL::to(route('admin.brand.index')) }}" class="nav-link {{$path ==  'admin/brand' ? 'active' : ''}}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Danh sách thương hiệu</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ URL::to(route('admin.brand.create')) }}" class="nav-link {{$path ==  'admin/brand/create' ? 'active' : ''}}" >
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Thêm thương hiệu</p>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <li class="nav-item {{strpos($path, 'category') ? 'menu-open' : ''}}">
                            <a href="#" class="nav-link {{strpos($path, 'category') ? 'active' : ''}}">
                                <i class="nav-icon fas fa-th"></i>
                                <p>
                                    Danh mục
                                    <i class="right fas fa-angle-left"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="{{ URL::to(route('admin.category.index')) }}" class="nav-link {{$path ==  'admin/category' ? 'active' : ''}}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Danh sách danh mục</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ URL::to(route('admin.category.create')) }}" class="nav-link {{$path ==  'admin/category/create' ? 'active' : ''}}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Thêm danh mục</p>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <li class="nav-item {{strpos($path, '/product') ? 'menu-open' : ''}}">
                            <a href="#" class="nav-link {{strpos($path, '/product') ? 'active' : ''}}">
                                <i class="nav-icon fas fa-bars"></i>
                                <p>
                                    Sản phẩm
                                    <i class="right fas fa-angle-left"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="{{ URL::to(route('admin.product.index')) }}" class="nav-link {{$path ==  'admin/product' ? 'active' : ''}}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Danh sách sản phẩm</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ URL::to(route('admin.product.create')) }}" class="nav-link {{$path ==  'admin/product/create' ? 'active' : ''}}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Thêm sản phẩm</p>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <li class="nav-item {{strpos($path, 'doctor') ? 'menu-open' : ''}}">
                            <a href="#" class="nav-link {{strpos($path, 'doctor') ? 'active' : ''}}">
                                <i class="nav-icon fas fa-user-md"></i>
                                <p>
                                    Bác sĩ
                                    <i class="right fas fa-angle-left"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="{{ URL::to(route('admin.doctor.index')) }}" class="nav-link {{$path ==  'admin/doctor' ? 'active' : ''}}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Danh sách bác sĩ</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ URL::to(route('admin.doctor.create')) }}" class="nav-link {{$path ==  'admin/doctor/create' ? 'active' : ''}}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Thêm bác sĩ</p>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <li class="nav-item {{strpos($path, 'service') ? 'menu-open' : ''}}">
                            <a href="#" class="nav-link {{strpos($path, 'service') ? 'active' : ''}}">
                                <i class="nav-icon fas fa-server"></i>
                                <p>
                                    Dịch vụ
                                    <i class="right fas fa-angle-left"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="{{ URL::to(route('admin.service.index')) }}" class="nav-link {{$path ==  'admin/service' ? 'active' : ''}}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Danh sách dịch vụ</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ URL::to(route('admin.service.create')) }}" class="nav-link {{$path ==  'admin/service/create' ? 'active' : ''}}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Thêm dịch vụ</p>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <li class="nav-item {{strpos($path, 'reservation') ? 'menu-open' : ''}}">
                            <a href="{{ URL::to(route('admin.reservation.index')) }}" class="nav-link {{$path ==  'admin/reservation' ? 'active' : ''}}">
                                <i class="nav-icon fas fa-calendar-alt"></i>
                                <p>Quản lý lịch hẹn</p>
                            </a>
                        </li>
                        <li class="nav-header">Hóa đơn</li>
                        <li class="nav-item {{strpos($path, 'invoice_import') ? 'menu-open' : ''}}">
                            <a href="#" class="nav-link {{strpos($path, 'invoice_import') ? 'active' : ''}}">
                                <i class="nav-icon fas fa-file-download"></i>
                                <p>
                                    Hóa đơn nhập
                                    <i class="right fas fa-angle-left"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="{{ URL::to(route('admin.invoice_import.index')) }}" class="nav-link  {{$path ==  'admin/invoice_import' ? 'active' : ''}}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Danh sách hóa đơn</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ URL::to(route('admin.invoice_import.create')) }}" class="nav-link {{$path ==  'admin/invoice_import/create' ? 'active' : ''}}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Nhập hàng</p>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <li class="nav-item {{strpos($path, 'invoice') ? 'menu-open' : ''}}">
                            <a href="{{ URL::to(route('admin.invoice_export.order')) }}" class="nav-link {{$path ==  'admin/order' ? 'active' : ''}}">
                                <i class="nav-icon fas fa-paste"></i>
                                <p>Đơn đặt hàng</p>
                            </a>
                        </li>
                        <li class="nav-item {{strpos($path, 'invoice') ? 'menu-open' : ''}}">
                            <a href="{{ URL::to(route('admin.invoice_export.invoice')) }}" class="nav-link {{$path ==  'admin/invoice' ? 'active' : ''}}">
                                <i class="nav-icon fas fa-file-export"></i>
                                <p>Hóa đơn bán</p>
                            </a>
                        </li>
                        <li class="nav-item {{strpos($path, 'invoice') ? 'menu-open' : ''}}">
                            <a href="{{ URL::to(route('admin.invoice_export.close_orders')) }}" class="nav-link {{$path ==  'admin/close-order' ? 'active' : ''}}">
                                <i class="nav-icon fas fa-times-circle"></i>
                                <p>Đơn đã hủy</p>
                            </a>
                        </li>
                        <li class="nav-header">Thống kê</li>
                        <li class="nav-item {{strpos($path, 'statistical-products') ? 'menu-open' : ''}}">
                            <a href="{{ URL::to(route('admin.statistical.products')) }}" class="nav-link {{$path ==  'admin/statistical-products' ? 'active' : ''}}">
                                <i class="nav-icon fas fa-chart-bar"></i>
                                <p>Thống kê sản phẩm</p>
                            </a>
                        </li>
                        <li class="nav-item {{strpos($path, 'statistical-invoices') ? 'menu-open' : ''}}">
                            <a href="{{ URL::to(route('admin.statistical.invoices')) }}" class="nav-link {{$path ==  'admin/statistical-invoices' ? 'active' : ''}}">
                                <i class="nav-icon fas fa-file-invoice-dollar"></i>
                                <p>Thống kê hóa đơn</p>
                            </a>
                        </li>
                        <li class="nav-item {{strpos($path, 'statistical-users') ? 'menu-open' : ''}}">
                            <a href="{{ URL::to(route('admin.statistical.users')) }}" class="nav-link {{$path ==  'admin/statistical-invoices' ? 'active' : ''}}">
                                <i class="nav-icon fas fa-id-card-alt"></i>
                                <p>Thống kê khách hàng</p>
                            </a>
                        </li>
                        @if (auth()->user()->role->name === Config::get('auth.roles.manager'))
                            <li class="nav-header">Tài khoản</li>
                            <li class="nav-item">
                                <a href="{{ URL::to(route('admin.account.index')) }}" class="nav-link">
                                    <i class="nav-icon fas fa-address-book"></i>
                                    <p>Danh sách tài khoản</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ URL::to(route('admin.account.create')) }}" class="nav-link">
                                    <i class="nav-icon fas fa-user-plus"></i>
                                    <p>Cấp tài khoản mới</p>
                                </a>
                            </li>
                            {{--                        <li class="nav-item">--}}
                            {{--                            <a href="#" class="nav-link">--}}
                            {{--                                <i class="nav-icon fas fa-sliders-h"></i>--}}
                            {{--                                <p>--}}
                            {{--                                    Slidebar--}}
                            {{--                                    <i class="right fas fa-angle-left"></i>--}}
                            {{--                                </p>--}}
                            {{--                            </a>--}}
                            {{--                            <ul class="nav nav-treeview">--}}
                            {{--                                <li class="nav-item">--}}
                            {{--                                    <a href="{{ URL::to(route('admin.sidebar.index')) }}" class="nav-link">--}}
                            {{--                                        <i class="far fa-circle nav-icon"></i>--}}
                            {{--                                        <p>Danh sách slider</p>--}}
                            {{--                                    </a>--}}
                            {{--                                </li>--}}
                            {{--                                <li class="nav-item">--}}
                            {{--                                    <a href="{{ URL::to(route('admin.sidebar.create')) }}" class="nav-link">--}}
                            {{--                                        <i class="far fa-circle nav-icon"></i>--}}
                            {{--                                        <p>Thêm Slider</p>--}}
                            {{--                                    </a>--}}
                            {{--                                </li>--}}
                            {{--                            </ul>--}}
                            {{--                        </li>--}}
                        @endif
                    </ul>
                </nav>
                <!-- /.sidebar-menu -->
            </div>
            <!-- /.sidebar -->
        </aside>
        <!-- /.navbar -->
        @yield('admin_content')
        <!-- /.content-wrapper -->

        <!-- Control Sidebar -->
{{--        <aside class="control-sidebar control-sidebar-dark">--}}
{{--            <!-- Control sidebar content goes here -->--}}
{{--        </aside>--}}
        <!-- /.control-sidebar -->
        @vite('resources/js/app.js')go

    </div>
    <!-- ./wrapper -->
    <!-- jQuery -->
    <script src="{{ asset('plugins/jquery/jquery.min.js') }}"></script>
    <!-- jQuery UI 1.11.4 -->
    <script src="{{ asset('plugins/jquery-ui/jquery-ui.min.j') }}s"></script>
    <!-- Resolve conflict in jQuery UI tooltip with Bootstrap tooltip -->
    <script>
        $.widget.bridge('uibutton', $.ui.button)
    </script>
    <script>
  $(function () {
    // Summernote
    $('#summernote').summernote()

    // CodeMirror
    CodeMirror.fromTextArea(document.getElementById("codeMirrorDemo"), {
      mode: "htmlmixed",
      theme: "monokai"
    });
  })
</script>
    <!-- Bootstrap 4 -->
    <script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <!-- ChartJS -->
    <script src="{{ asset('plugins/chart.js/Chart.min.js') }}"></script>
    <!-- Sparkline -->
    <script src="{{ asset('plugins/sparklines/sparkline.js') }}"></script>
    <!-- JQVMap -->
    <script src="{{ asset('plugins/jqvmap/jquery.vmap.min.js') }}"></script>
    <script src="{{ asset('plugins/jqvmap/maps/jquery.vmap.usa.js') }}"></script>
    <!-- jQuery Knob Chart -->
    <script src="{{ asset('plugins/jquery-knob/jquery.knob.min.js') }}"></script>
    <!-- daterangepicker -->
    <script src="{{ asset('plugins/moment/moment.min.js') }}"></script>
    <script src="plugins/daterangepicker/daterangepicker.js') }}"></script>
    <!-- Tempusdominus Bootstrap 4 -->
    <script src="{{ asset('plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js') }}"></script>
    <!-- Summernote -->
    <script src="{{ asset('plugins/summernote/summernote-bs4.min.js') }}"></script>
    <!-- overlayScrollbars -->
    <script src="{{ asset('plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js') }}"></script>
    <!-- AdminLTE App -->
    <script src="{{ asset('dist/js/adminlte.js') }}"></script>
    <!-- AdminLTE for demo purposes -->
    <script src="{{ asset('dist/js/demo.js') }}"></script>
    <!-- AdminLTE dashboard demo (This is only for demo purposes) -->
    <script src="{{ asset('dist/js/pages/dashboard.js') }}"></script>
    <!-- jquery-validation -->
    <script src="{{ asset('plugins/jquery-validation/jquery.validate.min.js') }}"></script>
    <script src="{{ asset('plugins/jquery-validation/additional-methods.min.js') }}"></script>
    <!-- Data table -->
    <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('plugins/datatables-bs4/js/dataTables.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('plugins/datatables-responsive/js/dataTables.responsive.min.js') }}"></script>
    <script src="{{ asset('plugins/datatables-responsive/js/responsive.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('plugins/datatables-buttons/js/dataTables.buttons.min.js') }}"></script>
    <script src="{{ asset('plugins/datatables-buttons/js/buttons.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('plugins/jszip/jszip.min.js') }}"></script>
    <script src="{{ asset('plugins/pdfmake/pdfmake.min.js') }}"></script>
    <script src="{{ asset('plugins/pdfmake/vfs_fonts.js') }}"></script>
    <script src="{{ asset('plugins/datatables-buttons/js/buttons.html5.min.js') }}"></script>
    <script src="{{ asset('plugins/datatables-buttons/js/buttons.print.min.js') }}"></script>
    <script src="{{ asset('plugins/datatables-buttons/js/buttons.colVis.min.js') }}"></script>
    <!-- Select2 -->
    <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('plugins/summernote/summernote-bs4.min.js') }}"></script>
    <!-- date-range-picker -->
    <script src="{{ asset('plugins/daterangepicker/daterangepicker.js') }}"></script>
    <!-- Bootstrap4 Duallistbox -->
    <script src=".{{ asset('plugins/bootstrap4-duallistbox/jquery.bootstrap-duallistbox.min.js') }}"></script>
    <!-- InputMask -->
    <script src="{{ asset('plugins/moment/moment.min.js') }}"></script>
    <script src="{{ asset('plugins/inputmask/jquery.inputmask.min.js') }}"></script>
    <!-- My script -->
    <script src="{{ asset('js/index.js') }}"></script>


</body>

</html>
