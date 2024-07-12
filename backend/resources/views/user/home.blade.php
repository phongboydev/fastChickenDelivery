@extends('user.layout')
@section('user_content')

    <!-- Full Screen Search Start -->
    <div class="modal fade" id="reservationModel" tabindex="-1">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content" style="background: rgba(9, 30, 62, .7);">
                <div class="modal-header border-0">
                    <button type="button" class="btn bg-white btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form class="modal-body d-flex align-items-center justify-content-center" method="get" action="{{ URL::to(route('reservation')) }}">
                    <div class="input-group" style="max-width: 600px;">
                        <input type="number" required style="background-color: white!important;" name="phone" class="form-control bg-transparent border-primary p-3" placeholder="Nhập số điện thoại của bạn">
                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-search"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Full Screen Search End -->

    <div id="header-carousel" class="carousel slide carousel-fade" data-bs-ride="carousel">
        <div class="carousel-inner">
            <div class="carousel-item active">
                <img class="w-100" src="{{ asset('lib/img/carousel-1.jpg')}}" alt="Image">
                <div class="carousel-caption d-flex flex-column align-items-center justify-content-center">
                    <div class="p-3" style="max-width: 900px;">
                        <h5 class="text-white text-uppercase mb-3 animated slideInDown">Tận tâm & Uy tín</h5>
                        <h1 class="display-1 text-white mb-md-4 animated zoomIn">Đội ngũ bác sĩ đầu ngành</h1>
                        <button class="btn btn-primary py-md-3 px-md-5 me-3 animated slideInLeft" data-bs-toggle="modal" data-bs-target="#reservationModel">Đặt lịch ngay</button>
                    </div>
                </div>
            </div>
            <div class="carousel-item">
                <img class="w-100" src="{{ asset('lib/img/carousel-2.jpg')}}" alt="Image">
                <div class="carousel-caption d-flex flex-column align-items-center justify-content-center">
                    <div class="p-3" style="max-width: 900px;">
                        <h5 class="text-white text-uppercase mb-3 animated slideInDown">Chuyên nghiệp</h5>
                        <h1 class="display-1 text-white mb-md-4 animated zoomIn">Nâng cao chất lượng nụ cười việt</h1>
                        <button class="btn btn-primary py-md-3 px-md-5 me-3 animated slideInLeft" data-bs-toggle="modal" data-bs-target="#reservationModel">Đặt lịch ngay</button>
                    </div>
                </div>
            </div>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#header-carousel"
            data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#header-carousel"
            data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
    </div>


        <!-- Facts Start -->
    <div class="container-fluid facts py-5 pt-lg-0">
        <div class="container py-5 pt-lg-0">
            <div class="row gx-0">
                <div class="col-lg-4 wow zoomIn" data-wow-delay="0.1s">
                    <div class="bg-primary shadow d-flex align-items-center justify-content-center p-4" style="height: 150px;">
                        <div class="bg-white d-flex align-items-center justify-content-center rounded mb-2" style="width: 60px; height: 60px;">
                            <i class="fa fa-users text-primary"></i>
                        </div>
                        <div class="ps-4">
                            <h5 class="text-white mb-0">Khách hàng</h5>
                            <h1 class="text-white mb-0" data-toggle="counter-up">12345</h1>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 wow zoomIn" data-wow-delay="0.3s">
                    <div class="bg-light shadow d-flex align-items-center justify-content-center p-4" style="height: 150px;">
                        <div class="bg-primary d-flex align-items-center justify-content-center rounded mb-2" style="width: 60px; height: 60px;">
                            <i class="fa fa-check text-white"></i>
                        </div>
                        <div class="ps-4">
                            <h5 class="text-primary mb-0">Đã hoàn thành</h5>
                            <h1 class="mb-0" data-toggle="counter-up">12345</h1>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 wow zoomIn" data-wow-delay="0.6s">
                    <div class="bg-primary shadow d-flex align-items-center justify-content-center p-4" style="height: 150px;">
                        <div class="bg-white d-flex align-items-center justify-content-center rounded mb-2" style="width: 60px; height: 60px;">
                            <i class="fa fa-award text-primary"></i>
                        </div>
                        <div class="ps-4">
                            <h5 class="text-white mb-0">Chứng chỉ</h5>
                            <h1 class="text-white mb-0" data-toggle="counter-up">12345</h1>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

       <div class="container-fluid py-5 wow fadeInUp" data-wow-delay="0.1s">
        <div class="container py-5">
            <div class="section-title text-center position-relative pb-3 mb-4 mx-auto" style="max-width: 600px;">
                <h5 class="fw-bold text-primary text-uppercase">Đội ngũ</h5>
                <h1 class="mb-0">Thông tin về các thành viên của chúng tôi</h1>
            </div>
            <div class="owl-carousel testimonial-carousel wow fadeInUp" data-wow-delay="0.6s">
                @foreach ($doctors as $key => $doctor )
                    @if ($key <=3)
                        <div class="testimonial-item bg-light my-4" style="height: 278px;">
                            <div class="d-flex align-items-center border-bottom pt-5 pb-4 px-5">
                                <img class="img-fluid rounded" src="{{ asset('' . $doctor->image) }}" style="width: 60px; height: 60px;" >
                                <div class="ps-4">
                                    <h4 class="text-primary mb-1">{{$doctor->name}}</h4>
                                    <small class="text-uppercase">{{$doctor->levelDoctor->name}}</small>
                                </div>
                            </div>
                            <div class="pt-4 pb-5 px-5">
                                {{$doctor->description}}
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
    <!-- Testimonial End -->

    <!-- Start Banner Hero -->
{{--    <div id="template-mo-zay-hero-carousel" class="carousel slide" data-bs-ride="carousel">--}}
{{--        <ol class="carousel-indicators">--}}
{{--            <li data-bs-target="#template-mo-zay-hero-carousel" data-bs-slide-to="0" class="active"></li>--}}
{{--            <li data-bs-target="#template-mo-zay-hero-carousel" data-bs-slide-to="1"></li>--}}
{{--            <li data-bs-target="#template-mo-zay-hero-carousel" data-bs-slide-to="2"></li>--}}
{{--        </ol>--}}
{{--        <div class="carousel-inner">--}}
{{--            <?php $i = 1; ?>--}}
{{--            @foreach ($sidebars as $key => $sidebar)--}}
{{--                @if ($i <= 3)--}}
{{--                    @if ($i == 1)--}}
{{--                        <div class="carousel-item active">--}}
{{--                        @else--}}
{{--                            <div class="carousel-item">--}}
{{--                    @endif--}}
{{--                    <?php $i++; ?>--}}
{{--                    <div class="container">--}}
{{--                        <div class="row p-5">--}}
{{--                            <img class="img-fluid" src="{{ asset('' . $sidebar->image) }}" alt="" />--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--        </div>--}}
{{--        @endif--}}
{{--        @endforeach--}}
{{--    </div>--}}
{{--    <a class="carousel-control-prev text-decoration-none w-auto ps-3" href="#template-mo-zay-hero-carousel" role="button"--}}
{{--        data-bs-slide="prev">--}}
{{--        <i class="fas fa-chevron-left"></i>--}}
{{--    </a>--}}
{{--    <a class="carousel-control-next text-decoration-none w-auto pe-3" href="#template-mo-zay-hero-carousel" role="button"--}}
{{--        data-bs-slide="next">--}}
{{--        <i class="fas fa-chevron-right"></i>--}}
{{--    </a>--}}
{{--    </div>--}}
{{--    <!-- End Banner Hero -->--}}

