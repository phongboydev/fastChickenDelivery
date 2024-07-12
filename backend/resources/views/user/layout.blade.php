{{--<!DOCTYPE html>--}}
{{--<html lang="en">--}}

{{--<head>--}}
{{--    <title>Mỹ phẩm chính hãng</title>--}}
{{--    <meta charset="utf-8" />--}}
{{--    <meta name="viewport" content="width=device-width, initial-scale=1" />--}}

{{--    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}" />--}}
{{--    <link rel="stylesheet" href="{{ asset('assets/css/templatemo.css') }}" />--}}
{{--    <link rel="stylesheet" href="{{ asset('assets/css/custom.css') }}" />--}}

{{--    <!-- Load fonts style after rendering the layout styles -->--}}
{{--    <link rel="stylesheet"--}}
{{--        href="https://fonts.googleapis.com/css2?family=Roboto:wght@100;200;300;400;500;700;900&display=swap" />--}}
{{--    <link rel="stylesheet" href="{{ asset('assets/css/fontawesome.min.css') }}" />--}}

{{--    <!-- Slick -->--}}
{{--    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/slick.min.css') }}" />--}}
{{--    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/slick-theme.css') }}" />--}}
{{--</head>--}}

{{--<body>--}}
{{--    --}}
{{--    <!-- Header -->--}}
{{--    <nav class="navbar navbar-expand-lg navbar-light shadow">--}}

{{--        <div class="container d-flex justify-content-between align-items-center">--}}
{{--            <a class="navbar-brand text-success logo h1 align-self-center"--}}
{{--                href="{{ URL::to(route('screen_home')) }}">--}}
{{--                Mỹ phẩm MIE--}}
{{--            </a>--}}

{{--            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse"--}}
{{--                data-bs-target="#templatemo_main_nav" aria-controls="navbarSupportedContent" aria-expanded="false"--}}
{{--                aria-label="Toggle navigation">--}}
{{--                <span class="navbar-toggler-icon"></span>--}}
{{--            </button>--}}

{{--            <div class="align-self-center collapse navbar-collapse flex-fill d-lg-flex justify-content-lg-between"--}}
{{--                id="templatemo_main_nav">--}}
{{--                <div class="flex-fill">--}}
{{--                </div>--}}
{{--                <div class="navbar align-self-center d-flex">--}}
{{--                    <div class="d-lg-none flex-sm-fill mt-3 mb-4 col-7 col-sm-auto pr-3">--}}
{{--                        <div class="input-group">--}}
{{--                            <input type="text" class="form-control" id="inputMobileSearch" placeholder="Search ..." />--}}
{{--                            <div class="input-group-text">--}}
{{--                                <i class="fa fa-fw fa-search"></i>--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                    <a class="nav-icon d-none d-lg-inline" href="#" data-bs-toggle="modal"--}}
{{--                        data-bs-target="#templatemo_search">--}}
{{--                        <i class="fa fa-fw fa-search text-dark mr-2"></i>--}}
{{--                    </a>--}}
{{--                    <a class="nav-icon d-none d-lg-inline" href="{{ URL::to(route('search_order')) }}">--}}
{{--                        <i class="fa fa-fw fa-file text-dark mr-2"></i>--}}
{{--                    </a>--}}
{{--                    <a class="nav-icon position-relative text-decoration-none" href="{{ URL::to(route('cart')) }}">--}}
{{--                        <i class="fa fa-fw fa-cart-arrow-down text-dark mr-1"></i>--}}
{{--                        @if (Cart::total() > 0)--}}
{{--                            <span--}}
{{--                                class="position-absolute top-0 left-100 translate-middle badge rounded-pill bg-light text-dark">{{ Cart::content()->groupBy('id')->count() }}</span>--}}
{{--                        @endif--}}
{{--                    </a>--}}
{{--                    <div class="dropdown">--}}
{{--                        @if (Auth::check() && Auth::user()->role->name === Config::get('auth.roles.user'))--}}
{{--                            <a class="nav-icon position-relative text-decoration-none" type="button" id="dropdownMenu2"--}}
{{--                                data-bs-toggle="dropdown">--}}
{{--                                <i class="fa fa-fw fa-user text-dark mr-3"></i>--}}
{{--                            </a>--}}
{{--                            <ul class="dropdown-menu" aria-labelledby="dropdownMenu2">--}}
{{--                                <li>--}}
{{--                                    <a href="{{ URL::to(route('screen_info')) }}" class="dropdown-item"--}}
{{--                                        id="filter_menu" type="button">--}}
{{--                                        {{ auth()->user()->name }}--}}
{{--                                    </a>--}}
{{--                                </li>--}}
{{--                                <li>--}}
{{--                                    <a href="{{ URL::to(route('history_order')) }}" class="dropdown-item"--}}
{{--                                        id="filter_menu" type="button">--}}
{{--                                        Lịch sử đơn hàng--}}
{{--                                    </a>--}}
{{--                                </li>--}}
{{--                                <li>--}}
{{--                                    <a class="dropdown-item" href="{{ URL::to(route('logout')) }}" type="button">--}}
{{--                                        Đăng xuất </a>--}}
{{--                                </li>--}}
{{--                            </ul>--}}
{{--                        @else--}}
{{--                            <a class="nav-icon position-relative text-decoration-none"--}}
{{--                                href="{{ URL::to(route('screen_login')) }}">--}}
{{--                                <i class="fa fa-fw fa-sign-in-alt text-dark mr-3"></i>--}}
{{--                            </a>--}}
{{--                        @endif--}}
{{--                    </div>--}}
{{--                    --}}
{{--                </div>--}}
{{--            </div>--}}
{{--        </div>--}}
{{--    </nav>--}}
{{--    <!-- Close Header -->--}}

{{--    <!-- Modal -->--}}
{{--    <div class="modal fade bg-white" id="templatemo_search" tabindex="-1" role="dialog"--}}
{{--        aria-labelledby="exampleModalLabel" aria-hidden="true">--}}
{{--        <div class="modal-dialog modal-lg" role="document">--}}
{{--            <div class="w-100 pt-1 mb-5 text-right">--}}
{{--                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>--}}
{{--            </div>--}}
{{--            <form action="{{ URL::to(route('search_products')) }}" method="get"--}}
{{--                class="modal-content modal-body border-0 p-0">--}}
{{--                <div class="input-group mb-2">--}}
{{--                    <input type="text" class="form-control" id="inputModalSearch" name="product"--}}
{{--                        placeholder="Nhập vào tên sản phẩm ..." />--}}
{{--                    <button type="submit" class="input-group-text bg-success text-light">--}}
{{--                        <i class="fa fa-fw fa-search text-white"></i>--}}
{{--                    </button>--}}
{{--                </div>--}}
{{--            </form>--}}
{{--        </div>--}}
{{--    </div>--}}
{{--    <!-- End Modal -->--}}




<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Nha khoa NA</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta content="Free HTML Templates" name="keywords">
    <meta content="Free HTML Templates" name="description">

    <!-- Google Web Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Rubik:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Icon Font Stylesheet -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Libraries Stylesheet -->
    <link href="{{ asset('lib/lib/owlcarousel/assets/owl.carousel.min.css') }}" rel="stylesheet">
    <link href="{{ asset('lib/lib/animate/animate.min.css') }}" rel="stylesheet">

    <!-- Customized Bootstrap Stylesheet -->
    <link href="{{ asset('lib/css/bootstrap.min.css') }}" rel="stylesheet">

    <!-- Template Stylesheet -->
    <link href="{{ asset('lib/css/style.css') }}" rel="stylesheet">
</head>

<body>
    <!-- Spinner Start -->
    <div id="spinner" class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
        <div class="spinner"></div>
    </div>
    <!-- Spinner End -->

    <!-- Full Screen Search Start -->
    <div class="modal fade" id="searchModal" tabindex="-1">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content" style="background: rgba(9, 30, 62, .7);">
                <div class="modal-header border-0">
                    <button type="button" class="btn bg-white btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form class="modal-body d-flex align-items-center justify-content-center" action="{{ URL::to(route('search_products')) }}" method="get">
                    <div class="input-group" style="max-width: 600px;">
                        <input type="text" style="background-color: white!important;" name="product" class="form-control bg-transparent border-primary p-3" placeholder="Nhập tên sản phẩm">
                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-search"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Full Screen Search End -->

    <!-- Full Screen Search Start -->
    <div class="modal fade" id="searchOrder" tabindex="-1">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content" style="background: rgba(9, 30, 62, .7);">
                <div class="modal-header border-0">
                    <button type="button" class="btn bg-white btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form class="modal-body d-flex align-items-center justify-content-center" action="{{ URL::to(route('search_order')) }}" method="get">
                    <div class="input-group" style="max-width: 600px;">
                        <input type="text" style="background-color: white!important;" name="code_invoice" class="form-control bg-transparent border-primary p-3" placeholder="Nhập mã đơn hàng">
                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-search"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Full Screen Search End -->

    <!-- Navbar & Carousel Start -->
    <div class="container-fluid position-relative p-0">
        <nav class="navbar navbar-expand-lg navbar-dark px-5 py-3 py-lg-0">
            <a href="{{ URL::to(route('screen_home')) }}" class="navbar-brand p-0">
                <h1 class="m-0"><i class="fa fa-user-tie me-2"></i>Nha Khoa NA</h1>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
                <span class="fa fa-bars"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarCollapse">
                <div class="navbar-nav ms-auto py-0">
                    <a href="{{ URL::to(route('screen_home')) }}" class="nav-item nav-link">Trang chủ</a>
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">Dịch vụ</a>
                        <div class="dropdown-menu m-0" style="width: 450px; min-heigh: 300px;">
                            <div class="row">
                                @if ($services)
                                    @foreach ($services as $key => $service )
                                        <div class="col-6"> <a href="{{ URL::to(route('service_info', ['id' => $service->id])) }}" class="dropdown-item ">{{$service->name}}</a> </div>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">Bác sĩ</a>
                        <div class="dropdown-menu m-0" style="width: 450px;">
                            <div class="row">
                                @if ($doctors)
                                    @foreach ($doctors as $key => $doctor )
                                        <div class="col-6"> <a href="{{ URL::to(route('doctor_info', ['id' => $doctor->id])) }}" class="dropdown-item ">{{$doctor->name}}</a> </div>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">Danh mục sản phẩm</a>
                        <div class="dropdown-menu m-0" style="width: 450px;">
                            <div class="row">
                                @if ($categories)
                                    @foreach ($categories as $key => $category )
                                        <div class="col-6"> <a href="{{ URL::to(route('search_products', ['category' => $category->id])) }}" class="dropdown-item ">{{$category->name}}</a> </div>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </div>
                    <a href="#" class="nav-item nav-link" data-bs-toggle="modal" data-bs-target="#searchOrder">Kiểm tra đơn hàng</a>
                    <a href="{{ URL::to(route('cart')) }}" class="nav-item nav-link">Giỏ hàng</a>
                    @if (Auth::check() && Auth::user()->role->name === Config::get('auth.roles.user'))
                        <div class="nav-item dropdown">
                            <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">{{ auth()->user()->name }}</a>
                            <div class="dropdown-menu m-0">
                                <a href="{{ URL::to(route('screen_info')) }}" class="dropdown-item">Thông tin cá nhân</a>
                                <a href="{{ URL::to(route('history_order')) }}" class="dropdown-item"> Lịch sử đơn hàng  </a>
                                <a href="{{ URL::to(route('logout')) }}" class="dropdown-item"> Đăng xuất </a>
                            </div>
                        </div>
                    @else
                        <a href="{{ URL::to(route('screen_login')) }}" class="nav-item nav-link">Đăng nhập</a>
                    @endif
                </div>
                <button type="button" class="btn text-primary ms-3" data-bs-toggle="modal" data-bs-target="#searchModal"><i class="fa fa-search"></i></button>
            </div>
        </nav>
    </div>

    <!-- Navbar & Carousel End -->

    @yield('user_content')
    <div class="container-fluid bg-dark text-light mt-5 wow fadeInUp" data-wow-delay="0.1s">
        <div class="container">
            <div class="row gx-5">
                <div class="col-lg-4 col-md-6">
                    <div class="d-flex flex-column align-items-center justify-content-center text-center h-100 bg-primary p-4">
                        <a href="index.html" class="navbar-brand">
                            <h1 class="m-0 text-white"><i class="fa fa-user-tie me-2"></i>Nha khoa NA</h1>
                        </a>
                    </div>
                </div>
                <div class="col-lg-8 col-md-6">
                    <div class="row gx-5">
                        <div class="col-lg-4 col-md-12 pt-5 mb-5">
                        </div>
                        <div class="col-lg-4 col-md-12 pt-5 mb-5">
                            <div class="section-title section-title-sm position-relative pb-3 mb-4">
                                <h3 class="text-light mb-0">Liên lạc</h3>
                            </div>
                            <div class="d-flex mb-2">
                                <i class="bi bi-geo-alt text-primary me-2"></i>
                                <p class="mb-0">450 Lê Văn Việt Quận 9</p>
                            </div>
                            <div class="d-flex mb-2">
                                <i class="bi bi-envelope-open text-primary me-2"></i>
                                <p class="mb-0">info@example.com</p>
                            </div>
                            <div class="d-flex mb-2">
                                <i class="bi bi-telephone text-primary me-2"></i>
                                <p class="mb-0">+012 345 67890</p>
                            </div>
                            <div class="d-flex mt-4">
                                <a class="btn btn-primary btn-square me-2" href="#"><i class="fab fa-twitter fw-normal"></i></a>
                                <a class="btn btn-primary btn-square me-2" href="#"><i class="fab fa-facebook-f fw-normal"></i></a>
                                <a class="btn btn-primary btn-square me-2" href="#"><i class="fab fa-linkedin-in fw-normal"></i></a>
                                <a class="btn btn-primary btn-square" href="#"><i class="fab fa-instagram fw-normal"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid text-white" style="background: #061429;">
        <div class="container text-center">
            <div class="row justify-content-end">
                <div class="col-lg-8 col-md-6">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Back to Top -->
    <a href="#" class="btn btn-lg btn-primary btn-lg-square rounded back-to-top"><i class="bi bi-arrow-up"></i></a>


    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('lib/lib/wow/wow.min.js')}}"></script>
    <script src="{{ asset('lib/lib/easing/easing.min.js')}}"></script>
    <script src="{{ asset('lib/lib/waypoints/waypoints.min.js')}}"></script>
    <script src="{{ asset('lib/lib/counterup/counterup.min.js')}}"></script>
    <script src="{{ asset('lib/lib/owlcarousel/owl.carousel.min.js')}}"></script>

    <!-- Template Javascript -->
    <script src="{{ asset('lib/js/main.js')}}"></script>

{{--    <!-- Start Footer -->--}}
{{--    <footer class="bg-dark" id="tempaltemo_footer">--}}
{{--        <div class="container">--}}
{{--            <div class="row">--}}
{{--                <div class="col-md-4 pt-5">--}}
{{--                    <h2 class="h2 text-success border-bottom pb-3 border-light logo">--}}
{{--                        Mỹ phẩm MIE--}}
{{--                    </h2>--}}
{{--                    <ul class="list-unstyled text-light footer-link-list">--}}
{{--                        <li>--}}
{{--                            <i class="fas fa-map-marker-alt fa-fw"></i>--}}
{{--                            450 Lê Văn Việt TP Thủ Đức--}}
{{--                        </li>--}}
{{--                        <li>--}}
{{--                            <i class="fa fa-phone fa-fw"></i>--}}
{{--                            <a class="text-decoration-none" href="tel:010-020-0340">0123456789</a>--}}
{{--                        </li>--}}
{{--                        <li>--}}
{{--                            <i class="fa fa-envelope fa-fw"></i>--}}
{{--                            <a class="text-decoration-none" href="mailto:info@company.com">info@utc2,edu,vn</a>--}}
{{--                        </li>--}}
{{--                    </ul>--}}
{{--                </div>--}}

{{--                <div class="col-md-4 pt-5 ">--}}
{{--                    <h2 class="h2 text-light border-bottom pb-3 border-light">--}}
{{--                        Danh mục--}}
{{--                    </h2>--}}
{{--                    <ul class="list-unstyled text-light footer-link-list">--}}
{{--                        @foreach ($categories as $key => $category)--}}
{{--                            @if ($key < 5)--}}
{{--                                <li><a class="text-decoration-none">{{ $category->name }}</a>--}}
{{--                                </li>--}}
{{--                            @endif--}}
{{--                        @endforeach--}}
{{--                    </ul>--}}
{{--                </div>--}}
{{--                <div class="col-md-4 pt-5">--}}
{{--                    <h2 class="h2 text-light border-bottom pb-3 border-light">--}}
{{--                        Thương hiệu--}}
{{--                    </h2>--}}
{{--                    <ul class="list-unstyled text-light footer-link-list">--}}
{{--                        @foreach ($brands as $key => $brand)--}}
{{--                            @if ($key < 5)--}}
{{--                                <li><a class="text-decoration-none">{{ $brand->name }}</a>--}}
{{--                                </li>--}}
{{--                            @endif--}}
{{--                        @endforeach--}}
{{--                    </ul>--}}
{{--                </div>--}}
{{--            </div>--}}
{{--        </div>--}}
{{--    </footer>--}}
{{--    <!-- End Footer -->--}}
{{--    <!-- Start Script -->--}}
{{--    <script src="{{ asset('assets/js/jquery-1.11.0.min.js') }}"></script>--}}
{{--    <script src="{{ asset('assets/js/jquery-migrate-1.2.1.min.js') }}"></script>--}}
{{--    <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>--}}
{{--    <script src="{{ asset('assets/js/templatemo.js') }}"></script>--}}
{{--    <script src="{{ asset('assets/js/custom.js') }}"></script>--}}
    <script src="https://www.paypalobjects.com/api/checkout.js"></script>
{{--    <!-- End Script -->--}}

{{--    <script src="{{ asset('assets/js/slick.min.js') }}"></script>--}}
    <script>
        $('#carousel-related-product').slick({
            infinite: true,
            arrows: false,
            slidesToShow: 4,
            slidesToScroll: 3,
            dots: true,
            responsive: [{
                    breakpoint: 1024,
                    settings: {
                        slidesToShow: 3,
                        slidesToScroll: 3,
                    },
                },
                {
                    breakpoint: 600,
                    settings: {
                        slidesToShow: 2,
                        slidesToScroll: 3,
                    },
                },
                {
                    breakpoint: 480,
                    settings: {
                        slidesToShow: 2,
                        slidesToScroll: 3,
                    },
                },
            ],
        });
    </script>
    <script>
        var intoMoney = document.getElementById('total').value
        var total = Math.round((intoMoney / 23000) * 100) / 100
        total = parseFloat(total)

        paypal.Button.render({
            // Configure environment
            env: 'sandbox',
            client: {
                sandbox: 'AS_uK5RVtE8H5aiNaPx_HQD_FFax5tPA0_UnXnZddv7_xzq43lbjaRzzXY6xH2m1Ey8emi5mkowbvzxI',
                production: 'demo_production_client_id'
            },
            // Customize button (optional)

            locale: 'en_US',
            style: {
                size: 'medium',
                color: 'gold',
                shape: 'pill',
            },

            // Enable Pay Now checkout flow (optional)
            commit: true,

            // Set up a payment
            payment: function(data, actions) {
                return actions.payment.create({
                    transactions: [{
                        amount: {
                            total: total,
                            currency: 'USD'
                        }
                    }]
                });
            },
            // Execute the payment
            onAuthorize: function(data, actions) {
                var email = document.getElementById("email").value
                var name = document.getElementById("name").value
                var phone = document.getElementById("phone").value
                var address = document.getElementById("address").value

                if (email && name && phone && address) {
                    return actions.payment.execute().then(function() {
                        // Show a confirmation message to the buyer
                        document.getElementById("is_pay_cod").value = 0;
                        document.getElementById("create_order").submit();
                        window.alert('Thank you for your purchase!');
                    });
                } else {
                    window.alert('Bạn chưa nhập đủ thông tin');
                }
            }
        }, '#paypal-button');
    </script>
</body>

</html>
