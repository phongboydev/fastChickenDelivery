<?php
namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;

class SignApiHelper
{
	public static function loginSignApi()
    {
        $data_login = array(
            "TenDangNhap" => config('vpo.esoc.username'),
            "MatKhau" => config('vpo.esoc.password'),
            "GhiNho"=> true
        );
        $response = Http::post(config('vpo.esoc.url') . Constant::API_LOGIN, $data_login);
        if( $response->successful() ) {
        	return $response['Data'];
        }
        return null;
    }
    public static function randomString( $length = 11 ) {
	    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
	    $size = strlen( $chars );
	    $str = '';
	    for( $i = 0; $i < $length; $i++ ) {
	        $str .= $chars[ rand( 0, $size - 1 ) ];
	    }
	    return $str;
	}
}
