<?php

namespace App\Http\Controllers;

use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\ErrorValidationReturn;
use App\Http\Middleware\RoleMiddleware;
use App\Models\Orders;
use App\Models\User;
use App\Models\WorkShiftModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CookController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return bool|\Illuminate\Http\JsonResponse|object
     */
    public function get_orders(Request $request)
    {
        // проверка на авторизацию
        $check_auth = new AuthMiddleware();
        $check_auth = $check_auth->check_auth($request);
        if($check_auth !== true){ return $check_auth; }

        // проверка на соответсиве роли пользователя
        $check_role = new RoleMiddleware();
        $check_role = $check_role->check_role($request, "Повар");
        if($check_role !== true){ return $check_role; }

        // поиск необходимых значений
        $search_orders_taken = Orders::
            where("status", "=", "taken")
            ->get();
        $search_orders_preparing = Orders::
            where("status", "=", "preparing")
            ->get();


        // возвращаемое значение сервера
        return response()
            ->json([
                "data" => [$search_orders_taken, $search_orders_preparing]
            ])
            ->setStatusCode(200)
            ->header("Content-Type", "application/json");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param $id
     * @return bool|\Illuminate\Http\JsonResponse|object
     */
    public function update_status(Request $request, $id)
    {
        // проверка на авторизацию
        $check_auth = new AuthMiddleware();
        $check_auth = $check_auth->check_auth($request);
        if($check_auth !== true){ return $check_auth; }

        // проверка на соответсиве роли пользователя
        $check_role = new RoleMiddleware();
        $check_role = $check_role->check_role($request, "Повар");
        if($check_role !== true){ return $check_role; }

        // получение запроса
        $status = $request->status;

        $errors = [];

        if(empty($status)) { $errors["status_required"] = "Поле статуса обязательно к заполнению"; }

        // ответ сервера при ошибке
        $error_validation = new ErrorValidationReturn();
        if(count($errors) !== 0) { return $error_validation->error_validate($errors); }

        // поиск результатов поиска в БД
        $search_order = Orders::find($id);
        $search_shift_work = WorkShiftModel::find($search_order->work_shift_id);
        $search_user = User::where("bearer_token", "=", $request->header("bearer_token"))->get();

        // проверка на корректность ввода статуса
        if(
            ($status !== "preparing" && $search_order->status !== "taken") ||
            ($status !== "ready" && $search_order->status !== "preparing")
        ){
            return response()
                ->json([
                    "error" => [
                        "code" => 403,
                        "message" => "Forbidden! Can't change existing order status"
                    ]
                ])
                ->setStatusCode(403)
                ->header("Content-Type", "application/json");
        }

        // проверка смены на активность
        if($search_shift_work->active !== 1){
            return response()
                ->json([
                    "error" => [
                        "code" => 403,
                        "message" => "You cannot change the order status of a closed shift!"
                    ]
                ])
                ->setStatusCode(403)
                ->header("Content-Type", "application/json");
        }

        // обновление статуса
        $search_order->update([
            "status" => $status
        ]);

        // ответ сервера
        return response()
            ->json([
                "data" => [
                    "id" => $search_order->id,
                    "status" => $status
                ]
            ])
            ->setStatusCode(200)
            ->header("Content-Type", "application/json");

    }

}
