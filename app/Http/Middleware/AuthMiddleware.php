<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Auth\Middleware\Authenticate as Middleware;

class AuthMiddleware
{
    public function check_auth($request)
    {
        // получаем токен из БД
        $token = $request->header("bearer_token");
        // поиск пользователей
        $search_auth = User::where("bearer_token", "=", $token)->get();
        // проверка на наличие токена и количество найденный пользователей
        if(empty($token) || count($search_auth) === 0){
            return response()
                ->json([
                    "error" => [
                        "code" => 403,
                        "message" => "Login failed"
                    ]
                ])
                ->header("Content_type", "application/json")
                ->setStatusCode(403);
        }

        // пользователь авторизован
        return true;
    }
}