{{--    <!-- Start Categories of The Month -->--}}
{{--    <section class="container py-5" id="home_page">--}}
{{--        <div class="row text-center pt-3">--}}
{{--            <div class="col-lg-6 m-auto">--}}
{{--                <h1 class="h1">Các sản phẩm nổi bật</h1>--}}
{{--                <p>--}}
{{--                    Các sản phẩm bán chạy trong 3 tháng gần đây--}}
{{--                </p>--}}
{{--            </div>--}}
{{--        </div>--}}
{{--        <div class="row">--}}
{{--            <?php $i = 1; ?>--}}
{{--            @foreach ($productsMax as $keyMax => $productMax)--}}
{{--                @if ($i <= 3)--}}
{{--                    @foreach ($products as $key => $product)--}}
{{--                        @if ($product->id == $keyMax)--}}
{{--                            <?php $i++; ?>--}}
{{--                            <div class="col-12 col-md-4 p-5 mt-3">--}}
{{--                                <div class="image-category">--}}
{{--                                    <a href="{{ URL::to(route('detail_product', ['id' => $product->id])) }}"><img--}}
{{--                                            src="{{ asset('' . $product->image) }}"--}}
{{--                                            class="rounded-circle img-fluid border" /></a>--}}
{{--                                    <div>--}}
{{--                                        <h3 class="text-center mt-3 mb-3" style="height: 67px">{{ $product->name ?? null }}</h3>--}}
{{--                                    </div>--}}
{{--                                    <h5 class="text-center mt-3 mb-3">Số lượng bán: {{ $productMax }}</h5>--}}
{{--                                    <p class="text-center"><a class="btn btn-success"--}}
{{--                                            href="{{ URL::to(route('detail_product', ['id' => $product->id])) }}">Xem chi--}}
{{--                                            tiết</a></p>--}}
{{--                                </div>--}}
{{--                            </div>--}}
{{--                        @endif--}}
{{--                    @endforeach--}}
{{--                @endif--}}
{{--            @endforeach--}}
{{--        </div>--}}
{{--    </section>--}}
{{--    <!-- End Categories of The Month -->--}}

