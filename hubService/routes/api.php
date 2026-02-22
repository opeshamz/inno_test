<?php

use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\SchemaController;
use App\Http\Controllers\StepsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Hub Service
|--------------------------------------------------------------------------
*/

// Feature 1: Checklist
Route::get('/checklists', [ChecklistController::class, 'index']);

// Feature 2: Server-Driven UI
Route::get('/steps',              [StepsController::class, 'index']);
Route::get('/employees',          [EmployeeController::class, 'index']);
Route::get('/schema/{step_id}',   [SchemaController::class, 'show']);
