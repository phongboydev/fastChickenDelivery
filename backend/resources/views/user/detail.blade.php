@extends('user.layout')
@section('user_content')
    <div class="container-fluid bg-primary py-5 bg-header" style="margin-bottom: 90px;">
        <div class="row py-5">
            <div class="col-12 pt-lg-5 mt-lg-5 text-center">
                <h1 class="display-4 text-white animated zoomIn">Thông tin cá nhân</h1>
            </div>
        </div>
    </div>
    <!-- Open Content -->
    <section id="user_detail">
        <div class="container rounded bg-white mt-5 mb-5" style="background-color: lightskyblue!important;">
            <div class="row">
                <div class="col-md-1 border-right"></div>
                <div class="col-md-6 border-right">
                    <div class="p-3 py-5">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="text-right">Thông tin</h4>
                        </div>
                        <form action="{{ URL::to(route('update_info')) }}" method="POST">
                            @csrf
                            <div class="row mt-2">
                                <div class="col-md-12"><label class="labels">Họ và tên</label>
                                    <input type="text" name="name" class="form-control" required placeholder="Nhập vào họ và tên" value="{{$user->name}}">
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-12"><label class="labels">Số điện thoại</label>
                                    <input type="text" name="phone" class="form-control" required  placeholder="Nhập vào số điện thoại" value="{{$user->phone}}">
                                </div>
                                <div class="col-md-12"><label class="labels">Địa chỉ</label>
                                    <input type="text" name="address" class="form-control" placeholder="Nhập vào địa chỉ" value="{{$user->address}}">
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-5"><label class="labels">Email</label>
                                    <p>{{$user->email}}</p>
                                </div>
                                <div class="col-md-6"><label class="labels">Tên đăng nhập</label>
                                    <p>{{$user->username}}</p>
                                </div>
                            </div>
                            <div class="mt-5 text-center">
                                <button class="btn btn-primary profile-button" type="submit">
                                    Lưu thông tin
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 py-5">
                        <form action="{{ URL::to(route('change_password')) }}" method="POST">
                            @csrf
                            <div class="d-flex justify-content-between align-items-center experience">
                                <h4 class="text-right">Thay đổi mật khẩu</h4>
                            </div><br>
                            <div class="col-md-12"><label class="labels">Mật khẩu cũ</label>
                                <input type="password" name="old_password" required class="form-control" placeholder="Mật khẩu cũ">
                            </div>
                            <br>
                            <div class="col-md-12"><label class="labels">Mật khẩu mới</label>
                                <input type="password" name="password" required class="form-control" placeholder="Nhập mật khẩu mới">
                            </div>
                            <br>
                            <div class="col-md-12"><label class="labels">Xác nhận mật khẩu</label>
                                <input type="password" name="confirm_password" required class="form-control" placeholder="Xác nhận mật khẩu">
                            </div>
                            <br>
                            <button class="btn btn-success profile-button" type="submit">
                                Đổi mật khẩu
                            </button>
                        </form>
                    </div>
                    @if (session('message'))
                        <p class="noti">{{ session('message') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </section>
    <!-- Close Banner -->
@endsection
