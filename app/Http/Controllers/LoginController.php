<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\Client;
use Validator;
use Joselfonseca\LighthouseGraphQLPassport\GraphQL\Mutations\BaseAuthResolver;

class LoginController extends Controller
{
    public function login(Request $request){
        
        $client = new Client();
        $client = $client->where('code',$request->input("codeClient"))->first();

        $baseAuth = new BaseAuthResolver();
        $loginPara = [
            'username' => $client->id . '_' . $request->input("username"),
            'password' => $request->input("password")
        ];

        $credentials = $baseAuth->buildCredentials($loginPara);
        $request = Request::create('oauth/token', 'POST', $credentials, [], [], [
            'HTTP_Accept' => 'application/json',
        ]);
        $response = app()->handle($request);
        $decodedResponse = json_decode($response->getContent(), true);
        if ($response->getStatusCode() != 200) {
            return response()->json(['error' => 'Tên đăng nhập hoặc mật khẩu không đúng. Hãy kiểm tra và nhập lại!'], 422);
        }
        return response()->json($decodedResponse);
       
        
    }

    
}