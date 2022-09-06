<?php

namespace App\Http\Controllers;

use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\ErrorValidationReturn;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function auth_login(Request $request)
    {
        $login = $request->login;
        $password = $request->password;

        $errors = [];

        if(empty($login)) $errors["login_required"] = "Login required";
        if(empty($password)) $errors["password_required"] = "Password required";

        $error = new ErrorValidationReturn();

        if(count($errors) !== 0) { return $error->error_validate($errors); }

        $search_user = User::where("login", "=", $login)->get();

        if(count($search_user) === 0 || !password_verify($password, $search_user[0]->password)){
            return response()
                ->json([
                    "error" => [
                        "code" => 401,
                        "message" => "Authentication failed"
                    ]
                ])
                ->header("Content-Type", "application/json")
                ->setStatusCode(401);
        }

        $token = "Bearer " . Str::random(60);

        $search_user[0]->update([
            "bearer_token" => $token
        ]);

        if($search_user[0]->role_id === 1){ $token .= "a"; }
        else if($search_user[0]->role_id === 2){ $token .= "o"; }
        else { $token .= "c"; }

        return response()
            ->json([
                "data" => [
                    "user_token" => $token
                ]
            ])
            ->header("Content-Type", "application/json")
            ->setStatusCode(200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool|\Illuminate\Http\JsonResponse|object
     */
    public function auth_exit(Request $request)
    {
        $check_auth = new AuthMiddleware();
        $check_auth = $check_auth->check_auth($request);

        if($check_auth !== true){ return $check_auth; }

        return response()
            ->json([
                "data" => [
                    "message" => "logout"
                ]
            ])
            ->setStatusCode(200)
            ->header("Content-Type", "application/json");
    }

}