{{--    <section class="bg-light">--}}
{{--        <div class="container py-5">--}}
{{--            <div class="row text-center py-3">--}}
{{--                <div class="col-lg-6 m-auto">--}}
{{--                    <h1 class="h1">{{ $brands->first()->name ?? null }}</h1>--}}
{{--                    <p>--}}
{{--                        Một số sản phẩm về thương hiệu {{ $brands->first()->name ?? null }} mà bạn không thể bỏ qua--}}
{{--                    </p>--}}
{{--                </div>--}}
{{--            </div>--}}
{{--            <div class="row">--}}
{{--                <?php $countBra = 0?>--}}
{{--                @if (isset($brands->first()->product) && $brands->first()->product != null)--}}

{{--                @foreach ($brands->first()->product as $key => $pro)--}}
{{--                     @if ($pro->is_deleted == 0 && $pro->active == 1)--}}
{{--                        @if($countBra <3)--}}
{{--                        <?php $countBra++?>--}}
{{--                    <div class="col-12 col-md-4 mb-4">--}}
{{--                        <div class="card">--}}
{{--                            <div class="image-category feature_prod ">--}}
{{--                                <a href="{{ URL::to(route('detail_product', ['id' => $pro->id])) }}">--}}
{{--                                    <img src="@if (isset($pro->image)) {{ asset('' . $pro->image) }} @else {{ asset('' . Config::get('app.image.default')) }} @endif"--}}
{{--                                        class="card-img-top" alt="..." />--}}
{{--                                </a>--}}
{{--                            </div>--}}
{{--                            <div class="card-body">--}}
{{--                                <ul class="list-unstyled d-flex justify-content-between">--}}
{{--                                    <?php $now = Carbon\Carbon::now()->toDateTimeString() ?>--}}
{{--                                    @if ($now <= $pro->end_promotion && $now >= $pro->start_promotion)--}}
{{--                                        <li class="text-right text-dark" style="font-weight: bold!important">--}}
{{--                                            {{ Lang::get('message.before_unit_money') . number_format($pro->price_down, 0, ',', '.') . Lang::get('message.after_unit_money') }}--}}
{{--                                        </li>--}}
{{--                                        <li class="text-right text-dark" style="text-decoration: line-through">--}}
{{--                                            {{ Lang::get('message.before_unit_money') . number_format($pro->price, 0, ',', '.') . Lang::get('message.after_unit_money') }}--}}
{{--                                        </li>--}}
{{--                                    @else--}}
{{--                                        <li class="text-right text-dark" style="font-weight: bold!important">--}}
{{--                                            {{ Lang::get('message.before_unit_money') . number_format($pro->price, 0, ',', '.') . Lang::get('message.after_unit_money') }}--}}
{{--                                        </li>--}}
{{--                                    @endif--}}
{{--                                </ul>--}}
{{--                                <div style="height: 73px;">--}}
{{--                                    <a href="{{ URL::to(route('detail_product', ['id' => $pro->id])) }}"--}}
{{--                                        class="h2 text-decoration-none text-dark">{{ $pro->name ?? null}}</a>--}}
{{--                                </div>--}}
{{--                                <p class="text-muted">{{ $pro->comment->count() }} Review</p>--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                    @endif--}}
{{--                    @endif--}}
{{--                @endforeach--}}
{{--                @endif--}}
{{--            </div>--}}
{{--        </div>--}}
{{--    </section>--}}

