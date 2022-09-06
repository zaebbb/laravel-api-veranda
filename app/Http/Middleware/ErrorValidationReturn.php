<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Auth\Middleware\Authenticate as Middleware;

class ErrorValidationReturn
{
    public function error_validate($errors = [])
    {
        return response()
            ->json([
                "error" => [
                    "code" => 422,
                    "message" => "validation",
                    "errors" => $errors
                ]
            ])
            ->setStatusCode(422)
            ->header("Content-Type", "application/json");
    }
}
