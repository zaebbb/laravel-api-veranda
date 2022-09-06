<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\CookController;
use App\Http\Controllers\OfficiantController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post("/login", [UserController::class, "auth_login"]);
Route::get("/logout", [UserController::class, "auth_exit"]);

// функции админа
Route::get("/user", [AdminController::class, "all_users"]);
Route::post("/user", [AdminController::class, "add_user"]);
Route::post("/work-shift", [AdminController::class, "create_work_shift"]);
Route::get("/work-shift", [AdminController::class, "get_work_shift"]);
Route::post("/work-shift/{id}/user", [AdminController::class, "add_user_to_shift_work"]);
Route::get("/work-shift/{id}/order", [AdminController::class, "get_orders_in_one_work_shift"]);

// функции официанта
Route::post("/order", [OfficiantController::class, "create_order"]);
Route::get("/order/{id}", [OfficiantController::class, "get_order"]);
Route::post("/order/{id}/change-status", [OfficiantController::class, "update_status"]);
Route::get("/work-shift/{id}/orders", [OfficiantController::class, "get_orders_in_work_shift"]);
Route::post("/order/{id}/position", [OfficiantController::class, "add_position_to_order"]);
Route::delete("/order/{order_id}/position/{position_id}", [OfficiantController::class, "delete_position"]);

// функции повара
Route::get("/order/taken/get", [CookController::class, "get_orders"]);
Route::post("/order/{id}/change-status/cook", [CookController::class, "update_status"]);