{{--    <section class="bg-light">--}}
{{--        <div class="container py-5">--}}
{{--            <div class="row text-center py-3">--}}
{{--                <div class="col-lg-6 m-auto">--}}
{{--                    <h1 class="h1">{{ $categories->first()->name ?? null}}</h1>--}}
{{--                    <p>--}}
{{--                        Một số sản phẩm về danh mục {{ $categories->first()->name ?? null}} mà bạn không thể bỏ qua--}}
{{--                    </p>--}}
{{--                </div>--}}
{{--            </div>--}}
{{--            <div class="row">--}}
{{--                <?php $countCate = 0?>--}}
{{--                @if (isset($categories->first()->product) && $categories->first()->product != null)--}}

{{--                @foreach ($categories->first()->product as $key => $pro)--}}
{{--                    @if ($pro->is_deleted == 0 && $pro->active == 1)--}}
{{--                        @if($countCate <3)--}}
{{--                        <?php $countCate++?>--}}
{{--                        <div class="col-12 col-md-4 mb-4">--}}
{{--                            <div class="card">--}}
{{--                                <div class="image-category feature_prod">--}}
{{--                                    <a href="{{ URL::to(route('detail_product', ['id' => $pro->id])) }}">--}}
{{--                                        <img src="@if (isset($pro->image)) {{ asset('' . $pro->image) }} @else {{ asset('' . Config::get('app.image.default')) }} @endif"--}}
{{--                                            class="card-img-top" alt="..." />--}}
{{--                                    </a>--}}
{{--                                </div>--}}
{{--                                <div class="card-body">--}}
{{--                                    <ul class="list-unstyled d-flex justify-content-between">--}}
{{--                                        <?php $now = Carbon\Carbon::now()->toDateTimeString() ?>--}}
{{--                                        @if ($now <= $pro->end_promotion && $now >= $pro->start_promotion)--}}
{{--                                            <li class="text-right text-dark" style="font-weight: bold!important">--}}
{{--                                                {{ Lang::get('message.before_unit_money') . number_format($pro->price_down, 0, ',', '.') . Lang::get('message.after_unit_money') }}--}}
{{--                                            </li>--}}
{{--                                            <li class="text-right text-dark" style="text-decoration: line-through">--}}
{{--                                                {{ Lang::get('message.before_unit_money') . number_format($pro->price, 0, ',', '.') . Lang::get('message.after_unit_money') }}--}}
{{--                                            </li>--}}
{{--                                        @else--}}
{{--                                            <li class="text-right text-dark" style="font-weight: bold!important">--}}
{{--                                                {{ Lang::get('message.before_unit_money') . number_format($pro->price, 0, ',', '.') . Lang::get('message.after_unit_money') }}--}}
{{--                                            </li>--}}
{{--                                        @endif--}}
{{--                                    </ul>--}}
{{--                                    <div style="height: 73px;">--}}
{{--                                        <a href="{{ URL::to(route('detail_product', ['id' => $pro->id])) }}"--}}
{{--                                            class="h2 text-decoration-none text-dark">{{ $pro->name ?? null }}</a>--}}
{{--                                    </div>--}}
{{--                                    <p class="text-muted">{{ $pro->comment->count() }} Review</p>--}}
{{--                                </div>--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        @endif--}}
{{--                    @endif--}}
{{--                @endforeach--}}
{{--                @endif--}}
{{--            </div>--}}
{{--        </div>--}}
{{--    </section>--}}



@endsection
