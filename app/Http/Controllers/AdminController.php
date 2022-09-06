<?php

namespace App\Http\Controllers;

use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\ErrorValidationReturn;
use App\Http\Middleware\RoleMiddleware;
use App\Models\Orders;
use App\Models\RoleModel;
use App\Models\User;
use App\Models\WorkShiftModel;
use App\Models\WorkShiftUserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use function Ramsey\Uuid\v4;

class AdminController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return bool|JsonResponse|object
     */
    public function all_users(Request $request)
    {
        // проверка на авторизацию
        $check_auth = new AuthMiddleware();
        $check_auth = $check_auth->check_auth($request);
        if($check_auth !== true){ return $check_auth; }

        // проверка на соответсиве роли пользователя
        $check_role = new RoleMiddleware();
        $check_role = $check_role->check_role($request, "Администратор");
        if($check_role !== true){ return $check_role; }

        // получение всех пользователей
        $search_user = User::all();
        $result_users = [];

        // получение всех ролей
        $search_role = RoleModel::all();

        // преобразование result
        foreach($search_user as $user){
            $result_users[] = [
                "id" => $user->id,
                "name" => $user->name,
                "login" => $user->login,
                "status" => $user->status,
                // получаем по номеру в массиве роль
                "group" => $search_role[$user->role_id - 1]->name_role,
            ];
        }

        return response()
            ->json([
                "data" => $result_users
            ])
            ->header("Content-Type", "application/json")
            ->setStatusCode(200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return bool|JsonResponse|object
     */
    public function add_user(Request $request)
    {
        // проверка на авторизацию
        $check_auth = new AuthMiddleware();
        $check_auth = $check_auth->check_auth($request);
        if($check_auth !== true){ return $check_auth; }

        // проверка на соответсиве роли пользователя
        $check_role = new RoleMiddleware();
        $check_role = $check_role->check_role($request, "Администратор");
        if($check_role !== true){ return $check_role; }

        $name = $request->name;
        $surname = $request->surname;
        $patronymic = $request->patronymic;
        $login = $request->login;
        $password = $request->password;
        $photo_file = $request->hasFile("photo_file");
        $role_id = $request->role_id;

        $errors = [];

        // поиск лоигна (должен быть уникальным)
        $search_login = User::where("login", "=", $login)->get();

        // проверка на заполненность поля
        if(empty($name)) { $errors["name_required"] = "Имя обязательно для заполнения"; }
        if(empty($login)) { $errors["login_required"] = "Логин обязателен для заполнения"; }
        if(empty($password)) { $errors["password_required"] = "Пароль обязателен для заполнения"; }
        if(empty($role_id)) { $errors["role_id_required"] = "Идентификатор роли обязателен для заполнения"; }

        // проверка на уникальность
        if(count($search_login) !== 0) { $errors["login_unique"] = "Введеный вами логин уже существует в системе"; }

        // проверка на наличие картинки (при отсутвии игнорируется)
        if($photo_file){
            // првоерка на тип файла
            if(
                !strpos($request->file("photo_file")->getClientOriginalName(), ".jpg") &&
                !strpos($request->file("photo_file")->getClientOriginalName(), ".png") &&
                !strpos($request->file("photo_file")->getClientOriginalName(), ".jpeg")
            ){
                $errors["image_type"] = "тип файла не соотвуетсвует допустимым (png, jpg, jpeg)";
            }
        }

        // если есть ошибки то вернуть ответ о недопустимости данного запроса
        $validation_error = new ErrorValidationReturn();
        if(count($errors) !== 0) {
            return $validation_error->error_validate($errors);
        }

        // дефолтное название имени файла
        $filename = "";

        if($photo_file){
            // если файл существует то выплнить его перемещение
            $filename = v4() . ".jpg";
            $request->file("photo_file")->move(public_path("/photo_profile"), $filename);
        }

        // генерация токена
        $generate_token = "Bearer " . Str::random(60);

        // создание пользователя
        $create_user = User::create([
            "name" => $name,
            "surname" => $surname,
            "patronymic" => $patronymic,
            "login" => $login,
            "password" => password_hash($password, PASSWORD_DEFAULT),
            "photo_file" => $filename,
            "role_id" => $role_id,
            "bearer_token" => $generate_token,
            "status" => "Created"
        ]);

        // ответ сервера
        return response()
            ->json([
                "data" => [
                    "id" => $create_user->id,
                    "status" => $create_user->status
                ]
            ])
            ->setStatusCode(201)
            ->header("Content-Type", "application/json");
    }

    /**
     * Display the specified resource.
     *
     * @param Request $request
     * @return bool|JsonResponse|object
     */
    public function create_work_shift(Request $request)
    {
        // проверка на авторизацию
        $check_auth = new AuthMiddleware();
        $check_auth = $check_auth->check_auth($request);
        if($check_auth !== true){ return $check_auth; }

        // проверка на соответсиве роли пользователя
        $check_role = new RoleMiddleware();
        $check_role = $check_role->check_role($request, "Администратор");
        if($check_role !== true){ return $check_role; }

        // получаем данные из запроса
        $start = $request->start;
        $end = $request->end;

        $errors = [];

        // валидация полей
        if(empty($start)) { $errors["start_required"] = "Поле старта смены обязательно к заполнению"; }
        if(empty($end)) { $errors["start_required"] = "Поле старта смены обязательно к заполнению"; }
        if($start < date("Y-m-d H:i")) { $errors["start_invalid"] = "Начало смены не соответсвует актуальности даты"; }
        if($start > $end) { $errors["end_invalid"] = "Конец смены не соответсвует актуальности даты"; }

        // ошибка валидации с ответом от сервера
        $error_validate = new ErrorValidationReturn();
        if(count($errors) !== 0) { return $error_validate->error_validate($errors); }

        // создание смены
        $create_work_shift = WorkShiftModel::create([
            "start" => $start,
            "end" => $end,
            "status" => "Created"
        ]);

        // ответ сервера
        return response()
            ->json([
                "data" => [
                    "id" => $create_work_shift->id,
                    "status" => $create_work_shift->status,
                ]
            ])
            ->setStatusCode(201)
            ->header("Content-Type", "application/json");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @return bool|JsonResponse|object
     */
    public function add_user_to_shift_work(Request $request, $id)
    {
        // проверка на авторизацию
        $check_auth = new AuthMiddleware();
        $check_auth = $check_auth->check_auth($request);
        if($check_auth !== true){ return $check_auth; }

        // проверка на соответсиве роли пользователя
        $check_role = new RoleMiddleware();
        $check_role = $check_role->check_role($request, "Администратор");
        if($check_role !== true){ return $check_role; }

        // получаем данные из запроса
//        $work_shift_id = $request->work_shift_id;
        $work_shift_id = $id;
        $user_id = $request->user_id;

        $errors = [];

        $search_user = User::
            where("id", "=", $user_id)
            ->where("status", "=", "working")
            ->get();
        $search_work_shift = WorkShiftModel::find($work_shift_id);

        // валидация полей
        if(empty($work_shift_id)) { $errors["start_required"] = "Идентификатор смены обязателен к заполнению"; }
        if(empty($user_id)) { $errors["user_id_required"] = "Идентификатор пользователя обязателен к заполнению"; }
        // проверка на существование
        if($search_work_shift === null) { $errors["work_shift_id_not_found"] = "Смена не найдена"; }
        if(count($search_user) === 0) { $errors["user_id_not_found"] = "Активный пользователь не найден"; }

        // ошибка валидации с ответом от сервера
        $error_validate = new ErrorValidationReturn();
        if(count($errors) !== 0) { return $error_validate->error_validate($errors); }

        // поиск пользователя в таблице
        $search_user_to_work_shift = WorkShiftUserModel::
            where("user_id", "=", $user_id)
            ->where("work_shift_id", "=", $work_shift_id)
            ->get();

        if(count($search_user_to_work_shift) !== 0){
            return response()
                ->json([
                    "error" => [
                        "code" => 403,
                        "message" => "Forbidden. The worker is already on shift!"
                    ]
                ])
                ->setStatusCode(403)
                ->header("Content-Type", "application/json");
        }

        // добавление пользователя на смену
        $create_user_to_work_shift = WorkShiftUserModel::create([
            "work_shift_id" => $work_shift_id,
            "user_id" => $user_id,
            "status" => "Created"
        ]);

        // ответ сервера
        return response()
            ->json([
                "data" => [
                    "id" => $create_user_to_work_shift->id,
                    "status" => $create_user_to_work_shift->status
                ]
            ])
            ->setStatusCode(201)
            ->header("Content-Type", "application/json");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request
     * @param $id
     * @return bool|JsonResponse|object
     */
    public function get_orders_in_one_work_shift(Request $request, $id)
    {
        // проверка на авторизацию
        $check_auth = new AuthMiddleware();
        $check_auth = $check_auth->check_auth($request);
        if($check_auth !== true){ return $check_auth; }

        // проверка на соответсиве роли пользователя
        $check_role = new RoleMiddleware();
        $check_role = $check_role->check_role($request, "Администратор");
        if($check_role !== true){ return $check_role; }

        $search_work_shift = WorkShiftModel::find($id);
        $search_orders = Orders::where("work_shift_id", "=", $id)->get();

        return response()
            ->json([
                "data" => [
                    "id" => $search_work_shift->id,
                    "start" => $search_work_shift->start,
                    "end" => $search_work_shift->end,
                    "active" => 1,
                    "orders" => $search_orders
                ]
            ])
            ->setStatusCode(200)
            ->header("Content-Type", "application/json");
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request
     * @param $id
     * @return bool|JsonResponse|object
     */
    public function get_work_shift(Request $request)
    {
        // проверка на авторизацию
        $check_auth = new AuthMiddleware();
        $check_auth = $check_auth->check_auth($request);
        if($check_auth !== true){ return $check_auth; }

        $search_work_shift = WorkShiftModel::all();

        return response()
            ->json([
                "data" => $search_work_shift
            ])
            ->setStatusCode(200)
            ->header("Content-Type", "application/json");
    }
}
