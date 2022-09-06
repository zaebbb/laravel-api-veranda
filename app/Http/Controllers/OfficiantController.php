<?php

namespace App\Http\Controllers;

use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\ErrorValidationReturn;
use App\Http\Middleware\RoleMiddleware;
use App\Models\OrderPositions;
use App\Models\Orders;
use App\Models\PositionsModel;
use App\Models\TablesModel;
use App\Models\User;
use App\Models\WorkShiftModel;
use App\Models\WorkShiftUserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfficiantController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return bool|JsonResponse|object
     */
    public function create_order(Request $request)
    {
        // проверка на авторизацию
        $check_auth = new AuthMiddleware();
        $check_auth = $check_auth->check_auth($request);
        if($check_auth !== true){ return $check_auth; }

        // проверка на соответсиве роли пользователя
        $check_role = new RoleMiddleware();
        $check_role = $check_role->check_role($request, "Официант");
        if($check_role !== true){ return $check_role; }

        // получение данных из запроса
        $work_shift_id = $request->work_shift_id;
        $table_id = $request->table_id;
        $number_of_person = $request->number_of_person;

        $errors = [];

        // поиск значений в таблицах
        $search_table = TablesModel::find($table_id);
        $search_user = User::where("bearer_token", "=", $request->header("bearer_token"))->get();
        $search_work_shift = WorkShiftModel::find($work_shift_id);
        $search_active_user_in_work_shift = WorkShiftUserModel::
        where("work_shift_id", "=", $work_shift_id)
            ->where("user_id", "=", $search_user[0]->id)
            ->get();

        // валидация полей
        if(empty($work_shift_id)) { $errors["work_shift_id_required"] = "Поле идентификатор смены обязательно для заполнения"; }
        if(empty($table_id)) { $errors["table_id_required"] = "Поле идентификатор столика обязательно для заполнения"; }
        if($search_table === null) { $errors["table_not_found"] = "Столик не найден"; }

        // ответ сервера при ошибке
        $error_validation = new ErrorValidationReturn();
        if(count($errors) !== 0) { return $error_validation->error_validate($errors); }

        // проверка на существоание активной смены
        if($search_work_shift === null || $search_work_shift->active !== 1){
            return response()
                ->json([
                    "error" => [
                        "code" => 403,
                        "message" => "Forbidden. The shift must be active!"
                    ]
                ])
                ->setStatusCode(403)
                ->header("Content-Type", "application/json");
        }

        // проверка на сушествование пользователя на смене
        if(count($search_active_user_in_work_shift) === 0){
            return response()
                ->json([
                    "error" => [
                        "code" => 403,
                        "message" => "Forbidden. You don't work this shift!"
                    ]
                ])
                ->setStatusCode(403)
                ->header("Content-Type", "application/json");
        }

        // создание заказа
        $create_order = Orders::create([
            "work_shift_id" => $search_work_shift->id,
            "table" => $search_table->name_table,
            "shift_worker" => $search_user[0]->name,
            "status" => "Принят",
            "price" => 0,
            "number_of_person" => $number_of_person,
            "more_menu_info" => null
        ]);

        // ответ сервера
        return response()
            ->json([
                "data" => [
                    "id" => $create_order->id,
                    "table" => $create_order->table,
                    "shift_workers" => $create_order->shift_worker,
                    "create_at" => $create_order->created_at,
                    "status" => $create_order->status,
                    "price" => $create_order->price,
                ]
            ])
            ->setStatusCode(201)
            ->header("Content-Type", "application/json");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param Request $request
     * @param $id
     * @return bool|JsonResponse|object
     */
    public function get_order(Request $request, $id)
    {
        // проверка на авторизацию
        $check_auth = new AuthMiddleware();
        $check_auth = $check_auth->check_auth($request);
        if($check_auth !== true){ return $check_auth; }

        // проверка на соответсиве роли пользователя
        $check_role = new RoleMiddleware();
        $check_role = $check_role->check_role($request, "Официант");
        if($check_role !== true){ return $check_role; }

        // получение заказа, пользователя, и позиций из БД
        $search_order = Orders::find($id);
        $search_user = User::where("bearer_token", "=", $request->header("bearer_token"))->get();
        $search_position = OrderPositions::where("order_id", "=", $id)->get();

        // проверка на доступ пользователя к данному заказу
        if($search_order !== null && $search_order->shift_worker !== $search_user[0]->name){
            return response()
                ->json([
                    "error" => [
                        "code" => 403,
                        "message" => "Forbidden. You did not accept this order!"
                    ]
                ])
                ->setStatusCode(403)
                ->header("Content-Type", "application/json");
        }

        // возвращение ответа сервера
        return response()
            ->json([
                "id" => $search_order->id,
                "table" => $search_order->table,
                "shift_workers" => $search_order->shift_worker,
                "create_at" => $search_order->created_at,
                "status" => $search_order->status,
                "price" => $search_order->price,
                "positions" => $search_position
            ])
            ->setStatusCode(200)
            ->header("Content-Type", "application/json");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @param $id
     * @return bool|JsonResponse|object
     */
    public function update_status(Request $request, $id)
    {
        // проверка на авторизацию
        $check_auth = new AuthMiddleware();
        $check_auth = $check_auth->check_auth($request);
        if($check_auth !== true){ return $check_auth; }

        // проверка на соответсиве роли пользователя
        $check_role = new RoleMiddleware();
        $check_role = $check_role->check_role($request, "Официант");
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
            ($status !== "canceled") &&
            ($status !== "paid-up")
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

        // проверка на доступ к смене для пользователя
        if($search_order->shift_worker !== $search_user[0]->name){
            return response()
                ->json([
                    "error" => [
                        "code" => 403,
                        "message" => "Forbidden! You did not accept this order!"
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

    /**
     * Display the specified resource.
     *
     * @param Request $request
     * @param $id
     * @return bool|JsonResponse|object
     */
    public function get_orders_in_work_shift(Request $request, $id)
    {
        // проверка на авторизацию
        $check_auth = new AuthMiddleware();
        $check_auth = $check_auth->check_auth($request);
        if($check_auth !== true){ return $check_auth; }

        // проверка на соответсиве роли пользователя
        $check_role = new RoleMiddleware();
        $check_role = $check_role->check_role($request, "Официант");
        if($check_role !== true){ return $check_role; }

        // поиск данных в БД
        $search_user = User::where("bearer_token", "=", $request->header("bearer_token"))->get();
        $search_orders = Orders::where("work_shift_id", "=", $id)->where("shift_worker", "=", $search_user[0]->name)->get();
        $search_work_shift = WorkShiftModel::find($id);

        if($search_work_shift === null){
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

        return response()
            ->json([
                "data" => [
                    "id" => $search_work_shift->id,
                    "start" => $search_work_shift->start,
                    "end" => $search_work_shift->end,
                    "active" => $search_work_shift->active,
                    "orders" => $search_orders
                ]
            ])
            ->setStatusCode(200)
            ->header("Content-Type", "application/json");
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param Request $request
     * @param $id
     * @return bool|JsonResponse|object
     */
    public function add_position_to_order(Request $request, $id)
    {
        // проверка на авторизацию
        $check_auth = new AuthMiddleware();
        $check_auth = $check_auth->check_auth($request);
        if($check_auth !== true){ return $check_auth; }

        // проверка на соответсиве роли пользователя
        $check_role = new RoleMiddleware();
        $check_role = $check_role->check_role($request, "Официант");
        if($check_role !== true){ return $check_role; }

        $menu_id = $request->menu_id;
        $count = $request->count;

        $search_order = Orders::find($id);
        $search_position = PositionsModel::find($menu_id);
        $search_user = User::where("bearer_token", "=", $request->header("bearer_token"))->get();
        $work_shift_user = WorkShiftUserModel::where("user_id", "=", $search_user[0]->id)->get();
        $work_shift = WorkShiftModel::find($work_shift_user[0]->work_shift_id);

        $errors = [];

        if(empty($menu_id)) { $errors["menu_id_required"] = "Поле идентификатора меню обязательно к заполнению"; }
        if(empty($count)) { $errors["count_required"] = "Поле идентификатора меню обязательно к заполнению"; }
        if($search_position === null) { $errors["position_not_found"] = "Данной позиции не существует"; }
        if($count > 10 || $count < 1) { $errors["count_invalid"] = "Некорреткное кол-во блюд"; }

        // ответ сервера при ошибке
        $error_validation = new ErrorValidationReturn();
        if(count($errors) !== 0) { return $error_validation->error_validate($errors); }

        // проверка на существование аткивной схемы
        if($work_shift === null || $work_shift->active !== 1){
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

        if($search_order->shift_worker !== $search_user[0]->name){
            return response()
                ->json([
                    "error" => [
                        "code" => 403,
                        "message" => "Forbidden! You did not accept this order!"
                    ]
                ])
                ->setStatusCode(403)
                ->header("Content-Type", "application/json");
        }

        if($search_order->status !== "taken" && $search_order->status !== "preparing"){
            return response()
                ->json([
                    "error" => [
                        "code" => 403,
                        "message" => "Forbidden! Cannot be added to an order with this status"
                    ]
                ])
                ->setStatusCode(403)
                ->header("Content-Type", "application/json");
        }

        $create_position = OrderPositions::create([
            "order_id" => $search_order->id,
            "price" => $search_position->price * $count,
            "count" => $count,
            "position" => $search_position->name_position,
        ]);

        // изменение заказа
        $search_order->update([
            "price" => $search_order->price + $search_position->price_position * $count
        ]);

        // поиск позиций
        $search_positions = OrderPositions::where("order_id", "=", $id)->get();

        // возвращение ответа сервера
        return response()
            ->json([
                "id" => $search_order->id,
                "table" => $search_order->table,
                "shift_workers" => $search_order->shift_worker,
                "create_at" => $search_order->created_at,
                "status" => $search_order->status,
                "price" => $search_order->price,
                "positions" => $search_positions
            ])
            ->setStatusCode(200)
            ->header("Content-Type", "application/json");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param $order_id
     * @param $position_id
     * @return bool|JsonResponse|object
     */
    public function delete_position(Request $request, $order_id, $position_id)
    {
        // проверка на авторизацию
        $check_auth = new AuthMiddleware();
        $check_auth = $check_auth->check_auth($request);
        if($check_auth !== true){ return $check_auth; }

        // проверка на соответсиве роли пользователя
        $check_role = new RoleMiddleware();
        $check_role = $check_role->check_role($request, "Официант");
        if($check_role !== true){ return $check_role; }

        // поиск даных
        $search_order = Orders::find($order_id);
        $search_position = OrderPositions::find($position_id);

        // проверка на существование заказа
        if($search_position === null){
            return response()
                ->json([
                    "error"=> [
                        "code" => 403,
                        "message" => "Position not found in order"
                    ]
                ])
                ->setStatusCode(403)
                ->header("Content-Type", "application/json");
        }

        $search_user = User::where("bearer_token", "=", $request->header("bearer_token"))->get();
        $search_work_shift_user = WorkShiftUserModel::
            where("user_id", "=", $search_user[0]->id)
            ->get();
        $search_work_shift = WorkShiftModel::find($search_work_shift_user[0]->work_shift_id);

        // проверка на принадлежность заказа определенному официанту
        if($search_order !== null && $search_order->shift_worker !== $search_user[0]->name){
            return response()
                ->json([
                    "error"=> [
                        "code" => 403,
                        "message" => "Forbidden! You did not accept this order!"
                    ]
                ])
                ->setStatusCode(403)
                ->header("Content-Type", "application/json");
        }

        // проверка на активность смены
        if($search_work_shift === null || $search_work_shift->active !== 1){
            return response()
                ->json([
                    "error"=> [
                        "code" => 403,
                        "message" => "You cannot change the order status of a closed shift!"
                    ]
                ])
                ->setStatusCode(403)
                ->header("Content-Type", "application/json");
        }

        // проверка на статус
        if($search_order->status !== "taken"){
            return response()
                ->json([
                    "error"=> [
                        "code" => 403,
                        "message" => "Forbidden! Cannot be added to an order with this status"
                    ]
                ])
                ->setStatusCode(403)
                ->header("Content-Type", "application/json");
        }

        // удаление элемента
        $search_position->delete();

        $search_positions = OrderPositions::where("order_id", "=", $order_id)->get();

        // возвращение ответа сервера
        return response()
            ->json([
                "id" => $search_order->id,
                "table" => $search_order->table,
                "shift_workers" => $search_order->shift_worker,
                "create_at" => $search_order->created_at,
                "status" => $search_order->status,
                "price" => $search_order->price,
                "positions" => $search_positions
            ])
            ->setStatusCode(200)
            ->header("Content-Type", "application/json");
    }

}
