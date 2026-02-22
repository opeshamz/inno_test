<?php

use App\Http\Controllers\EmployeeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - HR Service
|--------------------------------------------------------------------------
*/

Route::apiResource('employees', EmployeeController::class);
