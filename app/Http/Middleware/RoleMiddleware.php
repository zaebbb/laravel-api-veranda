<?php

namespace App\Http\Middleware;

use App\Models\RoleModel;
use App\Models\User;
use Illuminate\Auth\Middleware\Authenticate as Middleware;

class RoleMiddleware
{
    public function check_role($request, $role = "Администратор")
    {
        // получаем токен из БД
        $token = $request->header("bearer_token");
        // поиск пользователей
        $search_user = User::where("bearer_token", "=", $token)->get();

        // поиск роли по ID из таблицы пользователей
        $search_role = RoleModel::find($search_user[0]->role_id);

        // проверка на наличие роли или ее соответствия
        if(
            ($search_role === null || $search_role->name_role !== $role)
        ){
            return response()
                ->json([
                    "error" => [
                        "code" => 403,
                        "message" => "Forbidden for you"
                    ]
                ])
                ->header("Content_type", "application/json")
                ->setStatusCode(403);
        }

        // пользователь имеет нужный уровень доступа
        return true;
    }
}
